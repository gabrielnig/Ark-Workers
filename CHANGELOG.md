# ArkWorkers.app — Changelog

All notable changes to the project. Format loosely follows Keep a
Changelog (keepachangelog.com) — newest at top.

---

## [Unreleased]

### Offline-first sync foundation: conflict handling + queued mutations
- Fixed a real gap: task completion had no protection against the
  exact conflict SECURITY.md 6.4 requires handling, completing an
  already-completed task silently overwrote the original completion
  with no log. Added `completed_by` to tasks (a manager can complete
  someone else's assigned task, attribution needed tracking) and
  `task_completion_conflicts`, logging every discarded duplicate.
  `complete()` is now first-sync-wins: original never overwritten,
  second attempt gets 409, discard is logged, not dropped
- Built the frontend offline-queue mechanics: `useTasks`/
  `useCompleteTask`, mutation defaults registered via
  `setMutationDefaults` (required since a resumed mutation can't carry
  a closure across a reload), `networkMode: offlineFirst`, paused
  mutations now persisted to IndexedDB too (not just queries),
  `resumePausedMutations()` wired to replay the queue on reconnect
  through the real endpoint, so every replay re-validates auth/grants/
  conflict state fresh, nothing trusts the local queue as final
- Verified the real API contract over HTTP (task list shape, complete
  success and 409 shapes) and the actual React Query APIs used against
  the installed v5.102.8 type definitions, not memory
- Deliberately not wired into any screen yet (no worker task view
  mocked up or approved), and not verified in a real browser
  offline/reconnect scenario, no browser available here
- 105 tests passing, 208 assertions

### Real Login, Sign-up, and Admin requests screens built
- Replaced the placeholder App.jsx with real React screens matching
  the three approved mockups, wired to the real backend, single
  responsive component per screen (CSS breakpoints, not separate
  mobile/desktop builds)
- Added `GET /departments` (public, rate limited), the sign-up
  screen's checklist needs real data, not a hardcoded list
- Self-hosted Plus Jakarta Sans + Work Sans as woff2, resolving
  DESIGN-SYSTEM.md's open self-hosting decision
- Fixed a real 500: an unauthenticated request without an explicit
  `Accept: application/json` header crashed instead of returning a
  clean 401, this API has no login route to redirect a guest to.
  Caught by curling the exact request shape a plain `fetch()` sends,
  not by the test suite (`getJson()` always sets that header). Added
  a regression test using the raw `get()` helper
- Fixed a real frontend bug before it shipped: account-request
  submission would have 419'd for every real user, only login primed
  the CSRF cookie. Now primed once at app bootstrap
- Verified the full sign-up -> admin login -> list -> approve/reject
  flow end to end over real HTTP, not just a production build
  succeeding
- Deliberately did not build: the mockup's Forgot-password link (no
  backend), the Approved/Rejected-this-month stat cards (no backend
  data source), or the non-"Pending requests" sidebar nav items
  (those screens don't exist yet) — all left out rather than shipped
  as dead UI
- 102 tests passing, 197 assertions

### Phase 3 started, paused for an auth/org-structure rework, rework now complete
- Auth reworked to dual-mode: browser/PWA requests from the stateful
  frontend domain get a Sanctum session cookie (CSRF-protected), no
  bearer token in the response body; requests with no matching
  Origin/Referer (Capacitor Android, API clients) still get a token.
  Detection is automatic via Sanctum's own frontend-origin check
- Self-service registration removed entirely. Replaced with an
  admin-approval pipeline: `POST /api/account-requests` (public
  submit, rate limited), `GET/approve/reject` on account requests
  (Admin-only), single-use expiring invite links
  (`GET/POST /api/invites/{token}`, also rate limited) that let the
  worker set a password and activate
- Authorization model reworked: `departments` and `roles` are now
  separate admin-manageable master lists (was a single fixed `role`
  enum). A worker can belong to multiple departments with a different
  role in each. `hasManagementPermission()` replaces the old
  admin/pastor/facility_manager role-list checks everywhere (Space,
  Asset, Routine, Task policies, AssetTypeController).
  `bypassesSpaceRestrictions()` is Admin-only now, Pastor is a title
  with zero permission weight
- Seeded starting departments (8) and roles (3, two of which grant
  management permission) via `DepartmentRoleSeeder`, all admin-editable
  afterward
- Frontend scaffold started: Vite + React + React Router + React Query
  (IndexedDB-persisted) + Capacitor (core + Android platform added).
  Plain CSS, no real screens yet, `App.jsx` is a placeholder pending
  mockup-approved screens
- Typography changed to Plus Jakarta Sans + Work Sans (was Nunito),
  touch target minimum stays 44px, both per this session's design
  decisions, DESIGN-SYSTEM.md updated
- Three mockups approved as HTML files: login, sign-up (departments
  checklist, baptismal-name placeholder), admin pending-requests
  dashboard (structure adapted from a Stitch export, invented content
  stripped)
- Self-audit findings this batch: a dead CORS path, two CSRF tests
  that could never fail for the right reason (Laravel's test-mode
  auto-bypass), a silently-dropped `email_verified_at` field on
  account activation, and missing rate limiting on all three new
  public endpoints, all fixed, see LESSONS.md
- 99 tests passing, 190 assertions

### Phase 2 in progress: Space/Asset/AssetType/Task controllers, proof upload
- SpaceController, AssetController, AssetTypeController: full CRUD,
  every action policy-gated, restricted spaces/assets excluded from
  listings entirely, not just blocked in detail view
- Asset types are genuinely open-ended: a brand-new asset type (a pool,
  a fan, anything) can be created at any time with zero code changes,
  tested explicitly
- TaskController: list, detail, manual task assignment (ad-hoc, outside
  the routine schedule, the automatic scheduler is Phase 4), completion
- Non-resumable proof upload: `task_proofs` table added, files
  validated by actual content (not extension), stored on the private
  disk outside the web root
- Asset deletion is soft-delete, not a hard wipe: marks
  `decommissioned_at`, keeps all routine/task/proof history intact and
  queryable, a daily scheduled `model:prune` job permanently removes it
  30 days later. Deliberately NOT applied to Users, staff records stay
  permanent for full history regardless of active/inactive status
- Self-audit fixes: Space::destroy() returning a raw 500 on a
  foreign-key conflict instead of a clean 409
- 70 tests passing, 141 assertions, Pint clean

### Phase 2 closeout: logout endpoint, full documentation pass
- Added `POST /auth/logout` (token revocation), the one real gap left
  after Phase 2's endpoint list, login with no logout is a genuine
  pending item, not deferred scope
- Full documentation audit across both repos: API.md, RESEARCH.md,
  USER-GUIDE.md, DEV-SETUP.md, TESTING.md, HANDOVER.md updated to
  match what's actually built, no doc left saying "no code exists yet"
- Added `AI-CODE-FOOTPRINT.md` and `PROJECT-DOCUMENT-CHECKLIST.md` to
  the shared-protocols repo (this was requested earlier in the Phase 1
  session and had been missed until this closeout pass)
- 71 tests passing, 144 assertions, Pint clean

### Phase 1 follow-up: auth switched to email + password
- Replaced phone + PIN/OTP login (built and tested earlier this
  session) with email + password, plus an email-OTP verification step
  at signup. Reason: dropping SMS entirely for cost.
- Phone is now an optional profile field only, not used for auth
- Migration, User model, factory, AuthController, routes, and the full
  AuthTest suite reworked accordingly
- SECURITY.md §3.1 and ARCHITECTURE.md §3 updated to match
- 44 tests passing, 96 assertions, Pint clean

### Phase 1 (backend scaffold + auth) - COMPLETE
- Laravel 13 app scaffolded in `arkworkers-api/` (SQLite for local/test,
  PostgreSQL for staging/production per ARCHITECTURE.md §1)
- Migrations for the full core data model: users (phone/PIN, no
  email/password), spaces, space_access_grants, asset_types, assets,
  routines, tasks, vehicles, otp_codes, personal_access_tokens
- SpacePolicy, AssetPolicy, RoutinePolicy, TaskPolicy, all delegating
  through SpacePolicy for the space-restriction check per
  SECURITY.md §4.2, built and tested first per RESEARCH.md §7
- PIN and OTP login via Sanctum, rate limited at 5 attempts / 15
  minutes per SECURITY.md §3.1
- `docs/TDD-PROTOCOL.md` added: binding process document, tests
  written alongside implementation, full sandbox suite green before
  any push or deploy
- 44 tests passing, 102 assertions, Laravel Pint clean
- Self-audit pass after the suite was green found and fixed 3 issues,
  see LESSONS.md

### Documentation Phase (pre-code) - COMPLETE
- Added `PRD.md` — full requirements brainstorm
- Added `SECURITY.md` — security & NDPA compliance specification
  (later expanded with API/network hardening, incident response,
  field-level encryption, and 3-2-1 backup strategy)
- Added `DESIGN-SYSTEM.md` — visual identity and component standard
- Added logo assets (`docs/assets/logo/`) — final recolored mark, all
  variants (light/dark, monochrome, favicons)
- Added `ARCHITECTURE.md` — technical stack and data model (later
  updated with hybrid PM-trigger design and iOS/cross-platform notes)
- Added `RESEARCH.md` — industry research across CMMS, fleet
  management, church software, and mobile field-service apps
- Added `ai-context.md`, `LESSONS.md`, `HANDOVER.md`, `API.md`,
  `USER-GUIDE.md`, `DEPLOYMENT.md`, `DEV-SETUP.md`, `GLOSSARY.md`,
  this file
- Stack decided: Laravel + PostgreSQL backend, React PWA + Capacitor
  (Android now, iOS planned) frontend
- Backup strategy decided: 3-2-1 rule with a physical offsite copy

**No code written yet.** Phase 1 (backend scaffold + auth) begins next
session — see `ai-context.md` for full current state.
