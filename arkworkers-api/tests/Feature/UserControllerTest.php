<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_search_users_but_manager_and_admin_can(): void
    {
        $staff = $this->staffUser();
        $manager = $this->managerUser();
        $admin = $this->adminUser();

        $this->actingAs($staff, 'sanctum')->getJson('/api/users')->assertForbidden();
        $this->actingAs($manager, 'sanctum')->getJson('/api/users')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/users')->assertOk();
    }

    public function test_search_matches_by_name_or_email(): void
    {
        $admin = $this->adminUser();
        $match = User::factory()->create(['name' => 'Funmilayo Okafor', 'email' => 'funmi@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'other@example.com']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users?search=Funmi');

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Funmilayo Okafor', $names->all());
        $this->assertCount(1, $names);
    }

    public function test_response_never_includes_password_or_sensitive_fields(): void
    {
        $admin = $this->adminUser();
        User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users');

        $response->assertJsonMissingPath('data.0.password');
        $this->assertEquals(['id', 'name', 'email'], array_keys($response->json('data.0')));
    }
}
