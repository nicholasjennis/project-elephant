<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numberBetween(1000, 9999),
            'phase_task' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'gpm_user_id' => User::factory()->state(['role' => 'gpm']),
            'project_status' => fake()->randomElement(['DONE', 'in progress', 'Upcoming', 'Wait for FBs']),
            'due_date' => fake()->optional()->date(),
        ];
    }
}
