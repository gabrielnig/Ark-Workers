<?php

namespace Tests;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A plain worker with no management permission and no department
     * standing beyond existing. Use for "this user should NOT be able
     * to..." cases.
     */
    protected function staffUser(): User
    {
        return User::factory()->create();
    }

    protected function adminUser(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * A non-admin worker who has management permission purely through
     * a department role flagged grants_management, the replacement
     * for the old facility_manager role.
     */
    protected function managerUser(): User
    {
        $role = Role::factory()->grantsManagement()->create();
        $department = Department::factory()->create();
        $department->roles()->attach($role);

        $user = User::factory()->create();
        $user->joinDepartment($department, $role);

        return $user;
    }

    /**
     * A worker holding the Pastor title, per this session's decision
     * this carries zero permission weight, tests should assert this
     * user behaves exactly like staffUser().
     */
    protected function pastorUser(): User
    {
        return User::factory()->title('Pastor')->create();
    }
}
