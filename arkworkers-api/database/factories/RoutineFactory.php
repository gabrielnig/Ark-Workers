<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Routine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Routine>
 */
class RoutineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'asset_type_id' => null,
            'name' => fake()->words(3, true),
            'calendar_interval_days' => 30,
            'meter_threshold' => null,
            'requires_proof' => false,
        ];
    }

    /**
     * A type-level default template, not bound to any specific asset.
     */
    public function typeLevelTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_id' => null,
            'asset_type_id' => AssetType::factory(),
        ]);
    }
}
