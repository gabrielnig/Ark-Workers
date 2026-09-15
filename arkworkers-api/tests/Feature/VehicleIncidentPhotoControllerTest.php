<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\VehicleIncident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleIncidentPhotoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reporting_driver_can_attach_a_before_photo(): void
    {
        Storage::fake('local');
        $driver = $this->staffUser();
        $vehicle = Vehicle::factory()->create(['assigned_driver_id' => $driver->id]);
        $incident = VehicleIncident::factory()->create([
            'vehicle_id' => $vehicle->id,
            'reported_by_user_id' => $driver->id,
        ]);

        $file = UploadedFile::fake()->image('broken-part.jpg');

        $response = $this->actingAs($driver, 'sanctum')->post(
            "/api/vehicle-incidents/{$incident->id}/photos",
            ['stage' => 'before', 'photo' => $file]
        );

        $response->assertCreated();
        $this->assertDatabaseHas('vehicle_incident_photos', [
            'vehicle_incident_id' => $incident->id,
            'stage' => 'before',
        ]);
    }

    public function test_a_manager_can_attach_an_after_photo(): void
    {
        Storage::fake('local');
        $incident = VehicleIncident::factory()->completed()->create();

        $file = UploadedFile::fake()->image('fixed.png');

        $this->actingAs($this->managerUser(), 'sanctum')->post(
            "/api/vehicle-incidents/{$incident->id}/photos",
            ['stage' => 'after', 'photo' => $file]
        )->assertCreated();
    }

    public function test_an_unrelated_staff_member_cannot_attach_a_photo(): void
    {
        Storage::fake('local');
        $vehicle = Vehicle::factory()->create(['assigned_driver_id' => $this->staffUser()->id]);
        $incident = VehicleIncident::factory()->create(['vehicle_id' => $vehicle->id]);

        $file = UploadedFile::fake()->image('photo.jpg');

        $this->actingAs($this->staffUser(), 'sanctum')->post(
            "/api/vehicle-incidents/{$incident->id}/photos",
            ['stage' => 'before', 'photo' => $file]
        )->assertForbidden();
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        Storage::fake('local');
        $incident = VehicleIncident::factory()->create();

        $file = UploadedFile::fake()->create('not-a-photo.pdf', 100, 'application/pdf');

        $this->actingAs($this->managerUser(), 'sanctum')->post(
            "/api/vehicle-incidents/{$incident->id}/photos",
            ['stage' => 'before', 'photo' => $file]
        )->assertStatus(422);
    }

    public function test_only_a_manager_can_delete_a_photo(): void
    {
        Storage::fake('local');
        $incident = VehicleIncident::factory()->create();
        $photo = $incident->photos()->create([
            'stage' => 'before',
            'file_path' => 'vehicle-incident-photos/fake.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->staffUser(), 'sanctum')
            ->deleteJson("/api/vehicle-incident-photos/{$photo->id}")
            ->assertForbidden();

        $this->actingAs($this->managerUser(), 'sanctum')
            ->deleteJson("/api/vehicle-incident-photos/{$photo->id}")
            ->assertNoContent();
    }
}
