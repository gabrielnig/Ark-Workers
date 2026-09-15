<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleIncident;

/**
 * Reporting a broken part is deliberately not manager-only, same
 * reasoning as VehicleLogPolicy, the assigned driver is usually the
 * one who notices something's wrong. Resolving it (mechanic, parts,
 * cost, marking complete) is manager+, that's real bookkeeping, not
 * a driver's job.
 */
class VehicleIncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VehicleIncident $vehicleIncident): bool
    {
        return $this->canReport($user, $vehicleIncident->vehicle);
    }

    public function create(User $user, Vehicle $vehicle): bool
    {
        return $this->canReport($user, $vehicle);
    }

    public function update(User $user, VehicleIncident $vehicleIncident): bool
    {
        return $user->hasManagementPermission();
    }

    private function canReport(User $user, Vehicle $vehicle): bool
    {
        return $user->hasManagementPermission() || $vehicle->assigned_driver_id === $user->id;
    }
}
