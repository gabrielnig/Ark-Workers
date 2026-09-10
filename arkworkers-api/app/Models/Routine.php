<?php

namespace App\Models;

use Database\Factories\RoutineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['asset_id', 'asset_type_id', 'name', 'calendar_interval_days', 'meter_threshold', 'requires_proof'])]
class Routine extends Model
{
    /**
     * @use HasFactory<RoutineFactory>
     *
     * Soft-delete only, deliberately not Prunable the way Asset is.
     * A deleted routine's task/proof history must remain permanently
     * reachable, not just for a 30-day grace period, so this row is
     * never eligible for `php artisan model:prune`.
     */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'requires_proof' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<AssetType, $this>
     */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * True if this is a concrete, asset-bound routine rather than a
     * type-level default template. Only asset-bound routines are
     * subject to space-level restriction (SECURITY.md §4.2), since a
     * template isn't tied to any actual space's data.
     */
    public function isAssetBound(): bool
    {
        return $this->asset_id !== null;
    }
}
