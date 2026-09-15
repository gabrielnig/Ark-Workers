<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_staff_member_cannot_view_the_directory(): void
    {
        $this->actingAs($this->staffUser(), 'sanctum')
            ->getJson('/api/staff')
            ->assertForbidden();
    }

    public function test_a_manager_or_admin_can_view_the_directory(): void
    {
        $this->actingAs($this->managerUser(), 'sanctum')->getJson('/api/staff')->assertOk();
        $this->actingAs($this->adminUser(), 'sanctum')->getJson('/api/staff')->assertOk();
    }

    public function test_only_approved_workers_are_listed(): void
    {
        User::factory()->create(['email_verified_at' => now(), 'name' => 'Approved Worker']);
        User::factory()->create(['email_verified_at' => null, 'name' => 'Still Pending']);

        $response = $this->actingAs($this->adminUser(), 'sanctum')->getJson('/api/staff');

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Approved Worker', $names->all());
        $this->assertNotContains('Still Pending', $names->all());
    }

    public function test_response_includes_department_and_role_but_never_the_password(): void
    {
        $department = Department::factory()->create(['name' => 'Choir']);
        $role = Role::factory()->create(['name' => 'Supervisor']);
        $department->roles()->attach($role);

        $worker = User::factory()->create(['email_verified_at' => now(), 'name' => 'Chidi Eze']);
        $worker->joinDepartment($department, $role);

        $response = $this->actingAs($this->adminUser(), 'sanctum')->getJson('/api/staff');

        $entry = collect($response->json('data'))->firstWhere('name', 'Chidi Eze');
        $this->assertSame('Choir', $entry['departments'][0]['name']);
        $this->assertSame('Supervisor', $entry['departments'][0]['role']);
        $this->assertArrayNotHasKey('password', $entry);
    }
}
