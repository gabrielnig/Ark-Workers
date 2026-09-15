<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\VehicleLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_assigned_driver_can_log_fuel_for_their_own_vehicle(): void
    {
        $driver = $this->staffUser();
        $vehicle = Vehicle::factory()->create(['assigned_driver_id' => $driver->id]);

        $response = $this->actingAs($driver, 'sanctum')->postJson("/api/vehicles/{$vehicle->id}/logs", [
            'type' => 'fuel',
            'value' => 45.5,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('vehicle_logs', [
            'vehicle_id' => $vehicle->id,
            'type' => 'fuel',
            'logged_by_user_id' => $driver->id,
        ]);
    }

    public function test_a_different_staff_member_cannot_log_for_someone_elses_vehicle(): void
    {
        $driver = $this->staffUser();
        $otherStaff = $this->staffUser();
        $vehicle = Vehicle::factory()->create(['assigned_driver_id' => $driver->id]);

        $this->actingAs($otherStaff, 'sanctum')->postJson("/api/vehicles/{$vehicle->id}/logs", [
            'type' => 'mileage',
            'value' => 12000,
        ])->assertForbidden();
    }

    public function test_a_manager_can_log_for_any_vehicle_regardless_of_assigned_driver(): void
    {
        $vehicle = Vehicle::factory()->create(['assigned_driver_id' => $this->staffUser()->id]);

        $this->actingAs($this->managerUser(), 'sanctum')->postJson("/api/vehicles/{$vehicle->id}/logs", [
            'type' => 'service',
            'value' => 15000,
        ])->assertCreated();
    }

    public function test_log_type_must_be_one_of_the_known_types(): void
    {
        $manager = $this->managerUser();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($manager, 'sanctum')->postJson("/api/vehicles/{$vehicle->id}/logs", [
            'type' => 'not-a-real-type',
            'value' => 1,
        ])->assertStatus(422);
    }

    public function test_index_lists_logs_newest_first(): void
    {
        $vehicle = Vehicle::factory()->create();
        $older = VehicleLog::factory()->create(['vehicle_id' => $vehicle->id, 'logged_at' => now()->subDays(3)]);
        $newer = VehicleLog::factory()->create(['vehicle_id' => $vehicle->id, 'logged_at' => now()]);

        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson("/api/vehicles/{$vehicle->id}/logs");

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$newer->id, $older->id], $ids->all());
    }

    public function test_only_a_manager_can_delete_a_log_entry(): void
    {
        $vehicle = Vehicle::factory()->create();
        $log = VehicleLog::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($this->staffUser(), 'sanctum')
            ->deleteJson("/api/vehicle-logs/{$log->id}")
            ->assertForbidden();

        $this->actingAs($this->managerUser(), 'sanctum')
            ->deleteJson("/api/vehicle-logs/{$log->id}")
            ->assertNoContent();
    }
}
