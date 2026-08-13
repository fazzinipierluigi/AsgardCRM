<?php

namespace Fazzinipierluigi\AsgardCRM\Database\Factories;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTokenStatus;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowInstance;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowToken>
 */
class WorkflowTokenFactory extends Factory
{
    protected $model = WorkflowToken::class;

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
