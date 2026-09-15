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

    public function test_only_admin_can_promote_someone_to_admin(): void
    {
        $worker = User::factory()->create(['email_verified_at' => now(), 'is_admin' => false]);

        $this->actingAs($this->managerUser(), 'sanctum')
            ->patchJson("/api/staff/{$worker->id}/admin", ['is_admin' => true])
            ->assertForbidden();

        $this->actingAs($this->adminUser(), 'sanctum')
            ->patchJson("/api/staff/{$worker->id}/admin", ['is_admin' => true])
            ->assertOk();

        $this->assertTrue($worker->fresh()->is_admin);
    }

    public function test_admin_can_demote_another_admin(): void
    {
        $otherAdmin = $this->adminUser();

        $this->actingAs($this->adminUser(), 'sanctum')
            ->patchJson("/api/staff/{$otherAdmin->id}/admin", ['is_admin' => false])
            ->assertOk();

        $this->assertFalse($otherAdmin->fresh()->is_admin);
    }

    public function test_an_admin_cannot_change_their_own_admin_status(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/staff/{$admin->id}/admin", ['is_admin' => false])
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_can_add_a_worker_to_a_department_with_a_role(): void
    {
        $worker = User::factory()->create(['email_verified_at' => now()]);
        $department = Department::factory()->create(['name' => 'Ushering']);
        $role = Role::factory()->create(['name' => 'Member']);
        $department->roles()->attach($role);

        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson("/api/staff/{$worker->id}/departments", [
                'department_id' => $department->id,
                'role_id' => $role->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('department_user', [
            'user_id' => $worker->id,
            'department_id' => $department->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_a_manager_cannot_add_a_worker_to_a_department(): void
    {
        $worker = User::factory()->create(['email_verified_at' => now()]);
        $department = Department::factory()->create();
        $role = Role::factory()->create();

        $this->actingAs($this->managerUser(), 'sanctum')
            ->postJson("/api/staff/{$worker->id}/departments", [
                'department_id' => $department->id,
                'role_id' => $role->id,
            ])->assertForbidden();
    }

    public function test_admin_can_remove_a_worker_from_a_department(): void
    {
        $worker = User::factory()->create(['email_verified_at' => now()]);
        $department = Department::factory()->create();
        $role = Role::factory()->create();
        $department->roles()->attach($role);
        $worker->joinDepartment($department, $role);

        $this->actingAs($this->adminUser(), 'sanctum')
            ->deleteJson("/api/staff/{$worker->id}/departments/{$department->id}")
            ->assertOk();

        $this->assertDatabaseMissing('department_user', [
            'user_id' => $worker->id,
            'department_id' => $department->id,
        ]);
    }

    public function test_only_admin_can_view_department_options(): void
    {
        $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson('/api/staff/department-options')
            ->assertForbidden();

        $department = Department::factory()->create(['name' => 'Sound']);
        $role = Role::factory()->create(['name' => 'Member']);
        $department->roles()->attach($role);

        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/staff/department-options');

        $response->assertOk();
        $entry = collect($response->json('data'))->firstWhere('name', 'Sound');
        $this->assertSame('Member', $entry['roles'][0]['name']);
    }
}
