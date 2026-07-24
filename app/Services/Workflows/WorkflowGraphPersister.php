<?php

namespace App\Services\Workflows;

use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reads/writes a Workflow's graph (variables, nodes, edges, and every
 * action attached to a node or edge) as one plain array — the shape
 * the MaxGraph editor posts on save and the shape export()/import()
 * move around as JSON. Nodes/edges don't have stable IDs on the wire
 * (the editor invents its own for a new, unsaved graph), so every node
 * carries a `key` string and edges/actions reference their node by
 * that key instead of a database id.
 *
 * Saving never touches the live graph in place: it writes to the
 * workflow's current *draft* WorkflowVersion (creating one, on its
 * first save, from the next version number), so repeated saves of the
 * same in-progress edit — even just dragging a node — don't each mint
 * a new version. A draft only takes effect for new instances once
 * publish() promotes it. Every already-running WorkflowInstance stays
 * pinned to the version it started with (see WorkflowEngine::start()),
 * untouched either way.
 */
class WorkflowGraphPersister
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Workflow $workflow): array
    {
        $version = $this->currentDraft($workflow) ?? $workflow->currentVersion;

        if (! $version) {
            return [
                'name' => $workflow->name,
                'description' => $workflow->description,
                'is_active' => $workflow->is_active,
                'variables' => [],
                'nodes' => [],
                'edges' => [],
            ];
        }

        $version->load(['variables', 'nodes.actions', 'edges.actions']);

        return [
            'name' => $workflow->name,
            'description' => $workflow->description,
            'is_active' => $workflow->is_active,
            'version' => $version->version,
            'version_status' => $version->status->value,
            'published_version' => $workflow->currentVersion?->version,
            'variables' => $version->variables->map(fn ($variable) => [
                'name' => $variable->name,
                'type' => $variable->type->value,
                'default_value' => $variable->default_value,
            ])->values()->all(),
            'nodes' => $version->nodes->map(fn (WorkflowNode $node) => [
                'key' => (string) $node->id,
                'type' => $node->type->value,
                'name' => $node->name,
                'pos_x' => $node->pos_x,
                'pos_y' => $node->pos_y,
                'config' => $node->config,
                'actions' => $this->actionsArray($node->actions),
            ])->values()->all(),
            'edges' => $version->edges->map(fn (WorkflowEdge $edge) => [
                'source_key' => (string) $edge->source_node_id,
                'target_key' => (string) $edge->target_node_id,
                'label' => $edge->label,
                'sequence' => $edge->sequence,
                'condition_logic' => $edge->condition_logic,
                'waypoints' => $edge->waypoints,
                'actions' => $this->actionsArray($edge->actions),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, WorkflowAction>  $actions
     * @return array<string, list<array<string, mixed>>>
     */
    private function actionsArray($actions): array
    {
        $grouped = ['before' => [], 'after' => []];

        foreach ($actions as $action) {
            $grouped[$action->phase->value][] = [
                'type' => $action->type->value,
                'config' => $action->config,
            ];
        }

        return $grouped;
    }

    /**
     * The workflow's draft version, if one is currently being worked on
     * (not yet promoted to live by publish()).
     */
    private function currentDraft(Workflow $workflow): ?WorkflowVersion
    {
        return $workflow->versions()->where('status', WorkflowVersionStatus::Draft->value)->first();
    }

    /**
     * Writes the graph into the workflow's current draft version,
     * replacing it wholesale — creating the draft, at the next version
     * number, on the first save since the last publish() (or ever).
     * Repeated saves of the same in-progress edit reuse that same draft
     * instead of minting a new version each time; nothing references a
     * draft's id (only a published version can become
     * workflow.current_version_id or a WorkflowInstance's
     * workflow_version_id), so discarding and recreating it here is
     * safe.
     *
     * @param  array<string, mixed>  $graph
     *
     * @throws RuntimeException if the graph is structurally invalid
     */
    public function replace(Workflow $workflow, array $graph): WorkflowVersion
    {
        $this->assertValidGraph($graph);

        return DB::transaction(function () use ($workflow, $graph) {
            $draft = $this->currentDraft($workflow);
            $versionNumber = $draft?->version ?? ($workflow->versions()->max('version') + 1);
            $draft?->delete();

            $version = $workflow->versions()->create([
                'version' => $versionNumber,
                'status' => WorkflowVersionStatus::Draft,
            ]);

            foreach ($graph['variables'] ?? [] as $variableInput) {
                $version->variables()->create([
                    'name' => $variableInput['name'],
                    'type' => $variableInput['type'],
                    'default_value' => $variableInput['default_value'] ?? null,
                ]);
            }

            $keyToId = [];
            foreach ($graph['nodes'] as $nodeInput) {
                $node = $version->nodes()->create([
                    'type' => $nodeInput['type'],
                    'name' => $nodeInput['name'],
                    'pos_x' => (int) ($nodeInput['pos_x'] ?? 0),
                    'pos_y' => (int) ($nodeInput['pos_y'] ?? 0),
                    'config' => $nodeInput['config'] ?? [],
                ]);
                $keyToId[$nodeInput['key']] = $node->id;

                $this->createActions($version, $node, $nodeInput['actions'] ?? []);
            }

            foreach ($graph['edges'] ?? [] as $index => $edgeInput) {
                $edge = $version->edges()->create([
                    'source_node_id' => $keyToId[$edgeInput['source_key']],
                    'target_node_id' => $keyToId[$edgeInput['target_key']],
                    'label' => $edgeInput['label'] ?? null,
                    'sequence' => $edgeInput['sequence'] ?? $index,
                    'condition_logic' => $edgeInput['condition_logic'] ?? null,
                    'waypoints' => $edgeInput['waypoints'] ?? null,
                ]);

                $this->createActions($version, $edge, $edgeInput['actions'] ?? []);
            }

            return $version;
        });
    }

    /**
     * Promotes the workflow's current draft to published, making it the
     * version new instances start against. The draft that was live
     * before (if any) is left exactly as it was — still published,
     * still what any instance still running against it stays pinned to
     * — only workflow.current_version_id moves forward.
     *
     * @throws RuntimeException if there is no draft to publish
     */
    public function publish(Workflow $workflow): WorkflowVersion
    {
        $draft = $this->currentDraft($workflow);

        if (! $draft) {
            throw new RuntimeException('Non c\'è nessuna bozza da pubblicare.');
        }

        return DB::transaction(function () use ($workflow, $draft) {
            $draft->update([
                'status' => WorkflowVersionStatus::Published,
                'published_at' => now(),
            ]);

            $workflow->update(['current_version_id' => $draft->id]);

            return $draft;
        });
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $actionsByPhase
     */
    private function createActions(WorkflowVersion $version, WorkflowNode|WorkflowEdge $actionable, array $actionsByPhase): void
    {
        foreach (WorkflowActionPhase::cases() as $phase) {
            foreach (array_values($actionsByPhase[$phase->value] ?? []) as $sequence => $actionInput) {
                $actionable->actions()->create([
                    'workflow_version_id' => $version->id,
                    'phase' => $phase->value,
                    'sequence' => $sequence,
                    'type' => $actionInput['type'],
                    'config' => $actionInput['config'] ?? [],
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $graph
     *
     * @throws RuntimeException
     */
    private function assertValidGraph(array $graph): void
    {
        if (empty($graph['nodes']) || ! is_array($graph['nodes'])) {
            throw new RuntimeException('Il workflow deve avere almeno un nodo.');
        }

        $keys = [];
        $startCount = 0;

        foreach ($graph['nodes'] as $nodeInput) {
            if (empty($nodeInput['key']) || empty($nodeInput['type']) || empty($nodeInput['name'])) {
                throw new RuntimeException('Ogni nodo deve avere una chiave, un tipo e un nome.');
            }

            if (WorkflowNodeType::tryFrom($nodeInput['type']) === null) {
                throw new RuntimeException("Tipo di nodo sconosciuto: {$nodeInput['type']}.");
            }

            if (isset($keys[$nodeInput['key']])) {
                throw new RuntimeException("Chiave nodo duplicata: {$nodeInput['key']}.");
            }
            $keys[$nodeInput['key']] = true;

            if ($nodeInput['type'] === WorkflowNodeType::Start->value) {
                $startCount++;
            }
        }

        if ($startCount !== 1) {
            throw new RuntimeException('Il workflow deve avere esattamente un nodo di avvio.');
        }

        foreach ($graph['edges'] ?? [] as $edgeInput) {
            if (empty($edgeInput['source_key']) || empty($edgeInput['target_key'])) {
                throw new RuntimeException('Ogni arco deve avere un nodo di partenza e uno di arrivo.');
            }

            if (! isset($keys[$edgeInput['source_key']]) || ! isset($keys[$edgeInput['target_key']])) {
                throw new RuntimeException('Un arco fa riferimento a un nodo inesistente.');
            }
        }
    }
}
