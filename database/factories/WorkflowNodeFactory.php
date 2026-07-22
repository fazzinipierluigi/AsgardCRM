<?php

namespace Database\Factories;

use App\Enums\WorkflowNodeType;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowNode>
 */
class WorkflowNodeFactory extends Factory
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
            'type' => WorkflowNodeType::ServiceTask,
            'name' => fake()->words(2, true),
            'pos_x' => fake()->numberBetween(0, 800),
            'pos_y' => fake()->numberBetween(0, 600),
            'config' => [],
        ];
    }

    public function start(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WorkflowNodeType::Start,
            'name' => 'Avvio',
            'config' => ['trigger_type' => 'manual'],
        ]);
    }

    public function end(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WorkflowNodeType::End,
            'name' => 'Fine',
        ]);
    }
}
