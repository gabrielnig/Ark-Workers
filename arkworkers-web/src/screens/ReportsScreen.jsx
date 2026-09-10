import AppShell from '../components/AppShell.jsx';
import { useDailySummary } from '../hooks/useDailySummary.js';
import './ReportsScreen.css';

const ISSUE_BAR_COLORS = [
  'var(--color-harbor-500)',
  'var(--color-ochre-500)',
  'var(--color-teal-600)',
  'var(--color-clay-500)',
  'var(--color-plum-500)',
];

function formatToday() {
  return new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * PRD.md §7 / BUILD-PLAN.md Phase 2. Deliberately a simple scannable
 * completion-rate bar per DESIGN-SYSTEM.md §6.3, not a dense BI
 * dashboard. Restricted-space exclusion happens entirely on the
 * backend (SECURITY.md §4.2), nothing here needs to re-filter
 * anything.
 */
export default function ReportsScreen() {
  const { data: summary, isLoading, isError } = useDailySummary();

  return (
    <AppShell>
      <div className="reports-screen">
        <h1 className="reports-title">Reports</h1>
        <div className="reports-date">Daily summary for {formatToday()}</div>

        {isLoading && <div className="empty-state">Loading today's summary...</div>}
        {isError && (
          <div className="empty-state">Couldn't load the report right now. Try again shortly.</div>
        )}

        {summary && (
          <>
            <CompletionRateCard summary={summary} />
            <OverdueTasksCard summary={summary} />
            <AssetIssuesCard summary={summary} />
          </>
        )}
      </div>
    </AppShell>
  );
}

function CompletionRateCard({ summary }) {
  const { completion_rate, tasks_due_today, tasks_completed_today } = summary;

  if (tasks_due_today === 0) {
    return (
      <div className="card">
        <div className="card-title">Completion rate today</div>
        <div className="empty-state">No tasks due today.</div>
      </div>
    );
  }

  return (
    <div className="card">
      <div className="card-title">Completion rate today</div>
      <div className="completion-rate-value">{completion_rate}%</div>
      <div className="completion-rate-caption">
        {tasks_completed_today} of {tasks_due_today} tasks due today are complete
      </div>
      <div className="completion-bar-track">
        <div className="completion-bar-fill" style={{ width: `${completion_rate}%` }} />
      </div>
      <div className="completion-bar-legend">
        <span>
          <span className="legend-dot" style={{ background: 'var(--color-moss-600)' }} />
          Completed
        </span>
        <span>
          <span className="legend-dot" style={{ background: 'var(--color-clay-500)' }} />
          Still due today
        </span>
      </div>
    </div>
  );
}

function OverdueTasksCard({ summary }) {
  const { overdue_count, overdue_tasks } = summary;

  return (
    <div className="card">
      <div className="card-title reports-card-title-row">
        <span>Overdue tasks</span>
        {overdue_count > 0 && <span className="overdue-count-badge">{overdue_count}</span>}
      </div>

      {overdue_count === 0 && <div className="empty-state">No overdue tasks. Nice work.</div>}

      {overdue_tasks.map((task) => (
        <div className="overdue-row" key={task.task_id}>
          <div className="overdue-row-main">
            <div className="overdue-asset-name">{task.asset_name ?? 'Unknown asset'}</div>
            <div className="overdue-meta">
              {task.space_name ?? 'Unknown space'}
              {task.assigned_user_name ? ` \u00b7 assigned to ${task.assigned_user_name}` : ''}
            </div>
          </div>
          <div className="overdue-days">
            {task.days_overdue} {task.days_overdue === 1 ? 'day' : 'days'}
          </div>
        </div>
      ))}

      <div className="restricted-note">
        <span className="restricted-dot" />
        Restricted spaces you don't have access to are left out of this report entirely.
      </div>
    </div>
  );
}

function AssetIssuesCard({ summary }) {
  const { asset_issues } = summary;

  if (asset_issues.length === 0) {
    return (
      <div className="card">
        <div className="card-title">Assets needing attention</div>
        <div className="empty-state">No assets need attention right now.</div>
      </div>
    );
  }

  const maxCount = Math.max(...asset_issues.map((issue) => issue.overdue_task_count));

  return (
    <div className="card">
      <div className="card-title">Assets needing attention</div>

      {asset_issues.map((issue, index) => (
        <div className="issue-row" key={issue.asset_id}>
          <div className="issue-row-label">
            <span>
              <span className="issue-row-name">{issue.asset_name}</span>
              {issue.space_name && <span className="issue-row-space">{issue.space_name}</span>}
            </span>
            <span className="issue-row-count">{issue.overdue_task_count}</span>
          </div>
          <div className="issue-bar-track">
            <div
              className="issue-bar-fill"
              style={{
                width: `${(issue.overdue_task_count / maxCount) * 100}%`,
                background: ISSUE_BAR_COLORS[index % ISSUE_BAR_COLORS.length],
              }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}
