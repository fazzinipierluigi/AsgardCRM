<?php

namespace Database\Factories;

use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowActionType;
use App\Models\WorkflowAction;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowAction>
 */
class WorkflowActionFactory extends Factory
{
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
