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
  - [x] **Decided and fixed, 2026-09-10: Asset pruning no longer
        cascades through Routines to Tasks.** `routines.asset_id`
        changed from `cascadeOnDelete` to `nullOnDelete`. A pruned
        Asset's Routines now survive (asset_id set to null instead of
        the row being hard-deleted), so their Task/proof history
        outlives Asset pruning the same way it already outlives direct
        Routine deletion. Regression test confirms a pruned asset's
        routine and that routine's task both remain intact.
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
      this surfaced along the way (see commit `a0f8d80`). Rename/edit
      (name + restricted flag) added later the same day, `update()`
      had existed on the backend with zero UI ever calling it.
  - [ ] **No task-level drill-down yet.** §5's full spec is Space →
        Asset → Asset's routines/tasks. This screen stops at the Asset
        list — clicking into an asset does nothing yet, since there's
        no Asset detail screen (deliberately not linked to a dead
        route). That's the next item below.
- [x] **Assets — done 2026-09-10, as a form inside Spaces, not a
      separate screen.** `Asset` always requires a real `space_id`
      (no "unassigned" bucket in the schema), so creation lives inside
      a space's detail view rather than a standalone Assets screen —
      same pattern as Space creation. Type is picked from a real
      `/api/asset-types` list, with an honest message (not a broken
      empty dropdown) if no asset types exist yet.
  - [x] **No longer blocked.** Asset Types admin screen + inline
        quick-create are done, see the item below.
  - [ ] **Still no Asset detail screen.** Clicking an asset row still
        does nothing (deliberately, see the Spaces entry above). Asset
        editing/decommissioning and the Asset → Routine/Task
        drill-down both depend on this screen existing.
- [x] **Asset Types admin screen — done 2026-09-10.** `AssetTypesScreen.jsx`
      at `/admin/asset-types` (list + create), plus inline quick-create
      right on the Add Asset form itself so an admin never has to leave
      the Spaces screen for the common case. Category is a curated
      dropdown matching `assetTypeImages.js`'s known categories, with
      an "Other" free-text fallback that still degrades safely to the
      default image. Gated by the new `RequireManager` component
      (mirrors `RequireAdmin` but checks `can_manage`, not `is_admin`,
      since managers can do this too per the policy).
- [x] **Restricted-space access grant admin UI, done 2026-09-10.**
      `SpaceAccessGrantController` (index/store/destroy) plus a
      minimal `UserController` for the grant-search picker, both
      admin only, matching `User::bypassesSpaceRestrictions()`, not
      `hasManagementPermission()`. Frontend is `AccessGrantsPanel.jsx`,
      shown only when viewing a restricted space as an admin: search,
      grant, revoke. A real bug was caught and fixed before shipping:
      `grantedBy()` snake-cases to `granted_by`, identical to the FK
      column name, so naive serialization silently overwrote the
      integer id with the nested user object, fixed with an explicit
      response shape and a regression test. 14 new backend tests, full
      suite green (145 passed).

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
