<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChunkUploadSession;
use App\Models\Task;
use App\Models\TaskProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Resumable/chunked proof upload, replacing the non-resumable version
 * per ARCHITECTURE.md §6 and the shared UI-UX-STANDARD.md §4
 * ("never restart from 0% on failure"). A small file is simply a
 * 1-chunk upload from the client's perspective, one code path, not
 * two parallel upload mechanisms.
 *
 * Chunk presence is tracked by the filesystem itself (does the chunk
 * file exist at its expected path), not a separate per-chunk DB row
 * that could drift out of sync with what's actually on disk.
 */
class ChunkedUploadController extends Controller
{
    private const MAX_TOTAL_SIZE = 50 * 1024 * 1024;

    private const ALLOWED_REAL_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'video/mp4',
        'video/quicktime',
    ];

    /**
     * Begins a session. Resuming an interrupted upload calls this
     * again with the same file, then checks status() to see which
     * chunks are already on the server, per the "resume from point of
     * failure" requirement, this endpoint alone does not resume
     * anything, it just tells the client whether to start fresh or
     * where an existing session already stands (see the note in
     * status()).
     */
    public function start(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = Validator::make($request->all(), [
            'filename' => ['required', 'string'],
            'declared_mime_type' => ['required', 'string'],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_TOTAL_SIZE],
            'chunk_size' => ['required', 'integer', 'min:1'],
        ])->validate();

        $totalChunks = (int) ceil($data['total_size'] / $data['chunk_size']);

        $session = ChunkUploadSession::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'original_filename' => $data['filename'],
            'declared_mime_type' => $data['declared_mime_type'],
            'total_size' => $data['total_size'],
            'chunk_size' => $data['chunk_size'],
            'total_chunks' => $totalChunks,
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json(['data' => [
            'session_id' => $session->id,
            'total_chunks' => $totalChunks,
        ]], 201);
    }

    /**
     * What a resuming client asks before re-uploading anything, so it
     * only sends chunks the server does not already have, this is the
     * actual resume mechanism, not the start() call above.
     */
    public function status(Request $request, Task $task, ChunkUploadSession $session): JsonResponse
    {
        $this->authorize('update', $task);
        $this->authorizeSessionOwnership($request, $task, $session);

        return response()->json(['data' => [
            'received_chunk_indexes' => $session->receivedChunkIndexes(),
            'total_chunks' => $session->total_chunks,
        ]]);
    }

    public function uploadChunk(Request $request, Task $task, ChunkUploadSession $session, int $index): JsonResponse
    {
        $this->authorize('update', $task);
        $this->authorizeSessionOwnership($request, $task, $session);

        if ($index < 0 || $index >= $session->total_chunks) {
            abort(422, 'Invalid chunk index.');
        }

        Validator::make($request->all(), [
            'chunk' => ['required', 'file'],
        ])->validate();

        Storage::disk('local')->putFileAs(
            $session->chunkDirectory(),
            $request->file('chunk'),
            (string) $index
        );

        return response()->json(['data' => [
            'received_chunk_indexes' => $session->receivedChunkIndexes(),
        ]]);
    }

    /**
     * Assembles every chunk in order, validates the ASSEMBLED file's
     * real signature and size, never trusting individual chunk
     * headers or the client-declared MIME type, per SECURITY.md §5.1
     * and ARCHITECTURE.md §6.
     */
    public function complete(Request $request, Task $task, ChunkUploadSession $session): JsonResponse
    {
        $this->authorize('update', $task);
        $this->authorizeSessionOwnership($request, $task, $session);

        if (! $session->hasAllChunks()) {
            abort(422, 'Not all chunks have been received yet.');
        }

        $assembledPath = $this->assembleChunks($session);

        $absolutePath = Storage::disk('local')->path($assembledPath);
        $actualSize = filesize($absolutePath);
        $actualMimeType = mime_content_type($absolutePath);

        if ($actualSize !== $session->total_size || ! in_array($actualMimeType, self::ALLOWED_REAL_MIME_TYPES, true)) {
            Storage::disk('local')->delete($assembledPath);
            $session->deleteChunkFiles();
            $session->delete();

            abort(422, 'The assembled file failed validation.');
        }

        $proof = TaskProof::create([
            'task_id' => $task->id,
            'file_path' => $assembledPath,
            'file_type' => $actualMimeType,
            'chunk_upload_session_id' => (string) $session->id,
        ]);

        $session->deleteChunkFiles();
        $session->delete();

        return response()->json(['data' => $proof], 201);
    }

    private function assembleChunks(ChunkUploadSession $session): string
    {
        $disk = Storage::disk('local');
        $finalPath = 'task-proofs/'.uniqid('proof_', true);

        $disk->makeDirectory('task-proofs');
        $out = fopen($disk->path($finalPath), 'wb');

        for ($i = 0; $i < $session->total_chunks; $i++) {
            $chunkPath = $disk->path($session->chunkPath($i));
            $in = fopen($chunkPath, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);

        return $finalPath;
    }

    /**
     * A session belongs to exactly one task and the user who started
     * it, prevents one worker writing chunks into another's session
     * even if they both happen to have access to the same task. Also
     * rejects a session past its expiry even if the daily prune job
     * has not run yet, expires_at is an enforced boundary, not just a
     * marker for cleanup to eventually notice.
     */
    private function authorizeSessionOwnership(Request $request, Task $task, ChunkUploadSession $session): void
    {
        abort_unless(
            $session->task_id === $task->id
                && $session->user_id === $request->user()->id
                && $session->expires_at->isFuture(),
            404
        );
    }
}
