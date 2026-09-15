<?php

namespace Database\Factories;

use App\Models\VehicleIncident;
use App\Models\VehicleIncidentPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleIncidentPhoto>
 */
class VehicleIncidentPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_incident_id' => VehicleIncident::factory(),
            'stage' => VehicleIncidentPhoto::STAGE_BEFORE,
            'file_path' => 'vehicle-incident-photos/'.fake()->uuid().'.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => now(),
        ];
    }
}
