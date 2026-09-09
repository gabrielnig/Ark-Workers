<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TaskCompletionConflict>
 */
class TaskCompletionConflictFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'kept_user_id' => User::factory(),
            'kept_completed_at' => now(),
            'discarded_user_id' => User::factory(),
            'discarded_attempted_at' => now(),
        ];
    }
}
