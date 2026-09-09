<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Routine;
use App\Models\Space;
use App\Models\SpaceAccessGrant;
use App\Models\Task;
use App\Models\User;
use App\Policies\SpacePolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TaskPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TaskPolicy(new SpacePolicy);
    }

    private function taskInSpace(Space $space, ?User $assignee = null): Task
    {
        $asset = Asset::factory()->create(['space_id' => $space->id]);
        $routine = Routine::factory()->create(['asset_id' => $asset->id]);

        return Task::factory()->create([
            'routine_id' => $routine->id,
            'assigned_user_id' => ($assignee ?? User::factory()->create())->id,
        ]);
    }

    public function test_task_in_restricted_space_is_not_viewable_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $task = $this->taskInSpace($space);
        $cleaner = $this->staffUser();

        $this->assertFalse($this->policy->view($cleaner, $task));
    }

    public function test_task_in_restricted_space_is_viewable_with_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $admin = $this->adminUser();
        $cleaner = $this->staffUser();
        $task = $this->taskInSpace($space, $cleaner);

        SpaceAccessGrant::factory()->create([
            'user_id' => $cleaner->id,
            'space_id' => $space->id,
            'granted_by' => $admin->id,
        ]);

        $this->assertTrue($this->policy->view($cleaner, $task));
    }

    public function test_a_task_on_a_type_level_routine_fails_closed(): void
    {
        $routine = Routine::factory()->typeLevelTemplate()->create();
        $task = Task::factory()->create(['routine_id' => $routine->id]);
        $admin = $this->adminUser();

        // Even an admin cannot view a task with no resolvable space,
        // since that indicates a data problem, not legitimate access.
        $this->assertFalse($this->policy->view($admin, $task));
    }

    public function test_only_the_assigned_user_or_a_privileged_role_can_update_a_task(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $assignee = $this->staffUser();
        $task = $this->taskInSpace($space, $assignee);

        $someoneElse = $this->staffUser();
        $admin = $this->adminUser();

        $this->assertTrue($this->policy->update($assignee, $task));
        $this->assertFalse($this->policy->update($someoneElse, $task));
        $this->assertTrue($this->policy->update($admin, $task));
    }

    public function test_assigned_user_still_needs_space_access_to_update_a_restricted_task(): void
    {
        // Assignment alone does not substitute for a space grant, per
        // SECURITY.md §4.2: access is role bypass or explicit grant only.
        $space = Space::factory()->create(['is_restricted' => true]);
        $assignee = $this->staffUser();
        $task = $this->taskInSpace($space, $assignee);

        $this->assertFalse($this->policy->update($assignee, $task));
    }
}
