<?php

namespace Fazzinipierluigi\AsgardCRM\Database\Factories;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowUserTaskStatus;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowInstance;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowToken;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowUserTask;
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
