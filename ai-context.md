# AI Context Matrix — ArkWorkers.app

**Last updated:** End of Phase 1 build session (backend scaffold complete)
**Update this file at the end of every significant work session**, this
is the first thing any future session (Claude or human) should read.

---

## 1. Project Overview & Current State

ArkWorkers is a mobile-first PWA + Android facility-management app for
King's Palace & Ark of Jesus Ministries International. Stack: Laravel +
PostgreSQL backend, React PWA wrapped by Capacitor for Android, self-hosted
on a private VPS with no third-party BaaS. Core model: modular
Spaces → Assets (of configurable Asset Types) → Routines → Tasks, with a
role-based + space-level-restricted authorization system (the Prophet's
Quarters is the concrete case driving this design).

**Current state: Phase 1 complete, Phase 2 in progress.** Laravel 13
app lives in `arkworkers-api/`. Phase 1: migrations for the full core
data model, email + password auth (with email-OTP signup
verification), space-restriction Policy layer. Phase 2 so far: full
CRUD controllers for Spaces, Assets, Asset Types (genuinely open-ended,
new asset types need zero code changes), Task list/detail/manual-
assignment/completion, non-resumable proof upload, Asset soft-delete
with a 30-day grace period before permanent pruning. 70 tests passing,
141 assertions. See `arkworkers-api/` directly for the code, this file
stays high-level.

**Data-loss policy, decided per entity (see LESSONS.md for the
reasoning):** Users are never deletable, restrictOnDelete blocks it
outright, since the whole point is a permanent record of what someone
did regardless of active status. Assets are soft-deleted with a 30-day
grace period, then permanently pruned via a daily scheduled job,
deletion is a normal frequent action (decommissioning), so history is
preserved without blocking the action itself.

Standing process rules now in force for every future session (see
`docs/TDD-PROTOCOL.md` for the full version):
- Tests written alongside implementation, full suite must be green in
  the sandbox before anything is pushed or deployed.
- Plain CSS only on the frontend, never Tailwind, even though Google
  Stitch mockups are generated in Tailwind. Stitch is a reference
  point only. Every UI piece gets a mockup shown for approval before
  the real build.
- Minimal/YAGNI code discipline: no unrequested abstractions, simplest
  solution that actually works, never at the expense of security,
  validation, or accessibility.
- No em dashes or en dashes anywhere in code, comments, commit
  messages, or UI copy. No comments that restate the obvious.

---

## 2. Known Knowns (Finalized Core)

- **Data model** — Spaces, Asset Types, Assets, Routines, Tasks structure
  is locked (`PRD.md` §2, `ARCHITECTURE.md` §3)
- **Authorization model** — role-based + explicit space-access-grants for
  restricted zones, implemented via Laravel Policies (`SECURITY.md` §4,
  `ARCHITECTURE.md` §4) — this is the most architecturally important
  decision in the project and must not be redesigned without revisiting
  both documents
- **Color/type/component design system** — finalized in
  `DESIGN-SYSTEM.md`, tested through two Stitch iteration rounds
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

1. **Phase 1: complete.** Laravel backend scaffolded in `arkworkers-api/`.
   Migrations for Spaces, Asset Types, Assets, Routines, Tasks, Users,
   Vehicles. Email + password auth with an email-OTP signup verification
   step, rate limited. No SMS anywhere, cost decision. SpacePolicy, AssetPolicy,
   RoutinePolicy, TaskPolicy, all delegating through SpacePolicy for
   the space-restriction check. 44 tests passing.
2. **Phase 2 (next): core task loop.** Task list, detail, completion,
   basic proof upload (non-resumable first), and Space/Asset CRUD
   controllers for Admin/Facility Manager.
3. Phase 3: offline reliability layer (sync, resumable upload, conflict
   resolution) — flagged by research as the phase most critical not to
   rush or skip
4. Phase 4: scheduling (hybrid PM triggers) and reporting
5. Phase 5: polish (task messaging, asset history, PM compliance KPI)
6. Phase 6: Android + iOS packaging via Capacitor
7. Resolve the open decisions listed in `SECURITY.md`, `ARCHITECTURE.md`,
   and `DEPLOYMENT.md` before they block a specific build step (e.g.
   session model needs deciding before auth is built)
8. Assign a real person to the monthly physical-backup responsibility
   (`SECURITY.md` §11) — not yet assigned to anyone

## 4. Unknown Knowns (Implicit Design Patterns)

- Unique works in **checkpointed batches** — prefers completing and
  verifying one deliverable fully before moving to the next, rather than
  parallel half-finished work (evidenced by the PRD → Security → Design
  → Logo sequence, each fully closed out before the next started)
- Strong preference for **catching my own mistakes before presenting
  work** — the security audit and the logo asset generation both
  benefited from a self-check pass before delivery; this should continue
  as standard practice, not a one-off
- Prefers **concrete numbers/values over vague guidance** — e.g. asked
  for exact rate-limit numbers rather than "implement rate limiting,"
  exact hex codes rather than "use warm colors"
- Building a **reusable protocol library** (`shared-protocols` repo)
  alongside project-specific work — general patterns should be
  extracted there, not just solved once for ArkWorkers

## 5. Unknown Unknowns (The Blindspot Log)

**Phase 1 self-audit findings (found and fixed, see LESSONS.md):** an
unused parameter left over from an earlier policy draft, a cascade
delete that would have silently destroyed task audit history, and a
missing rate limit on the OTP request endpoint. None of these were
caught by the tests that were already passing, since tests only prove
what they were written to check. A deliberate audit pass found them.

**Pre-emptive risk flagged from documentation review, not yet
code-verified:** the offline-sync re-validation logic
(`ARCHITECTURE.md` §5, `SECURITY.md` §6.4) is conceptually specified but
is exactly the kind of feature that's easy to get subtly wrong in
implementation (race conditions between reconnect-sync and a
simultaneous access-grant revocation). Flag this for extra scrutiny
during the first security audit pass once built.
