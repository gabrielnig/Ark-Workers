# AI Context Matrix — ArkWorkers.app

**Last updated:** End of auth/org-structure rework session (Phase 3
started, then paused for a prerequisite rebuild)
**Update this file at the end of every significant work session**, this
is the first thing any future session (Claude or human) should read.

---

## 1. Project Overview & Current State

ArkWorkers is a mobile-first PWA + Android facility-management app for
King's Palace & Ark of Jesus Ministries International. Stack: Laravel +
PostgreSQL backend, React PWA wrapped by Capacitor for Android, self-hosted
on a private VPS with no third-party BaaS. Core model: modular
Spaces → Assets (of configurable Asset Types) → Routines → Tasks, with a
department/role-based + space-level-restricted authorization system (the
Prophet's Quarters is the concrete case driving space restriction design).

**Current state: Phase 1 and 2 complete, the auth/org-structure prerequisite
complete, the real Login/Sign-up/Admin-requests screens built and verified,
the offline-first sync foundation built, and resumable chunked upload now
replaces the old non-resumable proof endpoint.** Laravel 13 app lives in
`arkworkers-api/`, React/Vite/Capacitor frontend lives in `arkworkers-web/`.
112 tests passing, 229 assertions on the backend; no frontend test
framework decided or set up yet (flagged, not silently resolved, see §3).

**Auth model reworked this session, twice, both real architecture
changes, not additions:**
1. Dual-mode login: browser/PWA requests from the stateful frontend
   domain get a session cookie (Sanctum SPA auth, CSRF-protected), no
   token in the response body at all. Requests with no matching
   Origin/Referer (Capacitor Android, API clients) still get a bearer
   token. Detection is automatic via Sanctum's
   `EnsureFrontendRequestsAreStateful::fromFrontend()`, no manual
   "is this mobile" flag anywhere.
2. Self-service registration removed entirely. Replaced with an
   admin-approval pipeline: a worker submits a sign-up request (name,
   email, phone, department_ids, no password) via
   `POST /api/account-requests`, an Admin approves or rejects it, and
   approval emails a single-use expiring invite link
   (`GET/POST /api/invites/{token}`) that lets the worker set a
   password and activate the account. The invite link itself is the
   email-verification step now, OTP is unused for this flow (the
   `verifyEmailOtp` endpoint and `OtpCode` model still exist but have
   no caller producing valid codes anymore, flagged as a cleanup item
   below, not yet removed).

**Authorization model also fully reworked**, replacing the old single
`role` enum column entirely:
- `departments` and `roles` are now separate, both admin-manageable
  master lists (not fixed enums). A department has a toggleable subset
  of roles available to it (`department_role` pivot).
- A worker can belong to multiple departments, with a different role
  in each (`department_user` pivot, one row per department
  membership, enforced by a unique constraint).
- `User::hasManagementPermission()` is Admin OR any department
  membership whose role has `grants_management = true` — this flag is
  admin-toggled per role, policies never hardcode a role or department
  name.
- `User::bypassesSpaceRestrictions()` is Admin-only now.
- "Pastor" is a plain `title` string with zero permission weight, not
  a role or department.
- Seeded starting data (`DepartmentRoleSeeder`): 8 departments
  (Cleaning, Maintenance, Security, Driver, Facility Management,
  Choir, Sound, Ushering), 3 roles (Member — no management, Supervisor
  and Coordinator — both grant management), all three roles enabled on
  every seeded department. All admin-editable afterward.

**Frontend scaffold started, no real screens yet.** Vite + React +
React Router + React Query (IndexedDB-persisted via `idb-keyval`) +
Capacitor (core + Android platform added and syncing). Plain CSS
design tokens in `src/styles/tokens.css`, typography is now **Plus
Jakarta Sans (display/headlines) + Work Sans (body/labels)**, replacing
the earlier single-Nunito decision (see DESIGN-SYSTEM.md §3, old
decision struck through not deleted). `App.jsx` is a deliberate
placeholder, real screens need mockup approval first per standing
rule.

**Three mockups approved this session** (HTML files, not inline chat
visuals, per standing rule): login, sign-up (departments as a
multi-select checklist, baptismal-name placeholder, invite-link
explainer), and the admin pending-requests dashboard (sidebar, stat
cards, search/filter, bulk-select, structure adapted from a Stitch
export with all invented/fictional content stripped out). Backend for
all three flows is now built and tested to match.

**Data-loss policy, decided per entity (see LESSONS.md for the
reasoning):** Users are never deletable, restrictOnDelete blocks it
outright. Assets are soft-deleted with a 30-day grace period, then
permanently pruned via a daily scheduled job.

Standing process rules now in force for every future session (see
`docs/TDD-PROTOCOL.md` for the full version):
- Tests written alongside implementation, full suite must be green in
  the sandbox before anything is pushed or deployed.
- Plain CSS only on the frontend, never Tailwind, even though Google
  Stitch mockups are generated in Tailwind. Stitch is a reference
  point only. Every UI piece gets a mockup shown for approval before
  the real build, delivered as an actual HTML file the person opens
  and reviews, not an inline chat visualization.
- Mockups are mobile-first but built with tablet/desktop responsive
  views together in the same pass, not mobile-only then adapted later.
- Minimal/YAGNI code discipline: no unrequested abstractions, simplest
  solution that actually works, never at the expense of security,
  validation, or accessibility.
- No em dashes or en dashes anywhere in code, comments, commit
  messages, or UI copy. No comments that restate the obvious.

---

## 2. Known Knowns (Finalized Core)

- **Data model** — Spaces, Asset Types, Assets, Routines, Tasks structure
  is locked (`PRD.md` §2, `ARCHITECTURE.md` §3)
- **Authorization model, v2** — department/role-based (both admin-
  manageable, not fixed enums) + explicit space-access-grants for
  restricted zones, implemented via Laravel Policies plus
  `User::hasManagementPermission()`. Admin-only bypasses space
  restrictions; Pastor is a title with zero permission weight. This
  replaced the original single-role-enum model this session and must
  not be redesigned again without a real reason, it's now the second
  time this specific piece has been reworked
- **Account creation model** — admin-approval only, no self-service.
  Request → Admin approve/reject → single-use invite link → worker
  sets password → active. Locked this session, see §1 above for the
  concrete endpoints
- **Color/type/component design system** — finalized in
  `DESIGN-SYSTEM.md`. Typography updated this session to Plus Jakarta
  Sans + Work Sans (was Nunito), touch target minimum stays 44px
  (Stitch's export used 48px, deliberately not adopted, see
  DESIGN-SYSTEM.md §3 for the reasoning)
- **Logo** — finalized, full asset set in `docs/assets/logo/`
- **Legal compliance baseline** — NDPA 2023 obligations mapped in
  `SECURITY.md` §2
- **Industry research** — CMMS, fleet management, church-specific
  software, and mobile field-service apps researched and documented in
  `RESEARCH.md`. Validated the existing architecture rather than
  requiring major changes; one concrete improvement (hybrid PM
  triggers) folded into `ARCHITECTURE.md` §3
- **Backup strategy** — 3-2-1 rule (live + offsite cloud + physical
  offline copy), documented in `SECURITY.md` §11 and `DEPLOYMENT.md`
  §2.5
- **Stack** — Laravel/PostgreSQL backend, React/Capacitor frontend,
  decided and documented in `ARCHITECTURE.md` §1
- **iOS is a confirmed future target, not launch scope** — Capacitor was
  specifically chosen to make this a low-effort addition later rather
  than a rewrite. Practical implication for every frontend decision from
  here on: no Android-only native plugins or assumptions, build against
  Capacitor's cross-platform plugin API only (`ARCHITECTURE.md` §1)

## 3. Known Unknowns (Immediate Roadmap)

1. **Phase 1: complete.** Migrations, auth, SpacePolicy/AssetPolicy/
   RoutinePolicy/TaskPolicy.
2. **Phase 2: complete.** Full CRUD controllers, task assignment/
   completion, proof upload, Asset soft-delete + pruning.
3. **Auth + org-structure rework: complete, this session.** Dual-mode
   cookie/token login, admin-approval account pipeline, department/
   role authorization model. This was a prerequisite that emerged
   while starting Phase 3 (the sign-up/login UI needed a real backend
   to build against), not originally planned as its own phase, see
   LESSONS.md for how it grew.
4. **Phase 3 (next): offline reliability layer.** Real Login, Sign-up,
   and Admin-requests screens are built and verified against the real
   backend, matching the three approved mockups. Offline-first sync
   foundation built: task completion conflict handling and the
   frontend offline-queue mechanics. Resumable chunked upload now
   replaces the old non-resumable proof endpoint entirely (50MB cap,
   filesystem-tracked chunk presence, real signature validation on
   the assembled file, daily pruning of abandoned sessions). What's
   left: a worker "My Work" task screen to actually exercise the
   sync engine and the new chunked-upload endpoints (none built or
   mocked up yet, only Login/Sign-up/Admin exist), a frontend
   chunked-upload client (the backend exists and is tested, nothing
   on the frontend calls it yet), and real browser-based verification
   of both the offline/reconnect behavior and the chunked upload flow
   (only verified at the API-contract and backend-test level so far,
   no browser available in this environment).
5. Phase 4: scheduling (hybrid PM triggers) and reporting
6. Phase 5: polish (task messaging, asset history, PM compliance KPI,
   bottom mobile nav's raised "My Work" center button opening a
   worker's private task view, per standing note)
7. Phase 6: Android + iOS packaging via Capacitor (Android platform
   already added and syncing, iOS not started)
8. **VPS + SSL: deliberately still not done.** Decision this session:
   wait until Phase 3 is fully built and tested, since that's the
   first phase where the offline/network layer actually needs real
   TLS behavior to test against, deploying earlier just means
   maintaining a live target nothing is exercising yet.
9. Resolve remaining open decisions in `SECURITY.md`, `ARCHITECTURE.md`,
   `DEPLOYMENT.md` before they block a specific build step
10. Assign a real person to the monthly physical-backup responsibility
    (`SECURITY.md` §11) — not yet assigned to anyone
11. **Cleanup flagged, not yet done:** `verifyEmailOtp` endpoint and
    `OtpCode` model are now orphaned (nothing produces a valid code
    since self-service registration was removed). Decide whether to
    repurpose OTP for something else (password reset?) or remove it
    outright, don't leave it as silent dead functionality
12. **Content decision needed:** the account-request/admin-approval
    mockup showed no rejection-reason field, and no search/filter is
    built server-side yet (the seeded 8 departments are small enough
    it doesn't matter yet, will matter once departments grow)
13. **No frontend test framework decided or set up.**
    `docs/TDD-PROTOCOL.md` is explicitly PHPUnit-scoped ("This
    resolves the open decision flagged in TESTING.md 4"), nothing
    equivalent exists for `arkworkers-web/`. The real screens built
    this session were verified by curling the actual backend with the
    exact request shapes the compiled frontend code sends, real but
    manual, not an automated frontend test suite. Decide Vitest vs
    something else before the frontend grows much further, don't let
    this stay silently unresolved

## 4. Unknown Knowns (Implicit Design Patterns)

- Unique works in **checkpointed batches** — prefers completing and
  verifying one deliverable fully before moving to the next, rather than
  parallel half-finished work
- Strong preference for **catching my own mistakes before presenting
  work** — a deliberate audit pass at the end of every major batch has
  caught real issues every single time it's been done, never a wasted
  step
- Prefers **concrete numbers/values over vague guidance** — e.g. asked
  for exact rate-limit numbers rather than "implement rate limiting,"
  exact hex codes rather than "use warm colors"
- Building a **reusable protocol library** (`shared-protocols` repo)
  alongside project-specific work — general patterns should be
  extracted there, not just solved once for ArkWorkers
- **Product decisions arrive mid-build, via dictation, often stated
  loosely at first** ("departments and roles are separate," described
  over several messages before the full shape was clear) — worth
  reflecting the concrete proposed schema/design back before writing
  code, rather than building on the first pass of a verbal description
- **Real scope can hide inside what looks like a small UI request** —
  "admin-approve sign-ups instead of self-service" turned into a full
  authorization-model rework (departments, roles, management
  permissions) because the UI decision and the existing single-role
  column were incompatible. Worth surfacing that kind of hidden
  coupling explicitly rather than silently absorbing it

## 5. Unknown Unknowns (The Blindspot Log)

**Phase 1 self-audit findings:** an unused parameter left over from an
earlier policy draft, a cascade delete that would have silently
destroyed task audit history, a missing rate limit on the OTP request
endpoint.

**This session's audit findings, all caught before or shortly after
shipping, none by the test suite alone:**
- A duplicate `sessions` table migration nearly got added, Phase 1 had
  already scaffolded one, unused until this session's cookie-auth work
  needed it. Caught by checking the actual migration files before
  writing a new one, not by assuming.
- The CSRF-rejection tests for the new cookie-auth flow initially
  asserted `419` but could never actually fail for the right reason,
  Laravel auto-bypasses CSRF whenever `app.env === 'testing'`, which is
  always true under PHPUnit. Fixed by forcing `app.env` to
  `'production'` for just those two assertions. A green test that
  cannot fail is worse than no test, worth specifically checking for
  this pattern (framework auto-bypass in test mode) on any future
  security-relevant test.
- `config/cors.php` shipped with a dead `'auth/*'` path that matched
  nothing (the real routes are `api/auth/*`), caught by checking the
  actual route list, not by trusting the config as written.
- `User::create()` silently dropped `email_verified_at` during invite
  activation because it isn't mass-fillable, caught by a real test
  asserting the field was actually set, not just that the response was
  200.
- All three new public account-request/invite endpoints shipped with
  zero rate limiting, directly against SECURITY.md's explicit "rate
  limiting on all public endpoints" requirement. Caught by rereading
  the doc against the new code, not by the doc being consulted
  proactively while writing it the first time. **Pattern worth
  repeating: after adding any new public route, explicitly check it
  against SECURITY.md's public-endpoint requirements before calling
  the batch done, don't rely on remembering to do it.**

**Pre-emptive risk flagged from documentation review, not yet
code-verified:** the offline-sync re-validation logic
(`ARCHITECTURE.md` §5, `SECURITY.md` §6.4) is conceptually specified but
is exactly the kind of feature that's easy to get subtly wrong in
implementation (race conditions between reconnect-sync and a
simultaneous access-grant revocation). Now more concretely relevant
since Sanctum tokens have no expiration set
(`config/sanctum.php` → `'expiration' => null`), meaning "session
still authorized" currently means "token not revoked," not "token not
stale" — worth an explicit decision when Phase 3's reconnect logic is
actually built, not an assumption.
