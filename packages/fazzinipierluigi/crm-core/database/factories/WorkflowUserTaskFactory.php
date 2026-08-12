<?php

namespace Fazzinipierluigi\CrmCore\Database\Factories;

use Fazzinipierluigi\CrmCore\Enums\WorkflowUserTaskStatus;
use Fazzinipierluigi\CrmCore\Models\WorkflowInstance;
use Fazzinipierluigi\CrmCore\Models\WorkflowNode;
use Fazzinipierluigi\CrmCore\Models\WorkflowToken;
use Fazzinipierluigi\CrmCore\Models\WorkflowUserTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowUserTask>
 */
class WorkflowUserTaskFactory extends Factory
{
    protected $model = WorkflowUserTask::class;

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
