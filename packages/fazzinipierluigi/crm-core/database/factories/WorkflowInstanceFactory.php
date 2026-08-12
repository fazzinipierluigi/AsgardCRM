<?php

namespace Fazzinipierluigi\CrmCore\Database\Factories;

use Fazzinipierluigi\CrmCore\Enums\WorkflowInstanceStatus;
use Fazzinipierluigi\CrmCore\Models\Workflow;
use Fazzinipierluigi\CrmCore\Models\WorkflowInstance;
use Fazzinipierluigi\CrmCore\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowInstance>
 */
class WorkflowInstanceFactory extends Factory
{
    protected $model = WorkflowInstance::class;

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
