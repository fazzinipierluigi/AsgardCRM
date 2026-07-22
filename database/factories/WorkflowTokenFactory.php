<?php

namespace Database\Factories;

use App\Enums\WorkflowTokenStatus;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Models\WorkflowToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowToken>
 */
class WorkflowTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_instance_id' => WorkflowInstance::factory(),
            'workflow_node_id' => WorkflowNode::factory(),
            'via_edge_id' => null,
            'status' => WorkflowTokenStatus::Active,
        ];
    }
}
