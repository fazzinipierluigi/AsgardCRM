<?php

namespace App\Services\Workflows;

use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowNodeType;
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
 * Saving never overwrites a graph in place: it publishes a brand new
 * WorkflowVersion and repoints the workflow's current_version_id at
 * it. Every already-running WorkflowInstance stays pinned to the
 * version it started with (see WorkflowEngine::start()), so editing a
 * workflow — even one with instances in flight — never disturbs them.
 */
class WorkflowGraphPersister
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Workflow $workflow): array
    {
        $version = $workflow->currentVersion;

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
     * @param  array<string, mixed>  $graph
     *
     * @throws RuntimeException if the graph is structurally invalid
     */
    public function replace(Workflow $workflow, array $graph): WorkflowVersion
    {
        $this->assertValidGraph($graph);

        return DB::transaction(function () use ($workflow, $graph) {
            $workflow->update([
                'name' => $graph['name'],
                'description' => $graph['description'] ?? null,
                'is_active' => (bool) ($graph['is_active'] ?? true),
            ]);

            $nextVersionNumber = $workflow->versions()->max('version') + 1;
            $version = $workflow->versions()->create(['version' => $nextVersionNumber]);

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
                ]);

                $this->createActions($version, $edge, $edgeInput['actions'] ?? []);
            }

            $workflow->update(['current_version_id' => $version->id]);

            return $version;
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
        if (empty($graph['name']) || ! is_string($graph['name'])) {
            throw new RuntimeException('Il workflow deve avere un nome.');
        }

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
