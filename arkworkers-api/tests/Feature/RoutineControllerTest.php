<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Routine;
use App\Models\Space;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_excludes_asset_bound_routines_in_restricted_spaces_without_a_grant(): void
    {
        $visibleSpace = Space::factory()->create(['is_restricted' => false]);
        $hiddenSpace = Space::factory()->create(['is_restricted' => true]);
        $visibleRoutine = Routine::factory()->create([
            'asset_id' => Asset::factory()->create(['space_id' => $visibleSpace->id])->id,
        ]);
        Routine::factory()->create([
            'asset_id' => Asset::factory()->create(['space_id' => $hiddenSpace->id])->id,
        ]);
        $user = $this->staffUser();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/routines');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$visibleRoutine->id], $ids->all());
    }

    public function test_index_always_includes_type_level_templates_regardless_of_role(): void
    {
        $template = Routine::factory()->typeLevelTemplate()->create();
        $cleaner = $this->staffUser();

        $response = $this->actingAs($cleaner, 'sanctum')->getJson('/api/routines');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($template->id, $ids->all());
    }

    public function test_index_can_be_filtered_by_asset_id(): void
    {
        $asset = Asset::factory()->create();
        $matching = Routine::factory()->create(['asset_id' => $asset->id]);
        Routine::factory()->create();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/routines?asset_id={$asset->id}");

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$matching->id], $ids->all());
    }

    public function test_cannot_create_an_asset_bound_routine_in_a_restricted_space_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $asset = Asset::factory()->create(['space_id' => $space->id]);
        $manager = $this->managerUser();

        $this->actingAs($manager, 'sanctum')->postJson('/api/routines', [
            'asset_id' => $asset->id,
            'name' => 'Wipe counters',
            'calendar_interval_days' => 1,
        ])->assertForbidden();
    }

    public function test_admin_can_create_an_asset_bound_routine(): void
    {
        $asset = Asset::factory()->create();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/routines', [
            'asset_id' => $asset->id,
            'name' => 'Filter check',
            'calendar_interval_days' => 30,
            'requires_proof' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('routines', ['name' => 'Filter check', 'asset_id' => $asset->id]);
    }

    public function test_admin_can_create_a_type_level_template(): void
    {
        $assetType = AssetType::factory()->create();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/routines', [
            'asset_type_id' => $assetType->id,
            'name' => 'Default cleaning',
            'meter_threshold' => 500,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('routines', ['name' => 'Default cleaning', 'asset_type_id' => $assetType->id]);
    }

    public function test_staff_cannot_create_a_routine(): void
    {
        $assetType = AssetType::factory()->create();
        $cleaner = $this->staffUser();

        $this->actingAs($cleaner, 'sanctum')->postJson('/api/routines', [
            'asset_type_id' => $assetType->id,
            'name' => 'Default cleaning',
            'calendar_interval_days' => 30,
        ])->assertForbidden();
    }

    public function test_cannot_supply_both_asset_id_and_asset_type_id(): void
    {
        $asset = Asset::factory()->create();
        $assetType = AssetType::factory()->create();
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')->postJson('/api/routines', [
            'asset_id' => $asset->id,
            'asset_type_id' => $assetType->id,
            'name' => 'Ambiguous routine',
            'calendar_interval_days' => 30,
        ])->assertStatus(422);
    }

    public function test_cannot_supply_neither_asset_id_nor_asset_type_id(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')->postJson('/api/routines', [
            'name' => 'Orphan routine',
            'calendar_interval_days' => 30,
        ])->assertStatus(422);
    }

    public function test_cannot_supply_neither_calendar_interval_nor_meter_threshold(): void
    {
        // A routine with no trigger of either kind would never fire,
        // this is a dead routine, not a valid one.
        $asset = Asset::factory()->create();
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')->postJson('/api/routines', [
            'asset_id' => $asset->id,
            'name' => 'Never fires',
        ])->assertStatus(422);
    }

    public function test_hybrid_trigger_allows_both_calendar_and_meter_values(): void
    {
        $asset = Asset::factory()->create();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/routines', [
            'asset_id' => $asset->id,
            'name' => 'Generator service',
            'calendar_interval_days' => 90,
            'meter_threshold' => 200,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('routines', [
            'name' => 'Generator service',
            'calendar_interval_days' => 90,
            'meter_threshold' => 200,
        ]);
    }

    public function test_manager_can_update_a_routine_they_can_view(): void
    {
        $routine = Routine::factory()->create();
        $manager = $this->managerUser();

        $this->actingAs($manager, 'sanctum')->patchJson("/api/routines/{$routine->id}", [
            'calendar_interval_days' => 14,
        ])->assertOk();

        $this->assertDatabaseHas('routines', ['id' => $routine->id, 'calendar_interval_days' => 14]);
    }

    public function test_staff_cannot_update_a_routine(): void
    {
        $routine = Routine::factory()->create();
        $cleaner = $this->staffUser();

        $this->actingAs($cleaner, 'sanctum')->patchJson("/api/routines/{$routine->id}", [
            'calendar_interval_days' => 14,
        ])->assertForbidden();
    }

    public function test_only_admin_can_delete_a_routine(): void
    {
        $routine = Routine::factory()->create();
        $manager = $this->managerUser();
        $admin = $this->adminUser();

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/routines/{$routine->id}")
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/routines/{$routine->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($routine);
    }

    public function test_deleting_a_routine_does_not_delete_its_task_history(): void
    {
        $routine = Routine::factory()->create();
        $task = Task::factory()->create(['routine_id' => $routine->id]);
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/routines/{$routine->id}")->assertNoContent();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_a_task_belonging_to_a_deleted_routine_still_shows_the_routines_name(): void
    {
        // The whole point of soft-deleting instead of hard-deleting: a
        // worker's completed-task history must still show what routine
        // they did, even after that routine is later removed.
        $routine = Routine::factory()->create(['name' => 'Vacuum stage carpet']);
        $task = Task::factory()->create(['routine_id' => $routine->id]);
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/routines/{$routine->id}")->assertNoContent();

        $this->assertEquals('Vacuum stage carpet', $task->fresh()->routine->name);
    }

    public function test_a_deleted_routine_is_excluded_from_the_index_listing(): void
    {
        $active = Routine::factory()->create();
        $deleted = Routine::factory()->create();
        $deleted->delete();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/routines');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($active->id, $ids->all());
        $this->assertNotContains($deleted->id, $ids->all());
    }

    public function test_a_deleted_routine_is_never_eligible_for_pruning(): void
    {
        // Unlike Asset, Routine is soft-deleted only, never Prunable —
        // task/proof history must stay reachable permanently, not just
        // for a grace period. model:prune should simply have nothing to
        // do for routines, not silently wipe them after some window.
        $routine = Routine::factory()->create();
        $routine->delete();

        $this->artisan('model:prune', ['--model' => [Routine::class]]);

        $this->assertSoftDeleted($routine);
    }
}
