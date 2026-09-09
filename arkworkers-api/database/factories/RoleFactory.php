<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'grants_management' => false,
        ];
    }

    public function grantsManagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'grants_management' => true,
        ]);
    }
}
