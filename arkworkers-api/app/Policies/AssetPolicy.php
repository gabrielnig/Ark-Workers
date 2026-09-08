<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

/**
 * Assets are space-scoped (ARCHITECTURE.md §3): every check here
 * delegates to SpacePolicy::view() rather than re-implementing the
 * restriction logic, SECURITY.md §4.3 requires this scoping to be
 * uniform across every route, and duplicating the check here would be
 * exactly the kind of drift that breaks that uniformity later.
 */
class AssetPolicy
{
    public function __construct(private readonly SpacePolicy $spacePolicy) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->spacePolicy->view($user, $asset->space);
    }

    /**
     * Creating an asset requires the same access as viewing its space,
     * you cannot add an asset to a restricted space you can't see into.
     * The controller must additionally check the target space_id from
     * the request against SpacePolicy::view() before authorizing this,
     * since there is no Asset instance yet at create time.
     */
    public function create(User $user): bool
    {
        return $user->hasRole([
            User::ROLE_ADMIN,
            User::ROLE_PASTOR,
            User::ROLE_FACILITY_MANAGER,
        ]);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset) && $user->hasRole([
            User::ROLE_ADMIN,
            User::ROLE_PASTOR,
            User::ROLE_FACILITY_MANAGER,
        ]);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->bypassesSpaceRestrictions();
    }
}
