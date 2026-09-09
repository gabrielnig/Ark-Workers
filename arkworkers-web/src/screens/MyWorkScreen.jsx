import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutationState } from '@tanstack/react-query';
import AppShell from '../components/AppShell.jsx';
import { useTasks } from '../hooks/useTasks.js';
import { useOnlineStatus } from '../hooks/useOnlineStatus.js';
import { queryClient } from '../queryClient.js';
import './MyWorkScreen.css';

const FILTERS = ['Today', 'Upcoming', 'Overdue', 'Completed'];

function isSameDay(a, b) {
  return a.toDateString() === b.toDateString();
}

function categorize(task, pendingSyncTaskIds) {
  if (pendingSyncTaskIds.has(task.id)) return 'pending-sync';
  if (task.status === 'completed') return 'completed';
  const due = new Date(task.due_at);
  if (due < new Date() && task.status !== 'completed') return 'overdue';
  return 'normal';
}

export default function MyWorkScreen() {
  const { data: tasks, isLoading } = useTasks();
  const isOnline = useOnlineStatus();
  const [activeFilter, setActiveFilter] = useState('Today');

  // Tasks with a paused (queued-while-offline) completion mutation,
  // this is the actual source of truth for "pending sync", not a
  // guess based on connectivity alone, a task can be queued and the
  // device can still be online for a moment before the queue drains.
  const pendingSyncTaskIds = new Set(
    useMutationState({
      filters: { mutationKey: ['completeTask'] },
      select: (mutation) => (mutation.state.isPaused ? mutation.state.variables : null),
    }).filter(Boolean)
  );

  const categorized = useMemo(() => {
    if (!tasks) return [];
    return tasks.map((task) => ({ task, category: categorize(task, pendingSyncTaskIds) }));
  }, [tasks, pendingSyncTaskIds]);

  const filtered = categorized.filter(({ task, category }) => {
    if (activeFilter === 'Completed') return category === 'completed';
    if (activeFilter === 'Overdue') return category === 'overdue';
    if (activeFilter === 'Today') {
      return category !== 'completed' && isSameDay(new Date(task.due_at), new Date());
    }
    // Upcoming: not completed, not overdue, not due today.
    return category === 'normal' && !isSameDay(new Date(task.due_at), new Date());
  });

  return (
    <AppShell>
      <div className="mywork-topbar">
        <div className="mywork-topbar-left">
          <img src="/images/logo-icon.png" alt="ArkWorkers" />
          <div>
            <p className="mywork-name">My Work</p>
            <p className="mywork-sub">{tasks?.length ?? 0} tasks</p>
          </div>
        </div>
      </div>

      <div className={`mywork-conn-status${isOnline ? '' : ' offline'}`}>
        <span className="mywork-conn-dot"></span>
        {isOnline ? 'Connected, all changes synced' : 'Offline, changes will sync when reconnected'}
      </div>

      <div className="mywork-filter-tabs">
        {FILTERS.map((filter) => (
          <span
            key={filter}
            className={activeFilter === filter ? 'mywork-filter-tab active' : 'mywork-filter-tab'}
            onClick={() => setActiveFilter(filter)}
          >
            {filter}
          </span>
        ))}
      </div>

      <div className="mywork-task-list">
        {isLoading && <p className="mywork-empty">Loading...</p>}
        {!isLoading && filtered.length === 0 && <p className="mywork-empty">Nothing here.</p>}

        {filtered.map(({ task, category }) => (
          <TaskCard key={task.id} task={task} category={category} />
        ))}
      </div>
    </AppShell>
  );
}

function TaskCard({ task, category }) {
  const isRestricted = task.routine?.asset?.space?.is_restricted;
  const requiresProof = task.routine?.requires_proof;

  return (
    <Link to={`/my-work/${task.id}`} className={`mywork-task-card ${category}`}>
      <div className="mywork-task-top">
        <div>
          <p className="mywork-task-name">{task.routine?.name}</p>
          <p className="mywork-task-meta">
            {task.routine?.asset?.name} &middot; {task.routine?.asset?.space?.name}
          </p>
        </div>
        <div className="mywork-task-badges">
          {isRestricted && <span className="mywork-badge restricted">Restricted</span>}
          {requiresProof && category !== 'completed' && (
            <span className="mywork-badge proof">Proof</span>
          )}
          {category === 'overdue' && <span className="mywork-badge overdue">Overdue</span>}
        </div>
      </div>

      <div className="mywork-task-bottom">
        {category === 'completed' && <span className="mywork-status-badge completed">Completed</span>}
        {category === 'pending-sync' && (
          <span className="mywork-status-badge pending-sync">
            <span className="mywork-dot-sync"></span>Pending sync
          </span>
        )}
        {category !== 'completed' && category !== 'pending-sync' && (
          <span className={category === 'overdue' ? 'mywork-due overdue' : 'mywork-due'}>
            Due {new Date(task.due_at).toLocaleString()}
          </span>
        )}
      </div>

      {category === 'pending-sync' && (
        <div className="mywork-sync-row">
          <span className="mywork-sync-note">Queued, will sync when back online.</span>
          <button
            className="mywork-force-sync-btn"
            onClick={(e) => {
              e.preventDefault();
              queryClient.resumePausedMutations();
            }}
          >
            Force sync
          </button>
        </div>
      )}
    </Link>
  );
}
