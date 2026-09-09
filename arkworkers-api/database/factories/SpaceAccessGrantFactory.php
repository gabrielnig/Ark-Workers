<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\SpaceAccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaceAccessGrant>
 */
class SpaceAccessGrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'space_id' => Space::factory(),
            'granted_by' => User::factory()->admin(),
            'granted_at' => now(),
        ];
    }
}
