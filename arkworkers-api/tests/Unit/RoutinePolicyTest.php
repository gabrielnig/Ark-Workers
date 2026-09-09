<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Routine;
use App\Models\Space;
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
        $cleaner = $this->staffUser();

        $this->assertTrue($this->policy->view($cleaner, $routine));
    }

    public function test_asset_bound_routine_inherits_its_assets_space_restriction(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $routine = Routine::factory()->create([
            'asset_id' => Asset::factory()->create(['space_id' => $space->id])->id,
        ]);
        $cleaner = $this->staffUser();
        $admin = $this->adminUser();

        $this->assertFalse($this->policy->view($cleaner, $routine));
        $this->assertTrue($this->policy->view($admin, $routine));
    }

    public function test_only_admin_or_a_manager_can_create_routines(): void
    {
        $this->assertTrue($this->policy->create($this->adminUser()));
        $this->assertTrue($this->policy->create($this->managerUser()));
        $this->assertFalse($this->policy->create($this->pastorUser()));
        $this->assertFalse($this->policy->create($this->staffUser()));
    }
}
