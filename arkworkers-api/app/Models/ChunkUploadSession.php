<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChunkUploadSession extends Model
{
    use HasFactory, Prunable;

    protected $fillable = [
        'task_id',
        'user_id',
        'original_filename',
        'declared_mime_type',
        'total_size',
        'chunk_size',
        'total_chunks',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunkDirectory(): string
    {
        return "chunk-uploads/{$this->id}";
    }

    public function chunkPath(int $index): string
    {
        return "{$this->chunkDirectory()}/{$index}";
    }

    /**
     * Which chunk indices have already been received, read straight
     * from disk since that is the actual source of truth, not a
     * separate per-chunk DB row that could drift out of sync with it.
     *
     * @return array<int, int>
     */
    public function receivedChunkIndexes(): array
    {
        $received = [];

        for ($i = 0; $i < $this->total_chunks; $i++) {
            if (Storage::disk('local')->exists($this->chunkPath($i))) {
                $received[] = $i;
            }
        }

        return $received;
    }

    public function hasAllChunks(): bool
    {
        return count($this->receivedChunkIndexes()) === $this->total_chunks;
    }

    public function deleteChunkFiles(): void
    {
        Storage::disk('local')->deleteDirectory($this->chunkDirectory());
    }

    /**
     * An abandoned upload (started, never completed) past its expiry.
     * A completed session is deleted immediately in
     * ChunkedUploadController::complete() and never reaches here.
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now());
    }

    protected function pruning(): void
    {
        $this->deleteChunkFiles();
    }
}
