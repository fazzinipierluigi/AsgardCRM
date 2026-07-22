<?php

namespace Database\Factories;

use App\Enums\WorkflowInstanceStatus;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowInstance>
 */
class WorkflowInstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workflow = Workflow::factory();

        return [
            'workflow_id' => $workflow,
            'workflow_version_id' => WorkflowVersion::factory(),
            'status' => WorkflowInstanceStatus::Running,
            'variables' => [],
            'started_at' => now(),
        ];
    }
}
