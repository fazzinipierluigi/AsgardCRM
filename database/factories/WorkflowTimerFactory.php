<?php

namespace Database\Factories;

use App\Enums\WorkflowTimerStatus;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Models\WorkflowTimer;
use App\Models\WorkflowToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTimer>
 */
class WorkflowTimerFactory extends Factory
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
            'run_at' => now()->addMinutes(15),
            'status' => WorkflowTimerStatus::Pending,
        ];
    }
}
