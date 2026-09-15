<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Space;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleIncident;
use App\Models\VehicleLog;
use Illuminate\Database\Seeder;

/**
 * Realistic placeholder content, requested by Unique 2026-09-16, so
 * new screens have something real to look at instead of empty
 * states. Everything is safe to re-run (firstOrCreate throughout) and
 * everything here is editable/removable from the app afterward,
 * nothing here is a structural decision.
 *
 * Vehicles are real vehicles the Prophet actually has, not invented,
 * per Unique directly. Spaces are the real named areas of the
 * building, also per Unique directly. Asset types and the specific
 * assets under each space are Claude's own reasonable inventory of
 * what a church/event venue actually has, meant as a realistic
 * starting set to edit from, not a claim that this exact list is
 * accurate to the building.
 */
class DummyContentSeeder extends Seeder
{
    public function run(): void
    {
        $spaces = $this->seedSpaces();
        $assetTypes = $this->seedAssetTypes();
        $this->seedAssets($spaces, $assetTypes);
        $vehicles = $this->seedVehicles();
        $this->seedVehicleLogs($vehicles);
        $this->seedVehicleIncidents($vehicles);
    }

    /**
     * @return array<string, Space>
     */
    private function seedSpaces(): array
    {
        $definitions = [
            'Main Church Auditorium' => false,
            'Reception Area' => false,
            "Children's Church" => false,
            'Compound' => false,
            "Prophet's Office" => true,
            "Prophet's Quarters" => true,
            'Gallery (Choir & Media)' => false,
            'Offices' => false,
        ];

        $spaces = [];
        foreach ($definitions as $name => $isRestricted) {
            $spaces[$name] = Space::firstOrCreate(['name' => $name], ['is_restricted' => $isRestricted]);
        }

        return $spaces;
    }

    /**
     * @return array<string, AssetType>
     */
    private function seedAssetTypes(): array
    {
        // [name => category], category is only for photo matching
        // (assetTypeImages.js), null just means it falls back to the
        // default image, that's a cosmetic gap, not a data problem.
        $definitions = [
            'AC Unit' => 'HVAC',
            'Sound System' => 'Audio',
            'Mixing Console' => 'Audio',
            'Projector & Screen' => null,
            'Stage Lighting' => null,
            'Chairs' => 'Furniture',
            'Pews / Benches' => 'Furniture',
            'Podium / Pulpit' => 'Furniture',
            'Office Furniture' => 'Furniture',
            'Window' => null,
            'Door' => null,
            'Handrail' => null,
            'Flooring / Tiles' => null,
            'Staircase / Steps' => null,
            'Generator' => null,
            'Electrical Panel' => null,
            'Plumbing Fixture' => null,
            'Camera (Media / CCTV)' => null,
            'Fire Extinguisher' => null,
            'Ceiling Fan' => null,
        ];

        $types = [];
        foreach ($definitions as $name => $category) {
            $types[$name] = AssetType::firstOrCreate(['name' => $name], ['category' => $category]);
        }

        return $types;
    }

    /**
     * @param  array<string, Space>  $spaces
     * @param  array<string, AssetType>  $types
     */
    private function seedAssets(array $spaces, array $types): void
    {
        // [space name => [[asset name, type name], ...]]
        $layout = [
            'Main Church Auditorium' => [
                ['AC Unit - Auditorium Left Wing', 'AC Unit'],
                ['AC Unit - Auditorium Right Wing', 'AC Unit'],
                ['Sound System - Main PA', 'Sound System'],
                ['Mixing Console - FOH', 'Mixing Console'],
                ['Projector & Screen - Main Stage', 'Projector & Screen'],
                ['Stage Lighting - Rig 1', 'Stage Lighting'],
                ['Chairs - Auditorium Seating (Block A)', 'Chairs'],
                ['Chairs - Auditorium Seating (Block B)', 'Chairs'],
                ["Main Pulpit", 'Podium / Pulpit'],
                ['Auditorium East Windows', 'Window'],
                ['Main Entrance Doors', 'Door'],
                ['Auditorium Floor', 'Flooring / Tiles'],
                ['Stage Steps Handrail', 'Handrail'],
                ['Stage Steps', 'Staircase / Steps'],
                ['Fire Extinguisher - Auditorium Rear', 'Fire Extinguisher'],
                ['Ceiling Fans - Auditorium', 'Ceiling Fan'],
            ],
            'Reception Area' => [
                ['AC Unit - Reception', 'AC Unit'],
                ['Chairs - Reception Waiting Area', 'Chairs'],
                ['Reception Entrance Door', 'Door'],
                ['Reception Windows', 'Window'],
                ['Reception Floor', 'Flooring / Tiles'],
            ],
            "Children's Church" => [
                ["Children's Church Seating", 'Chairs'],
                ["Children's Church Windows", 'Window'],
                ["Children's Church Door", 'Door'],
                ["Children's Church Floor", 'Flooring / Tiles'],
            ],
            'Compound' => [
                ['Main Generator (Big)', 'Generator'],
                ['Backup Generator (Small)', 'Generator'],
                ['Compound Main Electrical Panel', 'Electrical Panel'],
                ['Compound Gate', 'Door'],
            ],
            "Prophet's Office" => [
                ["Prophet's Office AC Unit", 'AC Unit'],
                ["Prophet's Desk & Cabinet", 'Office Furniture'],
                ["Prophet's Office Window", 'Window'],
                ["Prophet's Office Door", 'Door'],
            ],
            "Prophet's Quarters" => [
                ['Quarters AC Unit', 'AC Unit'],
                ['Quarters Furniture', 'Office Furniture'],
                ['Quarters Windows', 'Window'],
                ['Quarters Door', 'Door'],
                ['Quarters Floor', 'Flooring / Tiles'],
            ],
            'Gallery (Choir & Media)' => [
                ['Gallery Monitor Mix', 'Sound System'],
                ['Gallery Console', 'Mixing Console'],
                ['Gallery Camera 1', 'Camera (Media / CCTV)'],
                ['Gallery Camera 2', 'Camera (Media / CCTV)'],
                ['Gallery Seating', 'Chairs'],
                ['Gallery AC Unit', 'AC Unit'],
            ],
            'Offices' => [
                ['General Offices AC Unit', 'AC Unit'],
                ['Office Desks & Cabinets', 'Office Furniture'],
                ['Office Windows', 'Window'],
                ['Office Door', 'Door'],
            ],
        ];

        foreach ($layout as $spaceName => $assets) {
            $space = $spaces[$spaceName];

            foreach ($assets as [$assetName, $typeName]) {
                Asset::firstOrCreate(
                    ['name' => $assetName, 'space_id' => $space->id],
                    ['asset_type_id' => $types[$typeName]->id]
                );
            }
        }
    }

    /**
     * @return array<string, Vehicle>
     */
    private function seedVehicles(): array
    {
        // Real vehicles, per Unique directly. Document expiry dates
        // are deliberately spread across expired / expiring-soon /
        // valid, so the new Vehicles screen has something real to
        // show for every badge state, not just the happy path.
        $definitions = [
            "Prophet's EAC (Hybrid)" => ['plate' => 'PRF-001EAC', 'insurance' => -10, 'roadworthiness' => 20, 'license' => 200],
            "Prophet's Goya (Hybrid)" => ['plate' => 'PRF-002GOY', 'insurance' => 300, 'roadworthiness' => 15, 'license' => 300],
            "Prophet's Range Rover" => ['plate' => 'PRF-003RRV', 'insurance' => 200, 'roadworthiness' => -5, 'license' => 180],
            "Prophet's Camry" => ['plate' => 'PRF-004CAM', 'insurance' => 250, 'roadworthiness' => 250, 'license' => 250],
            "Prophet's Brabus" => ['plate' => 'PRF-005BRB', 'insurance' => 25, 'roadworthiness' => 300, 'license' => 300],
            "Prophet's Escalade" => ['plate' => 'PRF-006ESC', 'insurance' => 180, 'roadworthiness' => 180, 'license' => -20],
            "Prophet's Toyota Tundra" => ['plate' => 'PRF-007TUN', 'insurance' => 300, 'roadworthiness' => 300, 'license' => 300],
        ];

        $vehicles = [];
        foreach ($definitions as $name => $spec) {
            $vehicles[$name] = Vehicle::firstOrCreate(
                ['plate_number' => $spec['plate']],
                [
                    'name' => $name,
                    'document_expiry' => [
                        'insurance' => now()->addDays($spec['insurance'])->toDateString(),
                        'roadworthiness' => now()->addDays($spec['roadworthiness'])->toDateString(),
                        'license' => now()->addDays($spec['license'])->toDateString(),
                    ],
                ]
            );
        }

        return $vehicles;
    }

    /**
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedVehicleLogs(array $vehicles): void
    {
        foreach ($vehicles as $vehicle) {
            if ($vehicle->logs()->exists()) {
                continue;
            }

            VehicleLog::create([
                'vehicle_id' => $vehicle->id,
                'type' => VehicleLog::TYPE_FUEL,
                'value' => fake()->randomFloat(2, 30, 80),
                'logged_at' => now()->subDays(3),
            ]);

            VehicleLog::create([
                'vehicle_id' => $vehicle->id,
                'type' => VehicleLog::TYPE_MILEAGE,
                'value' => fake()->numberBetween(20000, 150000),
                'logged_at' => now()->subDay(),
            ]);
        }
    }

    /**
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedVehicleIncidents(array $vehicles): void
    {
        $reporter = User::where('is_admin', true)->first();

        if (! $reporter) {
            $this->command?->warn('No admin user exists yet, skipping vehicle incident dummy content.');

            return;
        }

        $brabus = $vehicles["Prophet's Brabus"];
        $rangeRover = $vehicles["Prophet's Range Rover"];

        if (! $brabus->incidents()->exists()) {
            VehicleIncident::create([
                'vehicle_id' => $brabus->id,
                'reported_by_user_id' => $reporter->id,
                'title' => 'AC not blowing cold',
                'description' => 'Started a few days ago, worse at highway speed. Needs a look before the next long trip.',
                'status' => VehicleIncident::STATUS_IN_REPAIR,
                'mechanic_name' => 'Bayo Motors',
                'parts_used' => 'AC compressor, refrigerant refill',
                'cost' => 145000,
                'reported_at' => now()->subDays(5),
            ]);
        }

        if (! $rangeRover->incidents()->exists()) {
            VehicleIncident::create([
                'vehicle_id' => $rangeRover->id,
                'reported_by_user_id' => $reporter->id,
                'title' => 'Front left tyre worn out',
                'description' => 'Visible wear, replaced before it became a safety issue.',
                'status' => VehicleIncident::STATUS_COMPLETED,
                'mechanic_name' => 'Coscharis Service Center',
                'parts_used' => 'One tyre, wheel alignment',
                'cost' => 95000,
                'reported_at' => now()->subDays(20),
                'resolved_at' => now()->subDays(18),
            ]);
        }
    }
}
