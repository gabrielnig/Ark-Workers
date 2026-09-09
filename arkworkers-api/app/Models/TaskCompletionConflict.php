<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskCompletionConflict extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'kept_user_id',
        'kept_completed_at',
        'discarded_user_id',
        'discarded_attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'kept_completed_at' => 'datetime',
            'discarded_attempted_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function keptUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kept_user_id');
    }

    public function discardedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discarded_user_id');
    }
}
