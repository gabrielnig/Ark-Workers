import { Link, useLocation } from 'react-router-dom';
import './AppShell.css';

const NAV_ITEMS = [
  { key: 'home', label: 'Home', icon: '\u2302' },
  { key: 'spaces', label: 'Spaces', icon: '\uD83C\uDFE0', to: '/spaces' },
  { key: 'my-work', label: 'My Work', icon: '\u2713', to: '/my-work', primary: true },
  { key: 'messages', label: 'Messages', icon: '\u2709' },
  { key: 'profile', label: 'Profile', icon: '\u25CF' },
];

/**
 * "Spaces" and "My Work" are real routes. Home, Messages, and Profile
 * still render as inert, muted placeholders rather than dead links,
 * those screens do not exist yet.
 *
 * My Work keeps the raised center bubble in the bottom nav regardless
 * of current page — that's the single highest-frequency primary
 * action (DESIGN-SYSTEM.md §5), not a "you are here" indicator. The
 * sidebar's filled/current state IS a "you are here" indicator, based
 * on the actual route, so two real routes don't both show as
 * selected simultaneously.
 */
export default function AppShell({ children }) {
  const location = useLocation();

  return (
    <div className="app-shell">
      <aside className="app-sidebar">
        <div className="app-sidebar-brand">
          <img src="/images/logo-icon.png" alt="ArkWorkers" />
          <span>ArkWorkers</span>
        </div>
        {NAV_ITEMS.map((item) => {
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
        {NAV_ITEMS.map((item) => {
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
