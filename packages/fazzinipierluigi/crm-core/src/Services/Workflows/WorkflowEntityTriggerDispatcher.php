<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Workflows;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowNodeType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTriggerType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\Workflow;

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
        $this->dispatch($record, [WorkflowTriggerType::EntityCreated, WorkflowTriggerType::EntityCreatedOrUpdated], []);
    }

    /**
     * $record->getOriginal() is still the pre-update snapshot here —
     * Eloquent fires the 'updated' event before syncOriginal() runs.
     * It's threaded through as the __entity_previous system variable so
     * the "è cambiato" condition operators (see
     * WorkflowConditionEvaluator::expandChangeOperators()) can compare
     * against it later, even from a gateway reached long after this
     * trigger fired.
     */
    public function handleUpdated(EntityRecord $record): void
    {
        $this->dispatch($record, [WorkflowTriggerType::EntityUpdated, WorkflowTriggerType::EntityCreatedOrUpdated], $record->getOriginal());
    }

    /**
     * @param  list<WorkflowTriggerType>  $triggerTypes
     * @param  array<string, mixed>  $previousValues
     */
    private function dispatch(EntityRecord $record, array $triggerTypes, array $previousValues): void
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

            $this->engine->start($workflow, ['__entity_previous' => $previousValues], $record, entitySlug: $entity->slug);
        }
    }
}
