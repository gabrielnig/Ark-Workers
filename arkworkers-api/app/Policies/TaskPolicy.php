<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Tasks are always generated from an asset-bound routine, so a task
 * without one fails closed rather than being treated as unrestricted.
 * This is deliberate: a task with no resolvable space is a data
 * problem, not a reason to grant access.
 */
class TaskPolicy
{
    public function __construct(private readonly SpacePolicy $spacePolicy) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        $routine = $task->routine;

        if (! $routine || ! $routine->isAssetBound()) {
            return false;
        }

        return $this->spacePolicy->view($user, $routine->asset->space);
    }

    /**
     * Assigning tasks is an Admin/Facility Manager action.
     */
    public function create(User $user): bool
    {
        return $user->hasManagementPermission();
    }

    /**
     * Completing a task requires space access AND being the assigned
     * user, or a privileged role reassigning/editing it.
     */
    public function update(User $user, Task $task): bool
    {
        if (! $this->view($user, $task)) {
            return false;
        }

        return $task->assigned_user_id === $user->id
            || $user->hasManagementPermission();
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->bypassesSpaceRestrictions();
    }
}
