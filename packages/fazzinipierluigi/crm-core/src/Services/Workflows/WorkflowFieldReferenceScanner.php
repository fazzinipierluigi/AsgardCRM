<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Workflows;

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowVersionStatus;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityField;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowAction;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowEdge;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowVersion;

/**
 * Read-only counterpart to WorkflowFieldReferenceCleaner: before a field
 * is actually deleted, tells the admin what references exist across
 * every workflow version (draft AND published, unlike the cleaner which
 * only ever touches drafts) so they can make an informed choice.
 *
 * Two buckets:
 * - "cleanable": structured references (JsonLogic `var` nodes, or a
 *   `{"column": ...}` entry in an UpdateEntity/CreateEntity/FetchEntity
 *   action) that live in a DRAFT version — exactly what
 *   WorkflowFieldReferenceCleaner will silently strip out the moment the
 *   field is actually deleted.
 * - "manual": everything the cleaner can't or won't touch — the same
 *   kind of structured reference but in a PUBLISHED version (immutable
 *   by design), plus any free-form ExpressionLanguage text (SetVariable,
 *   id_expression, SQL/API binding expressions, FetchEntity condition
 *   expressions, email/template placeholders) that mentions
 *   `entity.<column>` — safely rewriting arbitrary expression text isn't
 *   feasible, so these need a human to go look.
 */
class WorkflowFieldReferenceScanner
{
    public function __construct(private readonly WorkflowJsonLogicVarFinder $varFinder) {}

    /**
     * @return array{cleanable: list<string>, manual: list<string>}
     */
    public function scan(Entity $entity, EntityField $field): array
    {
        $column = $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
        $varPath = "entity.{$column}";

        $cleanable = [];
        $manual = [];

        $versions = WorkflowVersion::with(['workflow', 'nodes.actions', 'edges.actions', 'edges.source', 'edges.target', 'startNode'])->get();

        foreach ($versions as $version) {
            $this->scanVersion($version, $entity->slug, $column, $varPath, $cleanable, $manual);
        }

        return ['cleanable' => array_values(array_unique($cleanable)), 'manual' => array_values(array_unique($manual))];
    }

    /**
     * @param  list<string>  $cleanable
     * @param  list<string>  $manual
     */
    private function scanVersion(WorkflowVersion $version, string $entitySlug, string $column, string $varPath, array &$cleanable, array &$manual): void
    {
        $isDraft = $version->status === WorkflowVersionStatus::Draft;
        $statusLabel = $isDraft ? 'Bozza' : "Pubblicata v{$version->version}";
        $workflowName = $version->workflow->name;

        $startNode = $version->startNode;
        $triggerBound = $startNode
            && in_array($startNode->config['trigger_type'] ?? null, ['entity_created', 'entity_updated', 'entity_created_or_updated'], true)
            && ($startNode->config['entity_slug'] ?? null) === $entitySlug;

        if ($triggerBound && ($startNode->config['start_condition'] ?? null) && $this->varFinder->containsVar($startNode->config['start_condition'], $varPath)) {
            $line = "Flusso «{$workflowName}» ({$statusLabel}) — condizione di avvio";
            $isDraft ? $cleanable[] = $line : $manual[] = $line;
        }

        if ($triggerBound) {
            foreach ($version->edges as $edge) {
                if ($edge->condition_logic && $this->varFinder->containsVar($edge->condition_logic, $varPath)) {
                    $line = "Flusso «{$workflowName}» ({$statusLabel}) — condizione del collegamento «{$this->edgeLabel($edge)}»";
                    $isDraft ? $cleanable[] = $line : $manual[] = $line;
                }
            }
        }

        foreach ($version->nodes as $node) {
            foreach ($node->actions as $action) {
                $this->scanAction($action, $entitySlug, $column, "nodo «{$node->name}»", $triggerBound, $varPath, $workflowName, $statusLabel, $isDraft, $cleanable, $manual);
            }
        }

        foreach ($version->edges as $edge) {
            foreach ($edge->actions as $action) {
                $this->scanAction($action, $entitySlug, $column, "collegamento «{$this->edgeLabel($edge)}»", $triggerBound, $varPath, $workflowName, $statusLabel, $isDraft, $cleanable, $manual);
            }
        }
    }

    /**
     * @param  list<string>  $cleanable
     * @param  list<string>  $manual
     */
    private function scanAction(WorkflowAction $action, string $entitySlug, string $column, string $location, bool $triggerBound, string $varPath, string $workflowName, string $statusLabel, bool $isDraft, array &$cleanable, array &$manual): void
    {
        $config = $action->config;
        $actionLabel = $action->type->label();

        if (($config['entity_slug'] ?? null) === $entitySlug && $this->hasStructuredColumnReference($action, $column)) {
            $line = "Flusso «{$workflowName}» ({$statusLabel}) — azione «{$actionLabel}» nel {$location}";
            $isDraft ? $cleanable[] = $line : $manual[] = $line;
        }

        if ($triggerBound && $this->hasFreeTextReference($action, $column)) {
            $manual[] = "Flusso «{$workflowName}» ({$statusLabel}) — azione «{$actionLabel}» nel {$location} (espressione libera, verificare manualmente)";
        }
    }

    private function hasStructuredColumnReference(WorkflowAction $action, string $column): bool
    {
        $config = $action->config;

        if (in_array($action->type, [WorkflowActionType::UpdateEntity, WorkflowActionType::CreateEntity], true)) {
            foreach ($config['fields'] ?? [] as $entry) {
                if (($entry['column'] ?? null) === $column) {
                    return true;
                }
            }
        }

        if ($action->type === WorkflowActionType::FetchEntity) {
            foreach ($config['conditions'] ?? [] as $entry) {
                if (($entry['column'] ?? null) === $column) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Only expression text belonging to an entry whose OWN column isn't
     * the one being deleted — an entry keyed on the deleted column gets
     * structurally removed already (see hasStructuredColumnReference),
     * so flagging its expression too would just be noise.
     */
    private function hasFreeTextReference(WorkflowAction $action, string $column): bool
    {
        $config = $action->config;
        $pattern = '/\bentity\.'.preg_quote($column, '/').'\b/';

        $texts = match ($action->type) {
            WorkflowActionType::SetVariable => array_filter([$config['expression'] ?? null]),
            WorkflowActionType::AssignEntityToVariable => array_filter([$config['id_expression'] ?? null]),
            WorkflowActionType::SendEmail => array_filter([$config['to'] ?? null, $config['subject'] ?? null, $config['body'] ?? null]),
            WorkflowActionType::UpdateEntity => array_filter(array_merge(
                [$config['id_expression'] ?? null],
                array_column(array_filter($config['fields'] ?? [], fn ($f) => ($f['column'] ?? null) !== $column), 'expression'),
            )),
            WorkflowActionType::CreateEntity => array_filter(
                array_column(array_filter($config['fields'] ?? [], fn ($f) => ($f['column'] ?? null) !== $column), 'expression'),
            ),
            WorkflowActionType::AssignVariableFromSql => array_filter(array_column($config['bindings'] ?? [], 'expression')),
            WorkflowActionType::AssignVariableFromApi => array_filter(array_merge(
                array_column($config['query'] ?? [], 'expression'),
                [$config['path'] ?? null, $config['body'] ?? null],
            )),
            WorkflowActionType::FetchEntity => array_filter(
                array_column(array_filter($config['conditions'] ?? [], fn ($c) => ($c['column'] ?? null) !== $column), 'expression'),
            ),
        };

        foreach ($texts as $text) {
            if (is_string($text) && preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function edgeLabel(WorkflowEdge $edge): string
    {
        if (! empty($edge->label)) {
            return $edge->label;
        }

        $source = $edge->source?->name ?? '?';
        $target = $edge->target?->name ?? '?';

        return "{$source} → {$target}";
    }
}
