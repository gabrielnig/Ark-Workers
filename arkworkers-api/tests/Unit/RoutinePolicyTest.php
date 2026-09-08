<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Routine;
use App\Models\Space;
use App\Models\User;
use App\Policies\RoutinePolicy;
use App\Policies\SpacePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutinePolicyTest extends TestCase
{
    use RefreshDatabase;

    private RoutinePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RoutinePolicy(new SpacePolicy);
    }

    public function test_type_level_template_is_viewable_by_any_role(): void
    {
        $routine = Routine::factory()->typeLevelTemplate()->create();
        $cleaner = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();

        $this->assertTrue($this->policy->view($cleaner, $routine));
    }

    public function test_asset_bound_routine_inherits_its_assets_space_restriction(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $routine = Routine::factory()->create([
            'asset_id' => Asset::factory()->create(['space_id' => $space->id])->id,
        ]);
        $cleaner = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();
        $admin = User::factory()->role(User::ROLE_ADMIN)->create();

        $this->assertFalse($this->policy->view($cleaner, $routine));
        $this->assertTrue($this->policy->view($admin, $routine));
    }

    public function test_only_privileged_roles_can_create_routines(): void
    {
        $allowed = [User::ROLE_ADMIN, User::ROLE_PASTOR, User::ROLE_FACILITY_MANAGER];

        foreach (User::ROLES as $role) {
            $user = User::factory()->role($role)->create();
            $this->assertSame(
                in_array($role, $allowed, true),
                $this->policy->create($user),
                "create() gave wrong result for role: {$role}"
            );
        }
    }
}
