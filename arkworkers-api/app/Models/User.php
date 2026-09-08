<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'phone', 'role', 'pin_hash'])]
#[Hidden(['pin_hash', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Canonical role identifiers, per SECURITY.md §4.1.
     * These are the ONLY valid values for the `role` column, enforced
     * in tests, not just documented here.
     */
    public const ROLE_ADMIN = 'admin';

    public const ROLE_PASTOR = 'pastor';

    public const ROLE_FACILITY_MANAGER = 'facility_manager';

    public const ROLE_CLEANING_STAFF = 'cleaning_staff';

    public const ROLE_MAINTENANCE = 'maintenance';

    public const ROLE_SECURITY = 'security';

    public const ROLE_DRIVER = 'driver';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_PASTOR,
        self::ROLE_FACILITY_MANAGER,
        self::ROLE_CLEANING_STAFF,
        self::ROLE_MAINTENANCE,
        self::ROLE_SECURITY,
        self::ROLE_DRIVER,
    ];

    /**
     * Roles that bypass space-level restriction checks entirely
     * (SECURITY.md §4.2 / ARCHITECTURE.md §4).
     */
    public const UNRESTRICTED_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_PASTOR,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'pin_hash' => 'hashed',
        ];
    }

    /**
     * True if the user's role is one of the given roles.
     * Server-side authorization must always go through this (or a
     * Policy built on it), never a client-side role check.
     *
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($this->role, $roles, true);
    }

    /**
     * True if this user's role is exempt from space-level restriction
     * checks (Admin/Pastor), per SECURITY.md §4.2.
     */
    public function bypassesSpaceRestrictions(): bool
    {
        return $this->hasRole(self::UNRESTRICTED_ROLES);
    }

    /**
     * @return HasMany<SpaceAccessGrant, $this>
     */
    public function spaceAccessGrants(): HasMany
    {
        return $this->hasMany(SpaceAccessGrant::class);
    }

    /**
     * @return HasMany<OtpCode, $this>
     */
    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class);
    }
}
