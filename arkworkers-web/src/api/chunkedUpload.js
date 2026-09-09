import { apiFetch } from './client.js';

export async function startChunkSession(taskId, file, chunkSize) {
  const { data } = await apiFetch(`/api/tasks/${taskId}/proofs/chunked/start`, {
    method: 'POST',
    body: JSON.stringify({
      filename: file.name,
      declared_mime_type: file.type,
      total_size: file.size,
      chunk_size: chunkSize,
    }),
  });
  return data;
}

export async function getChunkStatus(taskId, sessionId) {
  const { data } = await apiFetch(`/api/tasks/${taskId}/proofs/chunked/${sessionId}/status`);
  return data;
}

export async function uploadChunk(taskId, sessionId, index, blob) {
  const formData = new FormData();
  formData.append('chunk', blob);

  return apiFetch(`/api/tasks/${taskId}/proofs/chunked/${sessionId}/chunks/${index}`, {
    method: 'POST',
    body: formData,
  });
}

export async function completeChunkSession(taskId, sessionId) {
  const { data } = await apiFetch(`/api/tasks/${taskId}/proofs/chunked/${sessionId}/complete`, {
    method: 'POST',
  });
  return data;
}
