<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_excludes_restricted_spaces_the_user_has_no_grant_for(): void
    {
        $visible = Space::factory()->create(['is_restricted' => false]);
        $hidden = Space::factory()->create(['is_restricted' => true]);
        $user = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/spaces');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($hidden->id));
    }

    public function test_show_denies_a_restricted_space_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $user = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/spaces/{$space->id}")
            ->assertForbidden();
    }

    public function test_only_privileged_roles_can_create_a_space(): void
    {
        $cleaner = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();
        $admin = User::factory()->role(User::ROLE_ADMIN)->create();

        $this->actingAs($cleaner, 'sanctum')
            ->postJson('/api/spaces', ['name' => 'New Wing'])
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/spaces', ['name' => 'New Wing'])
            ->assertCreated();
    }

    public function test_only_admin_or_pastor_can_delete_a_space(): void
    {
        $space = Space::factory()->create();
        $manager = User::factory()->role(User::ROLE_FACILITY_MANAGER)->create();
        $admin = User::factory()->role(User::ROLE_ADMIN)->create();

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/spaces/{$space->id}")
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/spaces/{$space->id}")
            ->assertNoContent();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/spaces')->assertUnauthorized();
    }

    public function test_deleting_a_space_that_still_has_assets_returns_a_clean_conflict_not_a_server_error(): void
    {
        $space = Space::factory()->create();
        Asset::factory()->create(['space_id' => $space->id]);
        $admin = User::factory()->role(User::ROLE_ADMIN)->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/spaces/{$space->id}")
            ->assertStatus(409);
    }
}
