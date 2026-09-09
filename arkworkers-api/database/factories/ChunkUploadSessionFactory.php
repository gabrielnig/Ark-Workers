<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ChunkUploadSession>
 */
class ChunkUploadSessionFactory extends Factory
{
    public function definition(): array
    {
        $totalSize = 5 * 1024 * 1024;
        $chunkSize = 1024 * 1024;

        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'original_filename' => 'proof.jpg',
            'declared_mime_type' => 'image/jpeg',
            'total_size' => $totalSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => (int) ceil($totalSize / $chunkSize),
            'expires_at' => now()->addHours(24),
        ];
    }
}
