<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['asset_type_id', 'space_id', 'name', 'metadata'])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, Prunable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'decommissioned_at' => 'datetime',
        ];
    }

    /**
     * Marks the asset decommissioned and soft-deletes it. Routine,
     * task, and proof history stays intact and queryable until the
     * scheduled prune permanently removes it, 30 days later.
     */
    public function decommission(): void
    {
        $this->forceFill(['decommissioned_at' => now()])->save();
        $this->delete();
    }

    /**
     * Assets decommissioned more than 30 days ago are eligible for
     * permanent removal via `php artisan model:prune`.
     */
    public function prunable(): Builder
    {
        return static::query()
            ->onlyTrashed()
            ->where('decommissioned_at', '<=', now()->subDays(30));
    }

    /**
     * @return BelongsTo<AssetType, $this>
     */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
