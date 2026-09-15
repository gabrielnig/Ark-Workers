<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_list_vehicles(): void
    {
        Vehicle::factory()->count(2)->create();

        $this->actingAs($this->staffUser(), 'sanctum')
            ->getJson('/api/vehicles')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_manager_can_create_a_vehicle(): void
    {
        $response = $this->actingAs($this->managerUser(), 'sanctum')->postJson('/api/vehicles', [
            'name' => 'Church Bus',
            'plate_number' => 'ABC-123XY',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('vehicles', ['name' => 'Church Bus', 'plate_number' => 'ABC-123XY']);
    }

    public function test_plain_staff_cannot_create_a_vehicle(): void
    {
        $this->actingAs($this->staffUser(), 'sanctum')->postJson('/api/vehicles', [
            'plate_number' => 'ABC-123XY',
        ])->assertForbidden();
    }

    public function test_plate_number_must_be_unique(): void
    {
        Vehicle::factory()->create(['plate_number' => 'ABC-123XY']);

        $this->actingAs($this->managerUser(), 'sanctum')->postJson('/api/vehicles', [
            'plate_number' => 'ABC-123XY',
        ])->assertStatus(422);
    }

    public function test_a_manager_can_assign_a_driver_and_update_document_expiry(): void
    {
        $vehicle = Vehicle::factory()->create();
        $driver = $this->staffUser();

        $this->actingAs($this->managerUser(), 'sanctum')->patchJson("/api/vehicles/{$vehicle->id}", [
            'assigned_driver_id' => $driver->id,
            'document_expiry' => ['insurance' => now()->addYear()->toDateString()],
        ])->assertOk();

        $vehicle->refresh();
        $this->assertSame($driver->id, $vehicle->assigned_driver_id);
        $this->assertSame(now()->addYear()->toDateString(), $vehicle->document_expiry['insurance']);
    }

    public function test_expired_and_expiring_soon_documents_are_computed_in_the_response(): void
    {
        $vehicle = Vehicle::factory()->create([
            'document_expiry' => [
                'insurance' => now()->subDay()->toDateString(),
                'roadworthiness' => now()->addDays(10)->toDateString(),
                'license' => now()->addYear()->toDateString(),
            ],
        ]);

        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson("/api/vehicles/{$vehicle->id}");

        $data = $response->json('data');
        $this->assertArrayHasKey('insurance', $data['expired_documents']);
        $this->assertArrayHasKey('roadworthiness', $data['expiring_soon_documents']);
        $this->assertArrayNotHasKey('license', $data['expired_documents']);
        $this->assertArrayNotHasKey('license', $data['expiring_soon_documents']);
    }

    public function test_only_a_manager_can_delete_a_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->staffUser(), 'sanctum')
            ->deleteJson("/api/vehicles/{$vehicle->id}")
            ->assertForbidden();

        $this->actingAs($this->managerUser(), 'sanctum')
            ->deleteJson("/api/vehicles/{$vehicle->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    public function test_a_staff_member_not_assigned_to_the_vehicle_cannot_view_its_details(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->staffUser(), 'sanctum')
            ->getJson("/api/vehicles/{$vehicle->id}")
            ->assertForbidden();
    }

    public function test_the_assigned_driver_can_view_their_own_vehicles_details(): void
    {
        $driver = $this->staffUser();
        $vehicle = Vehicle::factory()->create(['assigned_driver_id' => $driver->id]);

        $this->actingAs($driver, 'sanctum')
            ->getJson("/api/vehicles/{$vehicle->id}")
            ->assertOk();
    }
}
