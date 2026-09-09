<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;

/**
 * Space-level restriction model (SECURITY.md §4.2 / ARCHITECTURE.md §4).
 * Single source of truth for space access. Asset/Routine/Task policies
 * delegate to view() here instead of reimplementing the check.
 */
class SpacePolicy
{
    /**
     * Query scoping (excluding restricted spaces without a grant) happens
     * in the controller, not here. See SECURITY.md §4.2.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * SECURITY.md §4.2: unrestricted spaces are visible to all;
     * restricted spaces require admin/pastor or an explicit grant.
     */
    public function view(User $user, Space $space): bool
    {
        if (! $space->is_restricted) {
            return true;
        }

        return $user->bypassesSpaceRestrictions()
            || $space->hasGrantFor($user);
    }

    public function create(User $user): bool
    {
        return $user->hasManagementPermission();
    }

    /**
     * A grant to view a restricted space's contents doesn't imply
     * permission to change the space's own configuration.
     */
    public function update(User $user, Space $space): bool
    {
        return $user->hasManagementPermission();
    }

    /**
     * Tighter than update: deletion cascades to everything scoped
     * under the space.
     */
    public function delete(User $user, Space $space): bool
    {
        return $user->bypassesSpaceRestrictions();
    }
}
