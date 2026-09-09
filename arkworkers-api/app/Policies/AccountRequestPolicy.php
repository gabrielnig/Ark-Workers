<?php

namespace App\Policies;

use App\Models\AccountRequest;
use App\Models\User;

/**
 * Approving who gets an account at all is treated as an Admin-only
 * action, not gated by hasManagementPermission() like Space/Asset/
 * Routine/Task. A department supervisor managing their own department
 * should not thereby be able to approve accounts joining unrelated
 * departments company-wide.
 */
class AccountRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function review(User $user, AccountRequest $accountRequest): bool
    {
        return $user->is_admin;
    }
}
