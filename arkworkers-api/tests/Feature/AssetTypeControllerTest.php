<?php

namespace Tests\Feature;

use App\Models\AssetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_authenticated_can_list_asset_types(): void
    {
        AssetType::factory()->count(2)->create();
        $cleaner = $this->staffUser();

        $this->actingAs($cleaner, 'sanctum')
            ->getJson('/api/asset-types')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_a_brand_new_asset_type_with_no_prior_code_changes(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/asset-types', [
            'name' => 'Swimming Pool',
            'category' => 'recreation',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('asset_types', ['name' => 'Swimming Pool']);
    }

    public function test_ordinary_staff_cannot_create_an_asset_type(): void
    {
        $cleaner = $this->staffUser();

        $this->actingAs($cleaner, 'sanctum')
            ->postJson('/api/asset-types', ['name' => 'Generator'])
            ->assertForbidden();
    }
}
