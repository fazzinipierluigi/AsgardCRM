<?php

namespace Fazzinipierluigi\CrmCore\Database\Factories;

use Fazzinipierluigi\CrmCore\Enums\WorkflowVariableType;
use Fazzinipierluigi\CrmCore\Models\WorkflowVariable;
use Fazzinipierluigi\CrmCore\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowVariable>
 */
class WorkflowVariableFactory extends Factory
{
    protected $model = WorkflowVariable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory(),
            'name' => fake()->unique()->word(),
            'type' => WorkflowVariableType::String,
            'default_value' => null,
            'is_system' => false,
        ];
    }
}
