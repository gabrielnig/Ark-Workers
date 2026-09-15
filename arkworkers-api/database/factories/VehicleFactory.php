<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'plate_number' => strtoupper(fake()->unique()->bothify('???-###??')),
            'assigned_driver_id' => null,
            'document_expiry' => null,
        ];
    }
}
