<?php

namespace Database\Factories;

use App\Enums\WorkflowVariableType;
use App\Models\WorkflowVariable;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowVariable>
 */
class WorkflowVariableFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'type' => WorkflowVariableType::String,
            'default_value' => null,
            'is_system' => false,
        ];
    }
}
