<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Routine;
use App\Models\Space;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_excludes_assets_in_restricted_spaces_without_a_grant(): void
    {
        $visibleSpace = Space::factory()->create(['is_restricted' => false]);
        $hiddenSpace = Space::factory()->create(['is_restricted' => true]);
        $visibleAsset = Asset::factory()->create(['space_id' => $visibleSpace->id]);
        Asset::factory()->create(['space_id' => $hiddenSpace->id]);
        $user = $this->staffUser();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/assets');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$visibleAsset->id], $ids->all());
    }

    public function test_index_includes_the_assets_type_and_category(): void
    {
        // The Spaces screen picks a representative photo per asset
        // based on asset_type.category, this must actually be present
        // in the response or every asset silently falls back to the
        // same generic image.
        $assetType = AssetType::factory()->create(['category' => 'HVAC']);
        Asset::factory()->create(['asset_type_id' => $assetType->id]);
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/assets');

        $this->assertEquals('HVAC', $response->json('data.0.asset_type.category'));
    }

    public function test_cannot_create_an_asset_in_a_restricted_space_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $assetType = AssetType::factory()->create();
        $manager = $this->managerUser();

        $this->actingAs($manager, 'sanctum')->postJson('/api/assets', [
            'asset_type_id' => $assetType->id,
            'space_id' => $space->id,
            'name' => 'Pool pump',
        ])->assertForbidden();
    }

    public function test_admin_can_create_an_asset_of_any_new_type_in_any_space(): void
    {
        // This is the modularity requirement: a brand new asset type
        // (created on the fly) works exactly like any built-in one.
        $space = Space::factory()->create(['is_restricted' => false]);
        $assetType = AssetType::factory()->create(['name' => 'Swimming Pool']);
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/assets', [
            'asset_type_id' => $assetType->id,
            'space_id' => $space->id,
            'name' => 'Main pool',
            'metadata' => ['volume_liters' => 40000],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('assets', ['name' => 'Main pool', 'space_id' => $space->id]);
    }

    public function test_only_privileged_roles_can_delete_an_asset(): void
    {
        $asset = Asset::factory()->create();
        $cleaner = $this->staffUser();
        $admin = $this->adminUser();

        $this->actingAs($cleaner, 'sanctum')
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertNoContent();
    }

    public function test_deleting_an_asset_soft_deletes_it_and_keeps_its_routine_history(): void
    {
        $asset = Asset::factory()->create();
        $routine = Routine::factory()->create(['asset_id' => $asset->id]);
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/assets/{$asset->id}")->assertNoContent();

        $this->assertSoftDeleted($asset);
        $this->assertNotNull($asset->fresh()->decommissioned_at);
        $this->assertDatabaseHas('routines', ['id' => $routine->id]);
    }

    public function test_a_decommissioned_asset_within_the_grace_period_is_not_pruned(): void
    {
        $asset = Asset::factory()->create();
        $asset->decommission();
        $asset->forceFill(['decommissioned_at' => now()->subDays(10)])->save();

        $this->artisan('model:prune', ['--model' => [Asset::class]]);

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_a_decommissioned_asset_past_the_grace_period_is_permanently_pruned(): void
    {
        $asset = Asset::factory()->create();
        $asset->decommission();
        $asset->forceFill(['decommissioned_at' => now()->subDays(31)])->save();

        $this->artisan('model:prune', ['--model' => [Asset::class]]);

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    public function test_pruning_an_asset_does_not_hard_delete_its_routines_or_tasks(): void
    {
        // Regression test for a real bug: routines.asset_id used to
        // cascadeOnDelete, and Asset::prunable() results in a genuine
        // forceDelete() (a real row deletion), which fires the FK
        // constraint at the database level, bypassing SoftDeletes
        // entirely. That was silently hard-deleting the asset's
        // routines, which then cascaded again onto tasks via
        // routines.id's own cascade, reintroducing the exact
        // history-loss problem already fixed for direct routine
        // deletion. asset_id is now nullOnDelete instead.
        $asset = Asset::factory()->create();
        $routine = Routine::factory()->create(['asset_id' => $asset->id]);
        $task = Task::factory()->create(['routine_id' => $routine->id]);

        $asset->decommission();
        $asset->forceFill(['decommissioned_at' => now()->subDays(31)])->save();
        $this->artisan('model:prune', ['--model' => [Asset::class]]);

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('routines', ['id' => $routine->id, 'asset_id' => null]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}
