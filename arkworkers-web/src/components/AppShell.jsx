import { useEffect, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useCurrentUser } from '../hooks/useCurrentUser.js';
import './AppShell.css';

const NAV_ITEMS = [
  { key: 'home', label: 'Home', icon: '\u2302', bottom: 'primary' },
  { key: 'spaces', label: 'Spaces', icon: '\uD83C\uDFE0', to: '/spaces', bottom: 'primary' },
  { key: 'my-work', label: 'My Work', icon: '\u2713', to: '/my-work', primary: true, bottom: 'primary' },
  { key: 'messages', label: 'Messages', icon: '\u2709', bottom: 'primary' },
  { key: 'reports', label: 'Reports', icon: '\uD83D\uDCCA', to: '/reports', managerOnly: true, bottom: 'more' },
  { key: 'staff', label: 'Staff', icon: '\uD83D\uDC65', to: '/staff', managerOnly: true, bottom: 'more' },
  { key: 'admin', label: 'Admin', icon: '\u2699', to: '/admin/requests', adminOnly: true, bottom: 'more' },
  { key: 'profile', label: 'Profile', icon: '\u25CF', bottom: 'more' },
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
 *
 * The bottom nav is capped at 5 icons on purpose, Home, Spaces, My
 * Work, Messages, and a "More" drawer. Reports and Admin only exist
 * for a manager/Admin, so they live inside the More drawer instead of
 * as extra top-level icons, that's what kept pushing an Admin's bar
 * to 7 icons before. The sidebar has room to show everything flat,
 * so it isn't affected by this split.
 */
export default function AppShell({ children }) {
  const location = useLocation();
  const { data: currentUser } = useCurrentUser();
  const [moreOpen, setMoreOpen] = useState(false);
  const drawerRef = useRef(null);

  useEffect(() => {
    if (!moreOpen) return;

    function handleOutsideClick(event) {
      if (drawerRef.current && !drawerRef.current.contains(event.target)) {
        setMoreOpen(false);
      }
    }

    function handleEscape(event) {
      if (event.key === 'Escape') setMoreOpen(false);
    }

    document.addEventListener('pointerdown', handleOutsideClick);
    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('pointerdown', handleOutsideClick);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [moreOpen]);

  // Reports is manager/admin only (ReportController::dailySummary),
  // Admin is Admin-only (is_admin, not the broader can_manage), both
  // hidden here rather than shown as a dead 403/redirect link for a
  // user who can't reach them.
  const navItems = NAV_ITEMS.filter((item) => {
    if (item.managerOnly) return currentUser?.can_manage;
    if (item.adminOnly) return currentUser?.is_admin;
    return true;
  });

  const bottomPrimaryItems = navItems.filter((item) => item.bottom === 'primary');
  const bottomMoreItems = navItems.filter((item) => item.bottom === 'more');

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
        {bottomPrimaryItems.map((item) => {
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

        <button
          type="button"
          className={`nav-item enabled${moreOpen ? ' current' : ''}`}
          onClick={() => setMoreOpen(true)}
        >
          <span className="nav-icon">&#8942;</span>
          More
        </button>
      </nav>

      {moreOpen && (
        <div className="nav-drawer-backdrop">
          <div className="nav-drawer" ref={drawerRef}>
            <div className="nav-drawer-handle" />
            {bottomMoreItems.map((item) => {
              if (!item.to) {
                return (
                  <div key={item.key} className="nav-drawer-item disabled">
                    <span className="nav-icon">{item.icon}</span>
                    {item.label}
                  </div>
                );
              }
              const isCurrent = location.pathname.startsWith(item.to);
              return (
                <Link
                  key={item.key}
                  to={item.to}
                  className={`nav-drawer-item enabled${isCurrent ? ' current' : ''}`}
                  onClick={() => setMoreOpen(false)}
                >
                  <span className="nav-icon">{item.icon}</span>
                  {item.label}
                </Link>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
