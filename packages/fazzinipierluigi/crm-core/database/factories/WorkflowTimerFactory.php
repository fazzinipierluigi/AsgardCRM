<?php

namespace Fazzinipierluigi\CrmCore\Database\Factories;

use Fazzinipierluigi\CrmCore\Enums\WorkflowTimerStatus;
use Fazzinipierluigi\CrmCore\Models\WorkflowInstance;
use Fazzinipierluigi\CrmCore\Models\WorkflowNode;
use Fazzinipierluigi\CrmCore\Models\WorkflowTimer;
use Fazzinipierluigi\CrmCore\Models\WorkflowToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTimer>
 */
class WorkflowTimerFactory extends Factory
{
    protected $model = WorkflowTimer::class;

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
