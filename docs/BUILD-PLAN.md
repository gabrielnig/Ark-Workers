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
- [ ] **Finish the SMTP/mailer setup (Brevo), paused mid-way
      2026-09-15, must not still be unfinished at project conclusion.**
      Explicitly confirmed by Unique as a hard requirement before the
      project is considered done, not optional polish. Was mid-setup
      (domain authentication + SMTP key generated) when deploy
      permission issues took priority; picking this back up is what
      unblocks properly re-securing Phase 2's sign-up redesign below,
      which is itself an explicitly temporary stopgap that depends on
      this getting finished.
- [ ] **URGENT, reported 2026-09-16: session appears to not persist
      across a page reload, and Admin approve/reject was failing
      completely silently.** Two separate things:
      1. The silent-failure half is fixed, done 2026-09-16.
         `AdminRequestsScreen`'s approve/reject had no error handling
         at all, any failure (auth, network, validation) looked
         identical to nothing happening when clicked. Now surfaces a
         real error message, and a 401/419 specifically gets a
         "your session looks expired" message rather than a generic
         one, since that's almost certainly what a silent approve
         click actually was.
      2. **The actual session-persistence cause is still
         unconfirmed, needs a live check on the VPS, not fixable from
         a read of the repo alone.** Cookie config looked correct
         when last verified directly against production (`domain=
         .arkworkers.app; secure; httponly; samesite=lax` on both
         `arkworkers-session` and `XSRF-TOKEN`, `Max-Age=7200`).
         Strongest lead: this started being noticed right around the
         manual `.env` edits for Brevo SMTP, worth specifically
         checking `APP_KEY` is still intact and `SESSION_DOMAIN`
         wasn't accidentally touched, a changed `APP_KEY` alone would
         make every previously-issued session cookie unreadable and
         match this exact symptom.

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
  - [x] **Frontend done, 2026-09-15.** `AssetDetailScreen.jsx` at
        `/assets/:assetId`. An Admin/manager can now define a real
        recurring schedule through the app (calendar-interval or
        meter-based, with a proof-required flag) instead of only via
        the backend. **No automatic task generation exists yet**, a
        routine here is a saved schedule definition, not a
        self-driving one, there's no default-assignee concept
        anywhere in the schema, so auto-generating a task with no
        real answer for "assigned to whom" would mean inventing an
        assignment policy that was never decided. "+ Assign a task"
        is the manual path instead, reusing the `POST /tasks` endpoint
        that already existed for exactly this ("standalone assign-now
        path," per that controller's own docblock). A real decision
        on auto-assignment is still open, worth its own conversation.
  - [x] **UX polish pass, 2026-09-15.** `window.confirm()` replaced
        with a real `ConfirmDialog.jsx` for both decommission and
        routine deletion, no native browser popup anywhere on this
        screen. `AssigneePicker.jsx` rebuilt from a bare search box
        into a real click-to-open dropdown, name only (email dropped
        from display, still matched server-side), the default staff
        list (`UserController::index` with no search term) shows
        immediately on open rather than waiting for 2+ typed
        characters, typing narrows that same list live. The due-date
        field's blank-looking empty state (a native `<input
        type="date">` quirk, no visible affordance that it's a date
        picker until clicked) got a calendar icon + "Select due date"
        placeholder overlay, `pointer-events: none` so the native
        picker still opens normally underneath, not a rebuilt
        component.
- [x] **Spaces screen — done 2026-09-10.** `SpacesScreen.jsx` live at
      `/spaces`, wired to the real backend. Breadcrumb drill-down,
      restricted badge at every level, real (fixed, not random)
      photo thumbnails per Asset Type category. Enabled in `AppShell.jsx`
      nav alongside My Work; fixed a latent sidebar "active state" bug
      this surfaced along the way (see commit `a0f8d80`). Rename/edit
      (name + restricted flag) added later the same day, `update()`
      had existed on the backend with zero UI ever calling it.
  - [x] **No task-level drill-down anymore, 2026-09-15.** §5's full
        spec is Space → Asset → Asset's routines/tasks. Asset rows are
        now real links to `/assets/:assetId`, see the Routines entry
        above and the Asset Types entry below.
- [x] **Assets — done 2026-09-10, as a form inside Spaces, not a
      separate screen.** `Asset` always requires a real `space_id`
      (no "unassigned" bucket in the schema), so creation lives inside
      a space's detail view rather than a standalone Assets screen —
      same pattern as Space creation. Type is picked from a real
      `/api/asset-types` list, with an honest message (not a broken
      empty dropdown) if no asset types exist yet.
  - [x] **No longer blocked.** Asset Types admin screen + inline
        quick-create are done, see the item below.
  - [x] **Asset detail screen done, 2026-09-15.** Editing (rename,
        manager+) and decommissioning (Admin only, per `AssetPolicy`)
        both now have real UI, no longer dead actions. See the
        Routines frontend entry above for the drill-down itself.
- [x] **Asset Types admin screen — done 2026-09-10.** `AssetTypesScreen.jsx`
      at `/admin/asset-types` (list + create), plus inline quick-create
      right on the Add Asset form itself so an admin never has to leave
      the Spaces screen for the common case. Category is a curated
      dropdown matching `assetTypeImages.js`'s known categories, with
      an "Other" free-text fallback that still degrades safely to the
      default image. Gated by the new `RequireManager` component
      (mirrors `RequireAdmin` but checks `can_manage`, not `is_admin`,
      since managers can do this too per the policy).
- [x] **`UserController` search widened to manager-level, done
      2026-09-15.** Was admin-only, originally built only to power the
      restricted-space grant picker. Now also powers the new "assign a
      task" assignee search on the Asset Detail screen, a
      manager-level action per `TaskPolicy::create()`, so the gate
      needed to match, not stay narrower than the action it was
      blocking. `TaskController::index` also gained an optional
      `routine_id` filter (same pattern `RoutineController::index`
      already used for `asset_id`/`asset_type_id`), so the Routines
      section can show each routine's own tasks without fetching every
      task the user can see and filtering client-side.
- [x] **Restricted-space access grant admin UI, done 2026-09-10.**
      `SpaceAccessGrantController` (index/store/destroy), admin only,
      matching `User::bypassesSpaceRestrictions()`, not
      `hasManagementPermission()`. Originally paired with an
      admin-only `UserController` for the grant-search picker, that
      controller's gate was later widened to manager-level, see the
      entry above, `SpaceAccessGrantController` itself is unaffected
      and still Admin only. Frontend is `AccessGrantsPanel.jsx`,
      shown only when viewing a restricted space as an admin: search,
      grant, revoke. A real bug was caught and fixed before shipping:
      `grantedBy()` snake-cases to `granted_by`, identical to the FK
      column name, so naive serialization silently overwrote the
      integer id with the nested user object, fixed with an explicit
      response shape and a regression test. 14 new backend tests, full
      suite green (145 passed).

---

## Out-of-plan fixes and additions, 2026-09-16 (same session as above)

- [x] **Investigated a tester report of a missing sign-up password
      field.** Verified directly against the live production bundle
      (not just the repo) that the password/confirm-password fields
      were genuinely deployed and working, this was not a real bug.
      Most likely explanation: the tester loaded the page before that
      deploy went out, or hit a stale browser cache, `index.html` is
      served with no explicit `Cache-Control` header, only
      `Last-Modified`/`ETag`, which leaves the caching behavior up to
      each browser's own heuristics. Worth adding an explicit
      no-cache header on `index.html` at some point so this class of
      confusion stops recurring as more people test, not urgent today.
- [x] **Password show/hide toggle, done 2026-09-16.** New
      `PasswordField.jsx`, an eye icon + Show/Hide text button next to
      every password input. Used on both sign-up (password and
      confirm password) and sign-in.
- [x] **Toast confirmation on successful sign-up, done 2026-09-16.**
      New reusable `Toast.jsx` (auto-dismissing, top of screen, our
      own styling, same "never a native browser popup" reasoning as
      `ConfirmDialog.jsx`). Fires "Your request has been sent
      successfully" alongside the existing "Account created"
      confirmation screen, not instead of it.

---

## Phase 2 — Reporting

The data-viz palette (`DESIGN-SYSTEM.md` §2.6) was built specifically
for this phase and has nowhere to render yet.

- [x] **Daily Summary report backend, done 2026-09-10.**
      `DailySummaryService` + `ReportController::dailySummary`,
      `GET /api/reports/daily-summary`, manager/admin only (Pastor
      title carries no weight, matches locked decision). Computes
      completion rate for tasks due today, the overdue task list, and
      asset issues. "Asset issues" was undefined anywhere in the
      PRD/ARCHITECTURE beyond the phrase itself, so it was implemented
      as "assets currently carrying one or more overdue tasks",
      derived from existing Task/Routine/Asset data rather than
      inventing a new field. Restricted-space exclusion reuses
      `TaskPolicy::view()`, same rule as Spaces/Assets/Tasks, not a
      reimplementation. 11 new tests, full suite green (159 passed).
- [x] **Reports screen (frontend), done 2026-09-10.** `ReportsScreen.jsx`
      at `/reports`, gated by `RequireManager`. Completion-rate card
      (moss/clay bar, per §6.3, not a dense dashboard), overdue-tasks
      card (rust badge, asset/space/assignee/days-overdue rows), and
      an asset-issues card, the first real use of the data-viz palette
      (harbor/ochre/teal, §2.6) as a small horizontal bar chart ranking
      assets by overdue-task count. Added as a new "Reports" nav item
      in `AppShell.jsx` (sidebar + bottom nav), hidden entirely for a
      non-manager rather than shown as a dead 403 link, there was no
      pre-existing nav slot literally named Reports before this
      session.

---

## Out-of-plan fixes and additions, 2026-09-10 (same session)

- [x] **Admin nav bug, fixed.** `/admin/requests` (the pending
      sign-up requests screen) existed as a real route the whole time
      but had no link anywhere in the UI, an Admin had no way to
      reach it except typing the URL directly. Added a new "Admin"
      nav item in `AppShell.jsx`, gated on `is_admin` specifically
      (not the broader `can_manage`, matching `AccountRequestPolicy`),
      same pattern as the Reports item added earlier this session.
- [x] **Display name + ministry office, added to sign-up.** Two new
      optional fields on the sign-up form: a free-text display name,
      separate from the worker's full/baptismal name, and a curated
      ministry-office dropdown (Brother, Sister, Evangelist, Deacon,
      Deaconess, Pastor). Explicitly decided to carry zero permission
      weight, reuses the existing `users.title` column (already
      documented as "a plain label, never checked by any policy") for
      the office rather than adding a new one. New `display_name`
      column added to both `account_requests` and `users`. Both
      fields flow end to end: sign-up form → `AccountRequest` →
      invite activation → `User`, and both show on the Admin pending-
      requests table. Backend validation is soft (nullable string,
      no enforced enum), matching the existing `AssetType::category`
      pattern, not a hardcoded fixed list at the database layer. 5
      new tests, full suite green (163 passed, was 159).
- [x] **Phone made required at sign-up.** Was optional,
      `AccountRequestController::store` now requires it, same as
      name/email. Frontend label and `required` attribute updated to
      match. 1 new test, full suite green (164 passed).
- [x] **Mobile sign-up bug: ministry office field replaced with a
      custom dropdown, done 2026-09-15.** Reported unable to
      populate departments/ministry office on iPhone and some Samsung
      phones. Verified the API/CORS/data layer directly against the
      live site, all working correctly, not a backend or data issue.
      The ministry office field was a native `<select>` styled with a
      custom height/padding and no `-webkit-appearance` reset, a
      well-known cause of broken or unresponsive rendering in iOS
      Safari specifically. Replaced it with a fully custom
      `Dropdown.jsx` component (own trigger button, own option list,
      no native OS picker involved at all), which was also an
      explicit standing preference, not just a bugfix. Also bumped
      `.dept-check-row` to the standard 44px touch-target minimum,
      defensively, since the report bundled departments and office
      together. Could not reproduce a code-level department-checkbox
      bug through review or the live API check, if it recurs after
      this fix, worth asking whether it was opened through an in-app
      browser (WhatsApp, Instagram) rather than Safari/Chrome
      directly, a common source of this exact class of bug.
      `AssetTypesScreen`'s category field is still a native `<select>`,
      same treatment would apply there if wanted.
- [x] **Mobile bottom nav capped at 5 icons, done 2026-09-15.** An
      Admin had grown to 7 icons (Home, Spaces, My Work, Reports,
      Admin, Messages, Profile) once Reports and Admin were added as
      separate top-level items, reported as visibly too many on a real
      phone. Bottom nav is now fixed at exactly 5 regardless of role,
      Home, Spaces, My Work (center), Messages, and a "More" button
      that opens a bottom drawer holding Reports (manager/admin),
      Admin (admin only), and Profile. The desktop sidebar is
      unaffected, it lists everything flat since it isn't
      space-constrained the way a phone's bottom bar is.
- [x] **Remaining native `<select>` elements replaced app-wide, done
      2026-09-15.** Two more were found beyond the sign-up form's
      ministry office: `AssetTypesScreen`'s category picker, and
      `SpacesScreen`'s asset-type picker (including its inline
      "+ New asset type..." quick-create option) and its quick-create
      category picker. All three now use the same `Dropdown.jsx`
      component, no native OS picker anywhere in the app anymore.
- [x] **Sign-up redesigned: password set at sign-up, approval just
      lifts a login block, done 2026-09-15.** Explicit decision to
      stop depending on the not-yet-working mailer for the core
      approval loop. A worker now sets email + password (12+ chars,
      confirmed) directly on the sign-up form, `User` is created
      immediately with `email_verified_at` null.
      `AuthController::login` already blocked login while that column
      is null (previously the OTP-verification gate, that endpoint is
      unused dead code per `ai-context.md`), so approving is now
      nothing more than an Admin setting that one timestamp, no token,
      no link, no mailer dependency for the block itself. Rejecting
      deletes the `User` row outright (department memberships cascade
      with it), matching "rejected means no account exists," not a
      disabled one. `AccountRequestApproved` still sends an
      informational "you're approved" email via `SignUpApproved`, but
      that's a courtesy notice now, not load-bearing, login already
      works the moment Admin clicks approve regardless of whether that
      email ever arrives.

      The older `AccountRequest` + invite-token flow
      (`AccountRequestApproved`, `InviteController`, `invites/{token}`
      routes) was **not deleted**, it's simply no longer wired to the
      public sign-up form, all still functional and covered by its
      own passing tests, in case it's ever wanted again. Admin
      approve/reject now operate on `User` rows directly
      (`GET /api/account-requests` returns
      `User::whereNull('email_verified_at')`), same URL shapes as
      before so the frontend `AdminRequestsScreen` needed only a copy
      fix, not a rebuild. 3 old tests removed (they tested the deleted
      `AccountRequest`-creation behavior of `store()`), rewritten as
      17 new ones covering the full password/approval/rejection/login
      cycle. Full suite green (165 passed).

      **Explicitly temporary, not a final decision:** Unique confirmed
      2026-09-15 this whole password-at-signup/no-email-verification
      design is a deliberate stopgap for the build/testing period
      only, to let testers get in without the mailer being finished.
      It is not meant to still be the flow at launch. The sign-up
      email itself is never verified under this flow, previously the
      invite-link click proved the applicant controlled that inbox,
      that check is gone for now. Low risk today since Admin still
      reviews every request by name/phone/department before approving.

      **Before this project is considered done, this needs revisiting**
      once the Brevo SMTP setup (paused mid-way this session, see the
      chat history around 2026-09-15) is actually finished and
      confirmed delivering real email. At that point, come back to
      this decision and either restore real email verification onto
      this flow (e.g. re-enable the OTP step, or bring back an
      invite-link click as proof of inbox ownership) or make a
      deliberate informed call to keep it password-only, but a call
      made with a working mailer in hand, not by default because the
      mailer still isn't finished. Don't let this quietly become
      permanent.

---

## Phase 3 — Vehicle Fleet

- [x] **Backend done, 2026-09-16.** `VehicleController` (full CRUD),
      `VehicleLogController` (fuel/mileage/service), both with real
      policies: fleet management is manager+ (`VehiclePolicy`), but
      the assigned driver can additionally view their own vehicle and
      log against it themselves, without needing management
      permission, same reasoning as a worker completing their own
      Task. Added a `name` field to `vehicles` (was plate-number-only,
      nothing else in the app identifies things by code alone) and a
      `logged_by_user_id` on `vehicle_logs` (audit fact, not PRD-
      required but matches `tasks.completed_by`'s existing pattern).
      Document-expiry tracking is computed live on every response
      (`Vehicle::expiredDocuments()`/`expiringSoonDocuments()`,
      30-day warning window) rather than needing a job to run first.
      Alerting is a separate daily scheduled command,
      `vehicles:document-expiry-digest`, emails every Admin a digest,
      real infrastructure that works and is tested today, but actual
      delivery depends on the Brevo mailer being finished (Phase 0),
      the live-computed badges are the reliable path until then. 18
      new tests, full suite green (184 passed).
- [x] **Frontend done, 2026-09-16.** `VehiclesScreen.jsx` (list,
      `/vehicles`, viewable by anyone per `VehiclePolicy::viewAny`,
      "+ Add vehicle" manager+) and `VehicleDetailScreen.jsx`
      (`/vehicles/:vehicleId`), same list→detail pattern as
      Spaces→Asset. Not Admin-gated at the route level, matching
      `VehiclePolicy` already allowing the assigned driver in too,
      write actions inside the screen are individually gated
      (rename/delete manager+, log/report-a-problem driver-or-manager,
      resolution fields manager-only) rather than the whole screen.
      Documents section shows all four types with expired/soon/valid/
      not-set badges and a manager+ edit form. Logs section with
      type/value/date + delete. Repairs & problems section is the
      full incident UI: report → expand to see description, before/
      after photo grids (upload via a plain file input, matches the
      backend's plain synchronous upload, no chunking), and a
      manager-only "Update repair details" form for status/mechanic/
      parts/cost. Added to the More nav drawer.
- [x] **Vehicle repair/incident tracking, backend done, 2026-09-16.**
      Explicit request: beyond the simple fuel/mileage/service value
      log, a full "report a broken part → mechanic → parts → cost →
      before/after photos → resolved" trail. New
      `VehicleIncidentController` + `VehicleIncidentPhotoController`.
      Reporting a problem is driver-or-manager (same as logging fuel),
      resolving it (mechanic name, parts used, cost, status) is
      manager-only, that's real bookkeeping, not something a driver
      does. Marking a status "completed" stamps `resolved_at`
      automatically, never client-supplied. Photos are a plain
      synchronous upload, deliberately not the chunked/resumable
      session `ChunkedUploadController` uses for Task proofs, that
      machinery exists for large video proofs over a flaky field
      connection, a handful of repair photos don't need it. 11 new
      tests, full suite green (195 passed). Now wired into
      `VehicleDetailScreen.jsx` above.
- [x] **Dummy content seeder, done 2026-09-16.** New
      `database/seeders/DummyContentSeeder.php`, run manually via
      `php artisan db:seed --class=DummyContentSeeder`, not part of
      the default seeder chain. 8 Spaces (the real named areas of the
      building, per Unique directly, `Prophet's Office` and
      `Prophet's Quarters` seeded restricted), 20 AssetTypes covering
      a realistic venue inventory (HVAC, audio, lighting, furniture,
      windows/doors, flooring, generators, cameras, fire safety, and
      more), 48 Assets spread realistically across every Space, and
      the 7 real vehicles the Prophet has (also per Unique directly,
      labeled "Prophet's <name>"), with document-expiry dates
      deliberately spread across expired/expiring-soon/valid so the
      new screen has a real example of every badge state, not just
      the happy path. Sample fuel/mileage logs and two sample repair
      incidents included. Every insert uses `firstOrCreate`, safe to
      re-run, confirmed locally (48 assets stayed 48 on a second run).
      The asset-type list and specific assets under each space are
      Claude's own reasonable inventory of what a church/event venue
      has, not claimed to be accurate to the actual building, freely
      editable from the app afterward.

---

## Phase 4 — Staff & org-structure admin

- [x] **Staff directory screen, done 2026-09-16 (built quickly, urgent
      request).** `StaffScreen.jsx` at `/staff`, manager+, lists every
      approved worker (`email_verified_at` not null, someone still
      pending admin approval belongs on the Admin requests screen, not
      here) with their departments and per-department role, search by
      name/email/department. New `StaffController::index`, a real
      endpoint, not the capped-20 assignee/grant picker
      (`UserController`) repurposed, that one's docblock already said
      this was coming. Built directly from already-approved visual
      patterns (card rows, badges) rather than a fresh mockup round,
      given the explicit urgency. Editing added same day, see below.
- [x] **Editing a worker's departments/roles, or promoting/demoting
      Admin, from the Staff directory, done 2026-09-16.** Every write
      here (`StaffController::updateAdmin`/`joinDepartment`/
      `leaveDepartment`) is Admin-only, not the broader
      `hasManagementPermission()`, same reasoning as
      `AccountRequestController`, who has company-wide access isn't a
      department manager's call, even for their own department. An
      Admin cannot change their own Admin status (a deliberate
      lockout guard, not an oversight), a different Admin can. Inline
      "Edit" per row on `StaffScreen.jsx`, Admin toggle, remove-from-
      department with a `ConfirmDialog`, and an add-to-department flow
      that only offers departments the worker isn't already in, with
      roles scoped to whichever department is picked. 11 new tests,
      full suite green (206 passed).
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
