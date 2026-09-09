<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ChunkUploadSession;
use App\Models\Routine;
use App\Models\Space;
use App\Models\SpaceAccessGrant;
use App\Models\Task;
use App\Models\TaskCompletionConflict;
use App\Models\TaskProof;
use App\Models\User;
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

    private function startChunkSession(Task $task, User $user, UploadedFile $file, int $chunkSize = null): int
    {
        $chunkSize = $chunkSize ?? $file->getSize();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/tasks/{$task->id}/proofs/chunked/start", [
            'filename' => $file->getClientOriginalName(),
            'declared_mime_type' => $file->getMimeType(),
            'total_size' => $file->getSize(),
            'chunk_size' => $chunkSize,
        ]);

        return $response->json('data.session_id');
    }

    private function uploadAllChunks(Task $task, User $user, int $sessionId, UploadedFile $file, int $chunkSize): void
    {
        $contents = file_get_contents($file->getPathname());
        $chunks = str_split($contents, $chunkSize);

        foreach ($chunks as $index => $chunkContents) {
            $chunkFile = UploadedFile::fake()->createWithContent("chunk-{$index}", $chunkContents);

            $this->actingAs($user, 'sanctum')->postJson(
                "/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/chunks/{$index}",
                ['chunk' => $chunkFile]
            )->assertOk();
        }
    }

    public function test_assigned_user_can_upload_a_valid_proof_file_in_a_single_chunk(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->image('proof.jpg');
        $sessionId = $this->startChunkSession($task, $staff, $file);
        $this->uploadAllChunks($task, $staff, $sessionId, $file, $file->getSize());

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/complete");

        $response->assertCreated();
        $this->assertDatabaseHas('task_proofs', ['task_id' => $task->id]);
        $this->assertDatabaseMissing('chunk_upload_sessions', ['id' => $sessionId]);
    }

    public function test_a_file_uploaded_across_several_chunks_assembles_correctly(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        // A real, reasonably sized fake image split into several
        // small chunks, the actual point of this feature.
        $file = UploadedFile::fake()->image('proof.jpg', 200, 200);
        $chunkSize = (int) ceil($file->getSize() / 4);
        $sessionId = $this->startChunkSession($task, $staff, $file, $chunkSize);
        $this->uploadAllChunks($task, $staff, $sessionId, $file, $chunkSize);

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/complete");

        $response->assertCreated();
        $proof = TaskProof::where('task_id', $task->id)->first();
        $assembledPath = Storage::disk('local')->path($proof->file_path);
        $this->assertSame($file->getSize(), filesize($assembledPath));
        $this->assertSame('image/jpeg', $proof->file_type);
    }

    public function test_status_reports_which_chunks_are_already_received_for_resuming(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->image('proof.jpg', 200, 200);
        $chunkSize = (int) ceil($file->getSize() / 4);
        $sessionId = $this->startChunkSession($task, $staff, $file, $chunkSize);

        // Simulate a connection drop after only the first chunk made it.
        $contents = file_get_contents($file->getPathname());
        $firstChunk = UploadedFile::fake()->createWithContent('chunk-0', substr($contents, 0, $chunkSize));
        $this->actingAs($staff, 'sanctum')->postJson(
            "/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/chunks/0",
            ['chunk' => $firstChunk]
        )->assertOk();

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/status");

        $response->assertOk();
        $this->assertSame([0], $response->json('data.received_chunk_indexes'));
    }

    public function test_completing_before_all_chunks_arrive_is_rejected(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->image('proof.jpg', 200, 200);
        $chunkSize = (int) ceil($file->getSize() / 4);
        $sessionId = $this->startChunkSession($task, $staff, $file, $chunkSize);

        // Only the first of several expected chunks arrives.
        $contents = file_get_contents($file->getPathname());
        $firstChunk = UploadedFile::fake()->createWithContent('chunk-0', substr($contents, 0, $chunkSize));
        $this->actingAs($staff, 'sanctum')->postJson(
            "/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/chunks/0",
            ['chunk' => $firstChunk]
        )->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/complete")
            ->assertStatus(422);
    }

    public function test_proof_upload_rejects_a_disallowed_file_type(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->create('malware.exe', 100);
        $sessionId = $this->startChunkSession($task, $staff, $file);
        $this->uploadAllChunks($task, $staff, $sessionId, $file, $file->getSize());

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/complete")
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
            ->postJson("/api/tasks/{$task->id}/proofs/chunked/start", [
                'filename' => $file->getClientOriginalName(),
                'declared_mime_type' => $file->getMimeType(),
                'total_size' => $file->getSize(),
                'chunk_size' => $file->getSize(),
            ])
            ->assertForbidden();
    }

    public function test_someone_else_cannot_write_chunks_into_another_users_session(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $manager = $this->managerUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->image('proof.jpg');
        $sessionId = $this->startChunkSession($task, $staff, $file);

        // The manager has update permission on this task too, but did
        // not start this specific session, so it must not be theirs
        // to write into.
        $this->actingAs($manager, 'sanctum')->postJson(
            "/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/chunks/0",
            ['chunk' => $file]
        )->assertStatus(404);
    }

    public function test_a_total_size_over_the_cap_is_rejected_at_start(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $this->actingAs($staff, 'sanctum')->postJson("/api/tasks/{$task->id}/proofs/chunked/start", [
            'filename' => 'huge.mp4',
            'declared_mime_type' => 'video/mp4',
            'total_size' => 51 * 1024 * 1024,
            'chunk_size' => 1024 * 1024,
        ])->assertStatus(422);
    }

    public function test_an_expired_session_cannot_be_used_even_before_pruning_runs(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->image('proof.jpg');
        $sessionId = $this->startChunkSession($task, $staff, $file);

        ChunkUploadSession::where('id', $sessionId)->update(['expires_at' => now()->subHour()]);

        $this->actingAs($staff, 'sanctum')->postJson(
            "/api/tasks/{$task->id}/proofs/chunked/{$sessionId}/chunks/0",
            ['chunk' => $file]
        )->assertStatus(404);
    }

    public function test_pruning_removes_expired_sessions_and_their_orphaned_chunk_files(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create(['is_restricted' => false]);
        $routine = $this->routineIn($space);
        $staff = $this->staffUser();
        $task = Task::factory()->create(['routine_id' => $routine->id, 'assigned_user_id' => $staff->id]);

        $file = UploadedFile::fake()->image('proof.jpg');
        $sessionId = $this->startChunkSession($task, $staff, $file);
        $this->uploadAllChunks($task, $staff, $sessionId, $file, $file->getSize());

        $session = ChunkUploadSession::find($sessionId);
        $this->assertTrue(Storage::disk('local')->exists($session->chunkPath(0)));

        $session->update(['expires_at' => now()->subHour()]);

        $this->artisan('model:prune', ['--model' => ChunkUploadSession::class]);

        $this->assertDatabaseMissing('chunk_upload_sessions', ['id' => $sessionId]);
        $this->assertFalse(Storage::disk('local')->exists($session->chunkPath(0)));
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
