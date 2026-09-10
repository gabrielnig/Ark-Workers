import { Link, useLocation } from 'react-router-dom';
import { useCurrentUser } from '../hooks/useCurrentUser.js';
import './AppShell.css';

const NAV_ITEMS = [
  { key: 'home', label: 'Home', icon: '\u2302' },
  { key: 'spaces', label: 'Spaces', icon: '\uD83C\uDFE0', to: '/spaces' },
  { key: 'my-work', label: 'My Work', icon: '\u2713', to: '/my-work', primary: true },
  { key: 'reports', label: 'Reports', icon: '\uD83D\uDCCA', to: '/reports', managerOnly: true },
  { key: 'admin', label: 'Admin', icon: '\u2699', to: '/admin/requests', adminOnly: true },
  { key: 'messages', label: 'Messages', icon: '\u2709' },
  { key: 'profile', label: 'Profile', icon: '\u25CF' },
];

/**
 * "Spaces" and "My Work" are real routes. Home, Messages, and Profile
 * still render as inert, muted placeholders rather than dead links,
 * those screens do not exist yet.
 *
 * My Work keeps the raised center bubble in the bottom nav regardless
 * of current page. That's the single highest-frequency primary
 * action (DESIGN-SYSTEM.md §5), not a "you are here" indicator. The
 * sidebar's filled/current state IS a "you are here" indicator, based
 * on the actual route, so two real routes don't both show as
 * selected simultaneously.
 */
export default function AppShell({ children }) {
  const location = useLocation();
  const { data: currentUser } = useCurrentUser();

  // Reports is manager/admin only (ReportController::dailySummary),
  // Admin is Admin-only (is_admin, not the broader can_manage), both
  // hidden here rather than shown as a dead 403/redirect link for a
  // user who can't reach them.
  const navItems = NAV_ITEMS.filter((item) => {
    if (item.managerOnly) return currentUser?.can_manage;
    if (item.adminOnly) return currentUser?.is_admin;
    return true;
  });

  return (
    <div className="app-shell">
      <aside className="app-sidebar">
        <div className="app-sidebar-brand">
          <img src="/images/logo-icon.png" alt="ArkWorkers" />
          <span>ArkWorkers</span>
        </div>
        {navItems.map((item) => {
          if (!item.to) {
            return (
              <div key={item.key} className="side-nav-item disabled">
                {item.label}
              </div>
            );
          }
          const isCurrent = location.pathname.startsWith(item.to);
          return (
            <Link
              key={item.key}
              to={item.to}
              className={`side-nav-item enabled${isCurrent ? ' current' : ''}`}
            >
              {item.label}
            </Link>
          );
        })}
      </aside>

      <div className="app-main">{children}</div>

      <nav className="bottom-nav">
        {navItems.map((item) => {
          if (!item.to) {
            return (
              <div key={item.key} className="nav-item">
                <span className="nav-icon">{item.icon}</span>
                {item.label}
              </div>
            );
          }
          if (item.primary) {
            return (
              <Link key={item.key} to={item.to} className="nav-item center">
                <div className="nav-icon-wrap">
                  <span>{item.icon}</span>
                </div>
                <span className="nav-label">{item.label}</span>
              </Link>
            );
          }
          const isCurrent = location.pathname.startsWith(item.to);
          return (
            <Link
              key={item.key}
              to={item.to}
              className={`nav-item enabled${isCurrent ? ' current' : ''}`}
            >
              <span className="nav-icon">{item.icon}</span>
              {item.label}
            </Link>
          );
        })}
      </nav>
    </div>
  );
}
