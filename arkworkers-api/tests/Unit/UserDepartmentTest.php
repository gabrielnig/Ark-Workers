<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_worker_can_belong_to_several_departments_with_different_roles(): void
    {
        $cleaning = Department::factory()->create(['name' => 'Cleaning']);
        $choir = Department::factory()->create(['name' => 'Choir']);
        $supervisor = Role::factory()->create(['name' => 'Supervisor']);
        $member = Role::factory()->create(['name' => 'Member']);

        $user = $this->staffUser();
        $user->joinDepartment($cleaning, $supervisor);
        $user->joinDepartment($choir, $member);

        $departments = $user->departments()->get();

        $this->assertCount(2, $departments);
        $this->assertSame($supervisor->id, $departments->firstWhere('id', $cleaning->id)->pivot->role_id);
        $this->assertSame($member->id, $departments->firstWhere('id', $choir->id)->pivot->role_id);
    }

    public function test_rejoining_a_department_with_a_different_role_replaces_the_old_role_rather_than_duplicating_the_row(): void
    {
        $cleaning = Department::factory()->create();
        $member = Role::factory()->create(['name' => 'Member']);
        $supervisor = Role::factory()->create(['name' => 'Supervisor']);

        $user = $this->staffUser();
        $user->joinDepartment($cleaning, $member);
        $user->joinDepartment($cleaning, $supervisor);

        $departments = $user->departments()->get();

        $this->assertCount(1, $departments, 'Rejoining the same department should not create a second row.');
        $this->assertSame($supervisor->id, $departments->first()->pivot->role_id);
    }

    public function test_management_permission_comes_from_a_department_role_flag_not_the_department_or_role_name(): void
    {
        // Deliberately named departments/roles that sound unprivileged,
        // to prove hasManagementPermission() checks the flag and
        // nothing about the name, department, or role in question.
        $choir = Department::factory()->create(['name' => 'Choir']);
        $provost = Role::factory()->grantsManagement()->create(['name' => 'Provost']);

        $user = $this->staffUser();
        $this->assertFalse($user->hasManagementPermission());

        $user->joinDepartment($choir, $provost);

        $this->assertTrue($user->fresh()->hasManagementPermission());
    }

    public function test_a_department_role_without_the_management_flag_grants_no_management_permission(): void
    {
        $cleaning = Department::factory()->create();
        $member = Role::factory()->create(['grants_management' => false]);

        $user = $this->staffUser();
        $user->joinDepartment($cleaning, $member);

        $this->assertFalse($user->fresh()->hasManagementPermission());
    }
}
