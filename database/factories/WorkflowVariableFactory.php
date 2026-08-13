<?php

namespace Fazzinipierluigi\AsgardCRM\Database\Factories;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowVariableType;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowVariable;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowVersion;
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
