<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskProof;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskProof>
 */
class TaskProofFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'file_path' => 'task-proofs/'.fake()->uuid().'.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => now(),
        ];
    }
}
