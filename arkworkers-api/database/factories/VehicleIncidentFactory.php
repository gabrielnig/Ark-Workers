<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleIncident>
 */
class VehicleIncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'reported_by_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => VehicleIncident::STATUS_REPORTED,
            'reported_at' => now(),
        ];
    }

    public function inRepair(): static
    {
        return $this->state(fn () => ['status' => VehicleIncident::STATUS_IN_REPAIR]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => VehicleIncident::STATUS_COMPLETED,
            'resolved_at' => now(),
        ]);
    }
}
