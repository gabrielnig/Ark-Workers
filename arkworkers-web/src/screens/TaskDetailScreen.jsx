import { useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import AppShell from '../components/AppShell.jsx';
import { useTasks } from '../hooks/useTasks.js';
import { useCompleteTask } from '../hooks/useCompleteTask.js';
import { useResumableUpload } from '../hooks/useResumableUpload.js';
import './TaskDetailScreen.css';

export default function TaskDetailScreen() {
  const { taskId } = useParams();
  const navigate = useNavigate();
  const { data: tasks } = useTasks();
  const task = tasks?.find((t) => String(t.id) === taskId);
  const completeTask = useCompleteTask();
  const { files, addFile, retry, removeFile } = useResumableUpload(Number(taskId));
  const fileInputRef = useRef(null);

  if (!task) {
    return (
      <AppShell>
        <p style={{ padding: 18 }}>Loading...</p>
      </AppShell>
    );
  }

  const requiresProof = task.routine?.requires_proof;
  const hasUploadedProof = files.some((f) => f.status === 'done');
  const canComplete = !requiresProof || hasUploadedProof;

  function handleFilesSelected(event) {
    Array.from(event.target.files).forEach((file) => addFile(file));
    event.target.value = '';
  }

  function handleMarkComplete() {
    completeTask.mutate(task.id, {
      onSuccess: () => navigate('/my-work'),
    });
  }

  return (
    <AppShell>
      <div className="detail-header">
        <Link to="/my-work" className="detail-back-link">&larr; Back to My Work</Link>
        <p className="detail-breadcrumb">
          {task.routine?.asset?.space?.name}
          {task.routine?.asset?.name && <> &gt; <b>{task.routine.asset.name}</b></>}
        </p>
        <p className="detail-title">{task.routine?.name}</p>
        <p className="detail-meta">Due {new Date(task.due_at).toLocaleString()}</p>
      </div>

      <div className="detail-body">
        {requiresProof && (
          <>
            <div
              className="drop-zone"
              onClick={() => fileInputRef.current?.click()}
            >
              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,video/mp4,video/quicktime"
                multiple
                style={{ display: 'none' }}
                onChange={handleFilesSelected}
              />
              <div className="drop-icon">&#128247;</div>
              <p className="drop-text">Take a photo or upload proof</p>
              <p className="drop-sub">JPG, PNG, or MP4, up to 50MB</p>
            </div>

            {files.map((f) => (
              <UploadRow key={f.id} item={f} onRetry={() => retry(f.id)} onRemove={() => removeFile(f.id)} />
            ))}
          </>
        )}

        {completeTask.isError && (
          <p className="detail-error">
            {completeTask.error?.status === 409
              ? 'This task was already completed by someone else.'
              : 'Could not mark this task complete.'}
          </p>
        )}
      </div>

      <div className="complete-btn-row">
        <button
          className="complete-btn"
          onClick={handleMarkComplete}
          disabled={!canComplete || completeTask.isPending}
        >
          {completeTask.isPending ? 'Saving...' : 'Mark complete'}
        </button>
      </div>
    </AppShell>
  );
}

function UploadRow({ item, onRetry, onRemove }) {
  if (item.status === 'done') {
    return (
      <div className="uploaded-card">
        <div className="file-thumb">&#128444;</div>
        <div className="file-info">
          <p className="file-name">{item.file.name}</p>
          <p className="file-status"><span className="uploaded-badge">Uploaded just now</span></p>
        </div>
        <div className="file-actions">
          {Math.round(item.file.size / 1024)} KB
          <span onClick={onRemove}>Remove</span>
        </div>
      </div>
    );
  }

  return (
    <div className="upload-row">
      <div className="upload-row-top">
        <div className="file-thumb">&#128444;</div>
        <div className="file-info">
          <p className="file-name">{item.file.name}</p>
          <p className="file-status">
            {item.status === 'failed' ? 'Upload failed' : `${item.progress}%`}
          </p>
        </div>
      </div>
      <div className="progress-track">
        <div
          className={item.status === 'failed' ? 'progress-fill failed' : 'progress-fill'}
          style={{ width: `${item.progress}%` }}
        ></div>
      </div>
      {item.status === 'failed' && (
        <div className="retry-row">
          <span className="retry-text">{item.error}</span>
          <button className="retry-btn" onClick={onRetry}>Retry</button>
        </div>
      )}
    </div>
  );
}
