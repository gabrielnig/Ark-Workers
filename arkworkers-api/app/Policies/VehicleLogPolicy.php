<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLog;

/**
 * Logging fuel/mileage/service is deliberately not manager-only, the
 * assigned driver needs to be able to log their own vehicle's usage
 * day to day, same reasoning as TaskPolicy letting the assigned
 * worker complete their own task.
 */
class VehicleLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VehicleLog $vehicleLog): bool
    {
        return $this->canActOnVehicle($user, $vehicleLog->vehicle);
    }

    public function create(User $user, Vehicle $vehicle): bool
    {
        return $this->canActOnVehicle($user, $vehicle);
    }

    public function delete(User $user, VehicleLog $vehicleLog): bool
    {
        return $user->hasManagementPermission();
    }

    private function canActOnVehicle(User $user, Vehicle $vehicle): bool
    {
        return $user->hasManagementPermission() || $vehicle->assigned_driver_id === $user->id;
    }
}
