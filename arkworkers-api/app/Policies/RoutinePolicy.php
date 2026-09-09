<?php

namespace App\Policies;

use App\Models\Routine;
use App\Models\User;

/**
 * A routine is space-scoped only when it is bound to a concrete asset.
 * A type-level default template (asset_id null) has no space and is
 * not restricted, per SECURITY.md §4.2, since it does not expose any
 * actual space's data.
 */
class RoutinePolicy
{
    public function __construct(private readonly SpacePolicy $spacePolicy) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Routine $routine): bool
    {
        if (! $routine->isAssetBound()) {
            return true;
        }

        return $this->spacePolicy->view($user, $routine->asset->space);
    }

    public function create(User $user): bool
    {
        return $user->hasManagementPermission();
    }

    public function update(User $user, Routine $routine): bool
    {
        return $this->view($user, $routine) && $user->hasManagementPermission();
    }

    public function delete(User $user, Routine $routine): bool
    {
        return $user->bypassesSpaceRestrictions();
    }
}
