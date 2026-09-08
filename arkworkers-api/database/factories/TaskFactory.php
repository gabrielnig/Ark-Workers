<?php

namespace Database\Factories;

use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'routine_id' => Routine::factory(),
            'assigned_user_id' => User::factory(),
            'due_at' => now()->addDay(),
            'completed_at' => null,
            'status' => Task::STATUS_PENDING,
        ];
    }
}
