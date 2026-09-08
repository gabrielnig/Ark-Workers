<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_type_id' => AssetType::factory(),
            'space_id' => Space::factory(),
            'name' => fake()->words(2, true),
            'metadata' => null,
        ];
    }
}
