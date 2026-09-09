import { Link } from 'react-router-dom';
import './AppShell.css';

const NAV_ITEMS = [
  { key: 'home', label: 'Home', icon: '\u2302' },
  { key: 'spaces', label: 'Spaces', icon: '\uD83C\uDFE0' },
  { key: 'my-work', label: 'My Work', icon: '\u2713', to: '/my-work' },
  { key: 'messages', label: 'Messages', icon: '\u2709' },
  { key: 'profile', label: 'Profile', icon: '\u25CF' },
];

/**
 * Only "My Work" is a real route. The other four render as inert,
 * muted placeholders rather than dead links, those screens do not
 * exist yet.
 */
export default function AppShell({ children }) {
  return (
    <div className="app-shell">
      <aside className="app-sidebar">
        <div className="app-sidebar-brand">
          <img src="/images/logo-icon.png" alt="ArkWorkers" />
          <span>ArkWorkers</span>
        </div>
        {NAV_ITEMS.map((item) =>
          item.to ? (
            <Link key={item.key} to={item.to} className="side-nav-item active">
              {item.label}
            </Link>
          ) : (
            <div key={item.key} className="side-nav-item disabled">
              {item.label}
            </div>
          )
        )}
      </aside>

      <div className="app-main">{children}</div>

      <nav className="bottom-nav">
        {NAV_ITEMS.map((item) =>
          item.to ? (
            <Link key={item.key} to={item.to} className="nav-item center">
              <div className="nav-icon-wrap">
                <span>{item.icon}</span>
              </div>
              <span className="nav-label">{item.label}</span>
            </Link>
          ) : (
            <div key={item.key} className="nav-item">
              <span className="nav-icon">{item.icon}</span>
              {item.label}
            </div>
          )
        )}
      </nav>
    </div>
  );
}
