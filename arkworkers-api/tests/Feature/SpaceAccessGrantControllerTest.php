<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\SpaceAccessGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaceAccessGrantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_list_grants_for_a_space(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $manager = $this->managerUser();
        $admin = $this->adminUser();

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/spaces/{$space->id}/access-grants")
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/spaces/{$space->id}/access-grants")
            ->assertOk();
    }

    public function test_admin_can_grant_a_named_user_access_to_a_restricted_space(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = $this->staffUser();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/spaces/{$space->id}/access-grants",
            ['user_id' => $cleaner->id]
        );

        $response->assertCreated();
        $this->assertDatabaseHas('space_access_grants', [
            'space_id' => $space->id,
            'user_id' => $cleaner->id,
            'granted_by' => $admin->id,
        ]);
    }

    public function test_response_shape_does_not_collide_granted_by_id_with_the_granter_relation(): void
    {
        // SpaceAccessGrant::grantedBy() snake-cases to "granted_by",
        // identical to the actual FK column name. A naive ->load() and
        // raw serialization silently overwrites the integer id with
        // the nested user object. This asserts the response keeps
        // both, distinctly.
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = $this->staffUser();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/spaces/{$space->id}/access-grants",
            ['user_id' => $cleaner->id]
        );

        $response->assertJson(['data' => ['granted_by' => $admin->id]]);
        $this->assertEquals($admin->name, $response->json('data.granted_by_name'));
    }

    public function test_a_manager_cannot_grant_access_even_to_a_space_they_cannot_see(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = $this->staffUser();
        $manager = $this->managerUser();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/spaces/{$space->id}/access-grants", ['user_id' => $cleaner->id])
            ->assertForbidden();
    }

    public function test_granting_the_same_user_twice_returns_a_clean_conflict_not_a_crash(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = $this->staffUser();
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/spaces/{$space->id}/access-grants", ['user_id' => $cleaner->id])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/spaces/{$space->id}/access-grants", ['user_id' => $cleaner->id])
            ->assertStatus(409);
    }

    public function test_granting_access_actually_lets_that_user_see_the_space(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = $this->staffUser();
        $admin = $this->adminUser();

        $this->actingAs($cleaner, 'sanctum')
            ->getJson('/api/spaces')
            ->assertJsonMissing(['id' => $space->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/spaces/{$space->id}/access-grants", ['user_id' => $cleaner->id])
            ->assertCreated();

        $this->actingAs($cleaner, 'sanctum')
            ->getJson('/api/spaces')
            ->assertJsonFragment(['id' => $space->id]);
    }

    public function test_admin_can_revoke_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = $this->staffUser();
        $admin = $this->adminUser();
        $grant = SpaceAccessGrant::factory()->create([
            'space_id' => $space->id,
            'user_id' => $cleaner->id,
            'granted_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/access-grants/{$grant->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('space_access_grants', ['id' => $grant->id]);
    }

    public function test_revoking_a_grant_actually_removes_that_users_access(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = $this->staffUser();
        $admin = $this->adminUser();
        $grant = SpaceAccessGrant::factory()->create([
            'space_id' => $space->id,
            'user_id' => $cleaner->id,
            'granted_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/access-grants/{$grant->id}")->assertNoContent();

        $this->actingAs($cleaner, 'sanctum')
            ->getJson('/api/spaces')
            ->assertJsonMissing(['id' => $space->id]);
    }

    public function test_only_admin_can_revoke_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = $this->staffUser();
        $admin = $this->adminUser();
        $manager = $this->managerUser();
        $grant = SpaceAccessGrant::factory()->create([
            'space_id' => $space->id,
            'user_id' => $cleaner->id,
            'granted_by' => $admin->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/access-grants/{$grant->id}")
            ->assertForbidden();
    }
}
