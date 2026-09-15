<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\VehicleIncident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleIncidentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_assigned_driver_can_report_a_broken_part(): void
    {
        $driver = $this->staffUser();
        $vehicle = Vehicle::factory()->create(['assigned_driver_id' => $driver->id]);

        $response = $this->actingAs($driver, 'sanctum')->postJson("/api/vehicles/{$vehicle->id}/incidents", [
            'title' => 'AC not blowing cold',
            'description' => 'Started yesterday, worse on the highway.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('vehicle_incidents', [
            'vehicle_id' => $vehicle->id,
            'title' => 'AC not blowing cold',
            'reported_by_user_id' => $driver->id,
            'status' => VehicleIncident::STATUS_REPORTED,
        ]);
    }

    public function test_a_different_staff_member_cannot_report_for_someone_elses_vehicle(): void
    {
        $driver = $this->staffUser();
        $otherStaff = $this->staffUser();
        $vehicle = Vehicle::factory()->create(['assigned_driver_id' => $driver->id]);

        $this->actingAs($otherStaff, 'sanctum')->postJson("/api/vehicles/{$vehicle->id}/incidents", [
            'title' => 'Flat tire',
        ])->assertForbidden();
    }

    public function test_a_manager_can_update_status_mechanic_parts_and_cost(): void
    {
        $incident = VehicleIncident::factory()->create();

        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->patchJson("/api/vehicle-incidents/{$incident->id}", [
                'status' => 'in_repair',
                'mechanic_name' => 'Bayo Motors',
                'parts_used' => 'Compressor, refrigerant refill',
                'cost' => 45000,
            ]);

        $response->assertOk();
        $incident->refresh();
        $this->assertSame('in_repair', $incident->status);
        $this->assertSame('Bayo Motors', $incident->mechanic_name);
        $this->assertNull($incident->resolved_at);
    }

    public function test_marking_completed_stamps_resolved_at_automatically(): void
    {
        $incident = VehicleIncident::factory()->inRepair()->create();

        $this->actingAs($this->managerUser(), 'sanctum')
            ->patchJson("/api/vehicle-incidents/{$incident->id}", ['status' => 'completed'])
            ->assertOk();

        $incident->refresh();
        $this->assertSame('completed', $incident->status);
        $this->assertNotNull($incident->resolved_at);
    }

    public function test_the_reporting_driver_cannot_update_mechanic_or_cost(): void
    {
        $driver = $this->staffUser();
        $vehicle = Vehicle::factory()->create(['assigned_driver_id' => $driver->id]);
        $incident = VehicleIncident::factory()->create([
            'vehicle_id' => $vehicle->id,
            'reported_by_user_id' => $driver->id,
        ]);

        $this->actingAs($driver, 'sanctum')
            ->patchJson("/api/vehicle-incidents/{$incident->id}", ['status' => 'completed'])
            ->assertForbidden();
    }

    public function test_index_lists_a_vehicles_incidents_newest_first(): void
    {
        $vehicle = Vehicle::factory()->create();
        $older = VehicleIncident::factory()->create(['vehicle_id' => $vehicle->id, 'reported_at' => now()->subDays(2)]);
        $newer = VehicleIncident::factory()->create(['vehicle_id' => $vehicle->id, 'reported_at' => now()]);

        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson("/api/vehicles/{$vehicle->id}/incidents");

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$newer->id, $older->id], $ids->all());
    }
}
