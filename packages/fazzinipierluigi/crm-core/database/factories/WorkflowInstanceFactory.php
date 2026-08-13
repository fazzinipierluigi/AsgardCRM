<?php

namespace Fazzinipierluigi\AsgardCRM\Database\Factories;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowInstanceStatus;
use Fazzinipierluigi\AsgardCRM\Models\Workflow;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowInstance;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowVersion;
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
