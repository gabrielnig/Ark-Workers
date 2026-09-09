# ArkWorkers.app — Build Plan

**Status:** v1, built 2026-09-09 from a full audit of what's actually in
the repo — migrations, API routes, and frontend screens — cross-checked
against `PRD.md`'s full requirements and `ARCHITECTURE.md`'s data model.
Not a wishlist, a gap analysis. Update this file's checkboxes as items
complete, don't create a parallel tracking doc.

**How to read this:** each phase lists items with their actual current
state (migration exists / controller exists / route exists / frontend
exists) so nothing gets re-built from scratch that's already half done,
and nothing gets assumed done that isn't. Phases are ordered by
dependency and by what's already live and real users can hit, not by
what's most interesting to build.

---

## Phase 0 — Operational gaps (do first, regardless of features)

These are live-site risks, not missing features. Nothing in Phase 1+
matters if this data is unrecoverable.

- [ ] **Backups.** `DEPLOYMENT.md` §2.5 — no automated backup exists at
      all. Real account-request data is already live. This is the
      single highest-risk open item in the whole project.
- [ ] **Rollback procedure.** `DEPLOYMENT.md` §4 — undecided. Depends on
      backups existing first.
- [ ] **Manual device verification.** `DEPLOYMENT.md` §6 — task list +
      photo upload never confirmed on a real phone, the actual point of
      the first deploy.
- [ ] **`npm audit` review.** 3 moderate-severity vulnerabilities flagged
      during the last two builds, never looked at.

---

## Phase 1 — Finish what's already half-built

Backend and frontend are out of sync in both directions here. Fix that
before starting anything new.

- [ ] **Spaces screen (frontend).** `SpaceController` is a full
      `apiResource` already (`GET/POST/PATCH/DELETE /spaces`) — the
      backend is done. Frontend has zero UI; "Spaces" is a disabled
      sidebar placeholder in `AppShell.jsx`. This blocks the entire
      Space → Asset → Task drill-down nav pattern locked in
      `DESIGN-SYSTEM.md` §5, which nothing currently uses.
- [ ] **Assets screen (frontend).** Same situation — `AssetController`
      is a full `apiResource`, no frontend at all.
- [ ] **Asset Types admin screen (frontend).** Backend has
      `GET/POST/PATCH /asset-types`, no UI. This is what lets Admins add
      new Asset Types (PRD's "fully modular, no code changes" requirement)
      — right now that requirement is unmet in practice even though the
      API supports it.
- [ ] **Routines — backend is incomplete, not just frontend.** The
      `routines` table migration exists
      (`2026_09_08_003740_create_routines_table.php`) but there is no
      `RoutineController` and no `/routines` route at all. Tasks
      currently get created directly (`POST /tasks`), bypassing the
      routine-schedule model entirely. This is a real backend gap, not
      a UI gap — routines are how recurring schedules and the
      calendar/meter hybrid-trigger logic (`ARCHITECTURE.md` §3) are
      supposed to work, and none of that exists yet.
- [ ] **Restricted-space access grant admin UI.** `space_access_grants`
      table exists, `SpacePolicy` checks it (`ARCHITECTURE.md` §4), but
      there's no screen for an Admin to actually grant/revoke access to
      a restricted Space. Right now a restricted Space, once created,
      has no visible way to authorize anyone to see it.

---

## Phase 2 — Reporting

The data-viz palette (`DESIGN-SYSTEM.md` §2.6) was built specifically
for this phase and has nowhere to render yet.

- [ ] **Daily Summary report backend.** PRD §7 requires completion
      rates, overdue tasks, and asset issues, reportable daily. No job,
      no endpoint exists for this at all — it's pure requirement right
      now, zero implementation.
- [ ] **Reports screen (frontend).** Nav placeholder only
      (`AppShell.jsx`). `DESIGN-SYSTEM.md` §6.3 already specifies this
      should read as a simple completion-rate bar, scannable in under a
      minute — not a dense BI dashboard. Depends on the backend item
      above.

---

## Phase 3 — Vehicle Fleet

PRD §5's full requirement (6+ vehicles, fuel/mileage/service logs,
document expiry tracking) — the `vehicles` table migration exists and
nothing else does.

- [ ] **`VehicleController` + routes.** Doesn't exist yet.
- [ ] **Vehicle logs backend** (fuel/mileage/service, per
      `ARCHITECTURE.md` §3's `vehicle_logs` table — check whether that
      migration exists yet or still needs writing).
- [ ] **Document expiry tracking + alerting** — insurance,
      roadworthiness, license, registration papers (PRD §5). This needs
      a scheduled job, same pattern as the eventual routine due-date
      checks.
- [ ] **Vehicles screen (frontend, admin-only)** — nav placeholder only.

---

## Phase 4 — Staff & org-structure admin

- [ ] **Staff directory screen.** "Staff" is an admin-only nav
      placeholder. Departments/roles are currently seed-data only
      (`DepartmentRoleSeeder`) with no admin UI to view or edit workers,
      their department memberships, or their per-department roles.
- [ ] **Department/Role management UI.** Both are already
      admin-manageable at the data layer (`department_role`,
      `department_user` pivot tables) — no UI exists to actually manage
      them without a database console.

---

## Phase 5 — Design decisions still open

Small in scope but blocking polish on screens already built.

- [ ] **Icon set decision** (`DESIGN-SYSTEM.md` §8 — Phosphor, Lucide,
      or similar, consistent with the warm/rounded direction). Task
      Detail and Admin screens currently use emoji/unicode glyphs as a
      stand-in.
- [ ] **Empty-state illustrations vs. text-only** — same open item,
      affects My Work's "nothing scheduled" states.

---

## Phase 6 — Platform completeness

- [ ] **Messages screen — needs a requirements conversation first, not
      a build.** It's a nav placeholder in `AppShell.jsx` but does not
      appear anywhere in `PRD.md` at all. Before this gets built, it
      needs actual scope defined (what kind of messaging, between whom,
      why) — building it straight from the nav placeholder would mean
      inventing requirements that were never decided.
- [ ] **Android real-device build test** via Capacitor — app has been
      built web-first this whole project, Capacitor Android platform
      was added early (per `ai-context.md`) but never verified as an
      installed APK on a real device.
- [ ] **iOS** — explicitly deferred per `ARCHITECTURE.md` §1 and §9,
      not urgent, revisit once Android is stable and a target date is
      worth setting.

---

## Explicitly not re-litigating

Locked decisions from prior sessions, listed here so future sessions
don't reopen them without a real reason:

- Data model (Spaces → Assets → Routines → Tasks) — `PRD.md` §2
- Authorization model v2 (department/role + space-access-grants) —
  reworked twice already, `ai-context.md` §2
- Account creation (admin-approval only, no self-service)
- Color/type/component design system — `DESIGN-SYSTEM.md`, including
  the 2026-09 full-skin decision (moss primary + Brevo interactive
  purple/gold layer)
- Stack (Laravel + PostgreSQL + React/Capacitor) — `ARCHITECTURE.md` §1
