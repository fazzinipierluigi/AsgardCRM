<?php

namespace Database\Factories;

use App\Enums\WorkflowUserTaskStatus;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Models\WorkflowToken;
use App\Models\WorkflowUserTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowUserTask>
 */
class WorkflowUserTaskFactory extends Factory
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
            'workflow_token_id' => WorkflowToken::factory(),
            'status' => WorkflowUserTaskStatus::Pending,
            'form_data' => null,
        ];
    }
}
