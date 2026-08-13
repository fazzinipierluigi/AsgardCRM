<?php

namespace Fazzinipierluigi\AsgardCRM\Database\Factories;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionPhase;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionType;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowAction;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowAction>
 */
class WorkflowActionFactory extends Factory
{
    protected $model = WorkflowAction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory(),
            'actionable_type' => WorkflowNode::class,
            'actionable_id' => WorkflowNode::factory(),
            'phase' => WorkflowActionPhase::After,
            'sequence' => 0,
            'type' => WorkflowActionType::SetVariable,
            'config' => [],
        ];
    }
}
