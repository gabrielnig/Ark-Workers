<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

/**
 * Vehicles aren't space-scoped like Assets, there's no restricted-zone
 * concept here (SECURITY.md §4.2 doesn't apply). A manager/Admin
 * manages the fleet; the assigned driver can additionally see their
 * own vehicle, since they're the one actually driving it day to day.
 */
class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->hasManagementPermission() || $vehicle->assigned_driver_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasManagementPermission();
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->hasManagementPermission();
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->hasManagementPermission();
    }
}
