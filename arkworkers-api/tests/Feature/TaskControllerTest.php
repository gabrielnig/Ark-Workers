<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Routine;
use App\Models\Space;
use App\Models\SpaceAccessGrant;
use App\Models\Task;
use App\Models\TaskCompletionConflict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private function routineIn(Space $space): Routine
    {
        $asset = Asset::factory()->create(['space_id' => $space->id]);

        return Routine::factory()->create(['asset_id' => $asset->id]);
    }

    public function test_index_excludes_tasks_in_restricted_spaces_without_a_grant(): void
    {
        $visibleSpace = Space::factory()->create(['is_restricted' => false]);
        $hiddenSpace = Space::factory()->create(['is_restricted' => true]);
        $visibleTask = Task::factory()->create(['routine_id' => $this->routineIn($visibleSpace)->id]);
        Task::factory()->create(['routine_id' => $this->routineIn($hiddenSpace)->id]);
        $user = $this->staffUser();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/tasks');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$visibleTask->id], $ids->all());
    }

    public function test_a_manager_can_manually_assign_a_task_in_an_unrestricted_space(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $manager = $this->managerUser();

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/tasks', [
            'routine_id' => $routine->id,
            'assigned_user_id' => $staff->id,
            'due_at' => now()->addDay()->toDateTimeString(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tasks', ['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);
    }

    public function test_cannot_manually_assign_a_task_into_a_restricted_space_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $manager = $this->managerUser();

        $this->actingAs($manager, 'sanctum')->postJson('/api/tasks', [
            'routine_id' => $routine->id,
            'assigned_user_id' => $staff->id,
            'due_at' => now()->addDay()->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_ordinary_staff_cannot_assign_tasks(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $cleaner = $this->staffUser();

        $this->actingAs($cleaner, 'sanctum')->postJson('/api/tasks', [
            'routine_id' => $routine->id,
            'assigned_user_id' => $cleaner->id,
            'due_at' => now()->addDay()->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_assigned_user_can_complete_their_own_task(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/tasks/{$task->id}/complete");

        $response->assertOk();
        $this->assertSame(Task::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_a_different_staff_member_cannot_complete_someone_elses_task(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $someoneElse = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $this->actingAs($someoneElse, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/complete")
            ->assertForbidden();
    }

    public function test_assigned_user_still_needs_a_grant_to_complete_a_task_in_a_restricted_space(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/complete")
            ->assertForbidden();

        $admin = $this->adminUser();
        SpaceAccessGrant::factory()->create([
            'user_id' => $staff->id,
            'space_id' => $space->id,
            'granted_by' => $admin->id,
        ]);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/complete")
            ->assertOk();
    }

    public function test_assigned_user_can_upload_a_valid_proof_file(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/proofs", ['file' => $file]);

        $response->assertCreated();
        $this->assertDatabaseHas('task_proofs', ['task_id' => $task->id]);
    }

    public function test_proof_upload_rejects_a_disallowed_file_type(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->create('malware.exe', 100);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/proofs", ['file' => $file])
            ->assertStatus(422);
    }

    public function test_someone_else_cannot_upload_a_proof_to_another_users_task(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $someoneElse = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($someoneElse, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/proofs", ['file' => $file])
            ->assertForbidden();
    }

    public function test_completing_an_already_completed_task_is_a_conflict_not_a_silent_overwrite(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $this->actingAs($staff, 'sanctum')->postJson("/api/tasks/{$task->id}/complete")->assertOk();
        $originalCompletedAt = $task->fresh()->completed_at;

        // A manager can also complete this task per TaskPolicy, this
        // simulates the real conflict scenario: the assigned staff
        // member and a manager both completed it while offline.
        $manager = $this->managerUser();

        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/tasks/{$task->id}/complete");

        $response->assertStatus(409);

        $task->refresh();
        $this->assertSame(Task::STATUS_COMPLETED, $task->status);
        $this->assertSame($staff->id, $task->completed_by);
        $this->assertEquals($originalCompletedAt, $task->completed_at, 'The original completion must never be overwritten.');
    }

    public function test_a_discarded_completion_conflict_is_logged_not_silently_dropped(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $this->actingAs($staff, 'sanctum')->postJson("/api/tasks/{$task->id}/complete")->assertOk();

        $manager = $this->managerUser();
        $this->actingAs($manager, 'sanctum')->postJson("/api/tasks/{$task->id}/complete");

        $conflict = TaskCompletionConflict::where('task_id', $task->id)->first();
        $this->assertNotNull($conflict);
        $this->assertSame($staff->id, $conflict->kept_user_id);
        $this->assertSame($manager->id, $conflict->discarded_user_id);
    }

    public function test_completing_a_task_records_who_actually_completed_it(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $manager = $this->managerUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        // A manager completing someone else's assigned task, not the
        // assignee, must be attributed correctly.
        $this->actingAs($manager, 'sanctum')->postJson("/api/tasks/{$task->id}/complete")->assertOk();

        $this->assertSame($manager->id, $task->fresh()->completed_by);
    }
}
