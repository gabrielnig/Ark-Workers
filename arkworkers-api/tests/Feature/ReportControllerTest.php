<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Routine;
use App\Models\Space;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_staff_user_cannot_view_the_daily_summary(): void
    {
        $this->actingAs($this->staffUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertForbidden();
    }

    public function test_a_manager_can_view_the_daily_summary(): void
    {
        $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertOk();
    }

    public function test_an_admin_can_view_the_daily_summary(): void
    {
        $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertOk();
    }

    public function test_a_pastor_title_carries_no_permission_to_view_the_summary(): void
    {
        $this->actingAs($this->pastorUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertForbidden();
    }

    public function test_completion_rate_reflects_tasks_due_today(): void
    {
        $asset = Asset::factory()->create();
        $routine = Routine::factory()->create(['asset_id' => $asset->id]);

        Task::factory()->create([
            'routine_id' => $routine->id,
            'due_at' => now(),
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        Task::factory()->create([
            'routine_id' => $routine->id,
            'due_at' => now()->addHours(2),
            'status' => Task::STATUS_PENDING,
        ]);
        // Not due today, should not affect the rate.
        Task::factory()->create([
            'routine_id' => $routine->id,
            'due_at' => now()->addDays(3),
            'status' => Task::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame(2, $data['tasks_due_today']);
        $this->assertSame(1, $data['tasks_completed_today']);
        $this->assertEquals(50.0, $data['completion_rate']);
    }

    public function test_completion_rate_is_zero_not_a_division_error_when_nothing_is_due_today(): void
    {
        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertOk();

        $this->assertSame(0, $response->json('data.tasks_due_today'));
        $this->assertEquals(0.0, $response->json('data.completion_rate'));
    }

    public function test_overdue_tasks_are_listed_with_asset_and_space_context(): void
    {
        $space = Space::factory()->create(['name' => 'Main Hall']);
        $asset = Asset::factory()->create(['space_id' => $space->id, 'name' => 'Generator']);
        $routine = Routine::factory()->create(['asset_id' => $asset->id]);

        $task = Task::factory()->create([
            'routine_id' => $routine->id,
            'due_at' => now()->subDays(2),
            'status' => Task::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame(1, $data['overdue_count']);
        $this->assertSame($task->id, $data['overdue_tasks'][0]['task_id']);
        $this->assertSame('Generator', $data['overdue_tasks'][0]['asset_name']);
        $this->assertSame('Main Hall', $data['overdue_tasks'][0]['space_name']);
        $this->assertSame(2, $data['overdue_tasks'][0]['days_overdue']);
    }

    public function test_a_completed_task_past_its_due_date_is_not_counted_overdue(): void
    {
        $asset = Asset::factory()->create();
        $routine = Routine::factory()->create(['asset_id' => $asset->id]);

        Task::factory()->create([
            'routine_id' => $routine->id,
            'due_at' => now()->subDays(2),
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertOk();

        $this->assertSame(0, $response->json('data.overdue_count'));
        $this->assertSame([], $response->json('data.asset_issues'));
    }

    public function test_asset_issues_group_overdue_tasks_by_asset(): void
    {
        $asset = Asset::factory()->create(['name' => 'Bus']);
        $routine = Routine::factory()->create(['asset_id' => $asset->id]);

        Task::factory()->count(3)->create([
            'routine_id' => $routine->id,
            'due_at' => now()->subDay(),
            'status' => Task::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertOk();

        $issues = $response->json('data.asset_issues');
        $this->assertCount(1, $issues);
        $this->assertSame('Bus', $issues[0]['asset_name']);
        $this->assertSame(3, $issues[0]['overdue_task_count']);
    }

    public function test_restricted_space_tasks_are_excluded_for_a_manager_without_a_grant(): void
    {
        $restrictedSpace = Space::factory()->create(['is_restricted' => true]);
        $restrictedAsset = Asset::factory()->create(['space_id' => $restrictedSpace->id]);
        $restrictedRoutine = Routine::factory()->create(['asset_id' => $restrictedAsset->id]);
        Task::factory()->create([
            'routine_id' => $restrictedRoutine->id,
            'due_at' => now()->subDay(),
            'status' => Task::STATUS_PENDING,
        ]);

        $openSpace = Space::factory()->create(['is_restricted' => false]);
        $openAsset = Asset::factory()->create(['space_id' => $openSpace->id]);
        $openRoutine = Routine::factory()->create(['asset_id' => $openAsset->id]);
        Task::factory()->create([
            'routine_id' => $openRoutine->id,
            'due_at' => now()->subDay(),
            'status' => Task::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->managerUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame(1, $data['overdue_count']);
        $this->assertSame(1, count($data['overdue_tasks']));
        $this->assertSame($openAsset->name, $data['overdue_tasks'][0]['asset_name']);
    }

    public function test_restricted_space_tasks_are_included_for_an_admin(): void
    {
        $restrictedSpace = Space::factory()->create(['is_restricted' => true]);
        $restrictedAsset = Asset::factory()->create(['space_id' => $restrictedSpace->id]);
        $restrictedRoutine = Routine::factory()->create(['asset_id' => $restrictedAsset->id]);
        Task::factory()->create([
            'routine_id' => $restrictedRoutine->id,
            'due_at' => now()->subDay(),
            'status' => Task::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/reports/daily-summary')
            ->assertOk();

        $this->assertSame(1, $response->json('data.overdue_count'));
    }
}
