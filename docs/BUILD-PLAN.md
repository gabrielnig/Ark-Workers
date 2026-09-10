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

- [ ] **Backups — explicitly deferred (2026-09-09), not skipped.** `DEPLOYMENT.md`
      §2.5 — no automated backup exists yet. Deliberately pushed later
      rather than blocking on it now; still the single highest-risk open
      item once picked back up, since real account-request data is
      already live.
- [ ] **Rollback procedure.** `DEPLOYMENT.md` §4 — undecided. Depends on
      backups existing first.
- [ ] **Manual device verification.** `DEPLOYMENT.md` §6 — task list +
      photo upload never confirmed on a real phone, the actual point of
      the first deploy.
- [x] **`npm audit` review — reviewed 2026-09-09, accepted as-is.** All 3
      moderate findings trace to one chain: `uuid` → `xcode` →
      `@capacitor/cli`. `@capacitor/cli` is dev-only build tooling, never
      shipped in the production bundle, and `xcode` specifically is
      iOS project-generation tooling — unused, since iOS is deferred
      (Phase 6). The suggested `npm audit fix --force` would force a
      breaking `@capacitor/cli` bump, risking the working Android setup
      for a vulnerability in an inactive code path. Deferred to when
      iOS actually gets prioritized, not left as unreviewed noise.

---

## Phase 1 — Finish what's already half-built

Backend and frontend are out of sync in both directions here. Fix that
before starting anything new.

- [x] **Routines backend — done 2026-09-09/10.** `RoutineController`
      (full CRUD) + `/routines` routes, 130 tests passing total.
      Validates exactly one of asset_id/asset_type_id, at least one of
      calendar_interval_days/meter_threshold. `RoutinePolicy` and the
      model itself already existed from an earlier session, just had no
      controller wired up.
  - [x] **Deletion preserves history — decided and fixed 2026-09-10.**
        Deleting a routine is soft-delete only, deliberately never
        `Prunable` the way Asset is — a routine's task/proof history
        (who did the work, when, any photo proof) must stay permanently
        reachable, not just for a 30-day grace period. `Task::routine()`
        loads `withTrashed()` so history still shows the routine's name
        after it's deleted.
  - [ ] **Related, separate, not yet fixed: Asset pruning cascades through
        Routines to Tasks.** `routines.asset_id` still cascades on
        delete, and `Asset` IS `Prunable` (permanently removed 30 days
        after decommission). When a decommissioned Asset is actually
        pruned, its Routines get hard-deleted via cascade, which then
        hard-deletes their Tasks too via `routines.id`'s cascade onto
        `tasks.id` — silently reintroducing the exact history-loss
        problem just fixed above, just via a different path. Not fixed
        in this pass since it's a separate schema question (does
        Task/proof history need to outlive Asset pruning too? Almost
        certainly yes, for the same reason routines needed it) — flagged
        for a decision, not silently resolved by guessing.
  - [ ] **Frontend still not built.** Tasks are still created directly
        (`POST /tasks`), bypassing the routine-schedule model
        entirely — there's no UI yet for an Admin to actually define a
        recurring schedule through the app. This is still required
        before Routines is genuinely usable, the backend existing
        doesn't mean the PRD requirement is met yet.
- [x] **Spaces screen — done 2026-09-10.** `SpacesScreen.jsx` live at
      `/spaces`, wired to the real backend. Breadcrumb drill-down,
      restricted badge at every level, real (fixed, not random)
      photo thumbnails per Asset Type category. Enabled in `AppShell.jsx`
      nav alongside My Work; fixed a latent sidebar "active state" bug
      this surfaced along the way (see commit `a0f8d80`).
  - [ ] **No task-level drill-down yet.** §5's full spec is Space →
        Asset → Asset's routines/tasks. This screen stops at the Asset
        list — clicking into an asset does nothing yet, since there's
        no Asset detail screen (deliberately not linked to a dead
        route). That's the next item below.
- [ ] **Assets screen (frontend).** Same situation — `AssetController`
      is a full `apiResource`, no frontend at all.
- [ ] **Asset Types admin screen (frontend).** Backend has
      `GET/POST/PATCH /asset-types`, no UI. This is what lets Admins add
      new Asset Types (PRD's "fully modular, no code changes" requirement)
      — right now that requirement is unmet in practice even though the
      API supports it.
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
