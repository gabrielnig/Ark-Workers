<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'display_name', 'email', 'phone', 'title', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Departments this worker belongs to, one row per department with
     * that department's specific role available via ->pivot->role.
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)
            ->using(DepartmentUser::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Add or change this worker's standing in a department. A worker
     * has exactly one role per department, so joining again with a
     * different role replaces the previous one rather than adding a
     * second row.
     */
    public function joinDepartment(Department $department, Role $role): void
    {
        $this->departments()->syncWithoutDetaching([
            $department->id => ['role_id' => $role->id],
        ]);
    }

    /**
     * True if Admin, or if any department membership carries a role
     * flagged grants_management. This is the only place that
     * permission logic lives, policies must call this rather than
     * re-deriving it from department/role names.
     */
    public function hasManagementPermission(): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return $this->departments()
            ->get()
            ->contains(fn (Department $department) => $department->pivot->role?->grants_management === true);
    }

    /**
     * True if this user's account is exempt from space-level
     * restriction checks (SECURITY.md §4.2). Admin-only, Pastor is a
     * title with no permission weight and no department role bypasses
     * restricted-space visibility, only grants management actions.
     */
    public function bypassesSpaceRestrictions(): bool
    {
        return $this->is_admin;
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
