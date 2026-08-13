<?php

namespace Fazzinipierluigi\AsgardCRM\Database\Factories;

use Fazzinipierluigi\AsgardCRM\Models\WorkflowEdge;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowEdge>
 */
class WorkflowEdgeFactory extends Factory
{
    protected $model = WorkflowEdge::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory(),
            'source_node_id' => WorkflowNode::factory(),
            'target_node_id' => WorkflowNode::factory(),
            'label' => null,
            'sequence' => 0,
            'condition_logic' => null,
        ];
    }
}
