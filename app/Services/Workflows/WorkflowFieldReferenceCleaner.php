<?php

namespace App\Services\Workflows;

use App\Enums\EntityFieldType;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowVersionStatus;
use App\Models\Entity;
use App\Models\EntityField;
use App\Models\WorkflowAction;
use App\Models\WorkflowVersion;

/**
 * Strips dangling references to a just-deleted entity field out of every
 * workflow's DRAFT version. Published versions are never touched — they
 * must stay immutable for whatever WorkflowInstance is still pinned to
 * them; a published workflow that references the deleted field simply
 * fails at runtime with a clear error the next time it reaches that node,
 * same as it always has for any other kind of broken config.
 *
 * Only structured references are cleaned: a JsonLogic {"var":
 * "entity.colonna"} node inside a start_condition/edge condition_logic
 * (the whole condition is cleared, not surgically edited — removing one
 * operand of a comparison can't leave a valid rule behind), and a
 * {"column": "colonna", ...} entry inside an UpdateEntity/CreateEntity/
 * FetchEntity action's fields/conditions list (just that entry is
 * dropped). Free-form expression strings (SetVariable, id_expression,
 * SQL/API binding expressions) are never rewritten — safely patching
 * arbitrary ExpressionLanguage text isn't feasible, so those are left to
 * fail loudly at runtime instead, a deliberate scope decision.
 */
class WorkflowFieldReferenceCleaner
{
    public function __construct(private readonly WorkflowJsonLogicVarFinder $varFinder) {}

    public function removeReferencesToField(Entity $entity, EntityField $field): void
    {
        $column = $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
        $varPath = "entity.{$column}";

        $drafts = WorkflowVersion::where('status', WorkflowVersionStatus::Draft->value)
            ->with(['nodes.actions', 'edges.actions', 'startNode'])
            ->get();

        foreach ($drafts as $version) {
            $this->cleanVersion($version, $entity->slug, $column, $varPath);
        }
    }

    private function cleanVersion(WorkflowVersion $version, string $entitySlug, string $column, string $varPath): void
    {
        $startNode = $version->startNode;
        $triggerBound = $startNode
            && in_array($startNode->config['trigger_type'] ?? null, ['entity_created', 'entity_updated', 'entity_created_or_updated'], true)
            && ($startNode->config['entity_slug'] ?? null) === $entitySlug;

        if ($triggerBound && ($startNode->config['start_condition'] ?? null) && $this->varFinder->containsVar($startNode->config['start_condition'], $varPath)) {
            $config = $startNode->config;
            $config['start_condition'] = null;
            $startNode->update(['config' => $config]);
        }

        if ($triggerBound) {
            foreach ($version->edges as $edge) {
                if ($edge->condition_logic && $this->varFinder->containsVar($edge->condition_logic, $varPath)) {
                    $edge->update(['condition_logic' => null]);
                }
            }
        }

        foreach ($version->nodes as $node) {
            foreach ($node->actions as $action) {
                $this->cleanAction($action, $entitySlug, $column);
            }
        }

        foreach ($version->edges as $edge) {
            foreach ($edge->actions as $action) {
                $this->cleanAction($action, $entitySlug, $column);
            }
        }
    }

    private function cleanAction(WorkflowAction $action, string $entitySlug, string $column): void
    {
        $config = $action->config;

        if (($config['entity_slug'] ?? null) !== $entitySlug) {
            return;
        }

        $changed = false;

        if (in_array($action->type, [WorkflowActionType::UpdateEntity, WorkflowActionType::CreateEntity], true) && ! empty($config['fields'])) {
            $filtered = array_values(array_filter($config['fields'], fn ($f) => ($f['column'] ?? null) !== $column));
            if (count($filtered) !== count($config['fields'])) {
                $config['fields'] = $filtered;
                $changed = true;
            }
        }

        if ($action->type === WorkflowActionType::FetchEntity && ! empty($config['conditions'])) {
            $filtered = array_values(array_filter($config['conditions'], fn ($c) => ($c['column'] ?? null) !== $column));
            if (count($filtered) !== count($config['conditions'])) {
                $config['conditions'] = $filtered;
                $changed = true;
            }
        }

        if ($changed) {
            $action->update(['config' => $config]);
        }
    }
}
