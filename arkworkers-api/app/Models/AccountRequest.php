<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccountRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['name', 'display_name', 'email', 'phone', 'title'];

    /**
     * Ministry offices offered on the sign-up form (curated dropdown,
     * client-side). Not enforced here as a strict enum, same pattern
     * as AssetType::category, this is a plain administrative label
     * with zero permission weight, never checked by any policy.
     */
    public const MINISTRY_OFFICES = ['Brother', 'Sister', 'Evangelist', 'Deacon', 'Deaconess', 'Pastor'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'invite_expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function inviteIsUsable(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->consumed_at === null
            && $this->invite_expires_at !== null
            && $this->invite_expires_at->isFuture();
    }
}
