<?php

namespace Database\Factories;

use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowEdge>
 */
class WorkflowEdgeFactory extends Factory
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
            'source_node_id' => WorkflowNode::factory(),
            'target_node_id' => WorkflowNode::factory(),
            'label' => null,
            'sequence' => 0,
            'condition_logic' => null,
        ];
    }
}
