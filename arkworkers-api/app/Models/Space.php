<?php

namespace App\Models;

use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'parent_space_id', 'is_restricted'])]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_restricted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Space::class, 'parent_space_id');
    }

    /**
     * @return HasMany<Space, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Space::class, 'parent_space_id');
    }

    /**
     * @return HasMany<SpaceAccessGrant, $this>
     */
    public function accessGrants(): HasMany
    {
        return $this->hasMany(SpaceAccessGrant::class);
    }

    /**
     * Whether the given user has an explicit access grant to this
     * restricted space (SECURITY.md §4.2). Does NOT account for the
     * role bypass, that's handled in SpacePolicy, which is the single
     * place this space-restriction check should be made from.
     */
    public function hasGrantFor(User $user): bool
    {
        return $this->accessGrants()->where('user_id', $user->id)->exists();
    }
}
