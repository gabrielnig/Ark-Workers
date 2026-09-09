import { useCallback, useState } from 'react';
import { startChunkSession, getChunkStatus, uploadChunk, completeChunkSession } from '../api/chunkedUpload.js';

const CHUNK_SIZE = 1024 * 1024; // 1MB, a mobile-friendly balance between
// HTTP overhead and how much re-uploads on retry after a real
// interrupted connection.

/**
 * Each attached file gets its own independent upload lane, per
 * UI-UX-STANDARD.md 5, one file failing or being retried never
 * touches the others' state.
 *
 * Progress is chunk-granular, not byte-granular. A true byte-level
 * progress bar mid-chunk would need XHR upload-progress events
 * instead of fetch(), which the rest of the API client is built on,
 * this is a deliberate, acknowledged tradeoff rather than a silent
 * gap: with a 1MB chunk size, progress still updates frequently for
 * anything but a very large video.
 */
export function useResumableUpload(taskId) {
  const [files, setFiles] = useState([]);

  const updateFile = useCallback((id, patch) => {
    setFiles((current) => current.map((f) => (f.id === id ? { ...f, ...patch } : f)));
  }, []);

  const runUpload = useCallback(async (id, file, sessionId, totalChunks, startFromIndex) => {
    try {
      for (let i = startFromIndex; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const blob = file.slice(start, start + CHUNK_SIZE);
        await uploadChunk(taskId, sessionId, i, blob);
        updateFile(id, { progress: Math.round(((i + 1) / totalChunks) * 100) });
      }

      const proof = await completeChunkSession(taskId, sessionId);
      updateFile(id, { status: 'done', progress: 100, proof });
    } catch (error) {
      updateFile(id, {
        status: 'failed',
        error: error.body?.message || 'Connection lost during upload.',
      });
    }
  }, [taskId, updateFile]);

  const addFile = useCallback(async (file) => {
    const id = `${file.name}-${Date.now()}`;
    setFiles((current) => [
      ...current,
      { id, file, progress: 0, status: 'uploading', error: null, sessionId: null },
    ]);

    try {
      const { session_id: sessionId, total_chunks: totalChunks } = await startChunkSession(
        taskId,
        file,
        CHUNK_SIZE
      );
      updateFile(id, { sessionId, totalChunks });
      await runUpload(id, file, sessionId, totalChunks, 0);
    } catch (error) {
      updateFile(id, {
        status: 'failed',
        error: error.body?.message || 'Could not start the upload.',
      });
    }
  }, [taskId, updateFile, runUpload]);

  /**
   * Resumes from the point of failure, not from 0%, per
   * UI-UX-STANDARD.md 3. Asks the server which chunks it already has
   * rather than trusting local state, since the failure could have
   * happened on the response of a chunk that actually made it.
   */
  const retry = useCallback(async (id) => {
    const item = files.find((f) => f.id === id);
    if (!item || !item.sessionId) return;

    updateFile(id, { status: 'uploading', error: null });

    try {
      const { received_chunk_indexes: received, total_chunks: totalChunks } = await getChunkStatus(
        taskId,
        item.sessionId
      );
      const nextIndex = received.length;
      updateFile(id, { progress: Math.round((nextIndex / totalChunks) * 100) });
      await runUpload(id, item.file, item.sessionId, totalChunks, nextIndex);
    } catch (error) {
      updateFile(id, {
        status: 'failed',
        error: error.body?.message || 'Still unable to reach the server.',
      });
    }
  }, [files, taskId, updateFile, runUpload]);

  const removeFile = useCallback((id) => {
    setFiles((current) => current.filter((f) => f.id !== id));
  }, []);

  return { files, addFile, retry, removeFile };
}
