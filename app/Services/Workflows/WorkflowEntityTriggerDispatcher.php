<?php

namespace App\Services\Workflows;

use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowTriggerType;
use App\Models\Entity;
use App\Models\EntityRecord;
use App\Models\Workflow;

/**
 * Starts every active workflow whose start node is configured to
 * trigger on an entity's creation/update, when a record of that
 * entity is actually created/updated. Hooked onto EntityRecord's
 * model events in AppServiceProvider::boot() — a record's entity is
 * resolved from its table name since EntityRecord is one generic
 * model shared by every dynamic entity table.
 */
class WorkflowEntityTriggerDispatcher
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    public function handleCreated(EntityRecord $record): void
    {
        $this->dispatch($record, [WorkflowTriggerType::EntityCreated, WorkflowTriggerType::EntityCreatedOrUpdated]);
    }

    public function handleUpdated(EntityRecord $record): void
    {
        $this->dispatch($record, [WorkflowTriggerType::EntityUpdated, WorkflowTriggerType::EntityCreatedOrUpdated]);
    }

    /**
     * @param  list<WorkflowTriggerType>  $triggerTypes
     */
    private function dispatch(EntityRecord $record, array $triggerTypes): void
    {
        $entity = Entity::where('table_name', $record->getTable())->first();

        if (! $entity) {
            return;
        }

        $values = array_map(fn (WorkflowTriggerType $type) => $type->value, $triggerTypes);

        $workflows = Workflow::where('is_active', true)
            ->whereHas('currentVersion.nodes', function ($query) use ($entity, $values) {
                $query->where('type', WorkflowNodeType::Start->value)
                    ->whereIn('config->trigger_type', $values)
                    ->where('config->entity_slug', $entity->slug);
            })
            ->with('currentVersion.startNode')
            ->get();

        foreach ($workflows as $workflow) {
            $occurrence = $workflow->currentVersion?->startNode?->config['occurrence'] ?? 'every_time';

            if ($occurrence === 'once' && $workflow->hasAlreadyTriggeredFor($entity->slug, $record->getKey())) {
                continue;
            }

            $this->engine->start($workflow, [], $record, entitySlug: $entity->slug);
        }
    }
}
