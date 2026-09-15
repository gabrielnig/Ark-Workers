<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleLog>
 */
class VehicleLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'type' => fake()->randomElement(VehicleLog::TYPES),
            'value' => fake()->randomFloat(2, 1, 500),
            'logged_by_user_id' => null,
            'logged_at' => now(),
        ];
    }
}
