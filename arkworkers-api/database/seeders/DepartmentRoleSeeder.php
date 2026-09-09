<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentRoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Starting departments and roles, per this session's decision that
     * both are admin-manageable at runtime. This just gives a fresh
     * install something to start from rather than an empty system,
     * the admin can rename, remove, or add to any of this afterward.
     */
    public function run(): void
    {
        $member = Role::firstOrCreate(['name' => 'Member'], ['grants_management' => false]);
        $supervisor = Role::firstOrCreate(['name' => 'Supervisor'], ['grants_management' => true]);
        $coordinator = Role::firstOrCreate(['name' => 'Coordinator'], ['grants_management' => true]);

        $departmentNames = [
            'Cleaning',
            'Maintenance',
            'Security',
            'Driver',
            'Facility Management',
            'Choir',
            'Sound',
            'Ushering',
        ];

        foreach ($departmentNames as $name) {
            $department = Department::firstOrCreate(['name' => $name]);
            $department->roles()->syncWithoutDetaching([
                $member->id,
                $supervisor->id,
                $coordinator->id,
            ]);
        }
    }
}
