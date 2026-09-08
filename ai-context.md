# AI Context Matrix — ArkWorkers.app

**Last updated:** Pre-build (documentation phase complete, no code written yet)
**Update this file at the end of every significant work session** — this
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

**Current state: documentation-complete, zero code written.** PRD,
Security/Compliance spec, Design System, Architecture, and industry
Research are all finalized and in the repo. Logo is finalized. Backup
strategy (3-2-1 with a physical offsite copy) is decided. Next step is
scaffolding the Laravel backend — Phase 1 per the build plan in
`RESEARCH.md` §7.

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

1. **Phase 1 (starting now):** scaffold Laravel backend — migrations for
   the core data model, auth setup, Policy classes. Build and test
   authorization first, before any other feature, per `RESEARCH.md` §7.
2. Phase 2: core task loop (list, detail, completion, basic proof
   upload)
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

No code exists yet, so no code-level blindspots have been found. This
section activates once implementation starts — run the audit protocol
from `shared-protocols/ai-context-protocol.md` after the first major
milestone (e.g. after the data model + auth are scaffolded) rather than
waiting until the whole app is built.

**Pre-emptive risk flagged from documentation review, not yet
code-verified:** the offline-sync re-validation logic
(`ARCHITECTURE.md` §5, `SECURITY.md` §6.4) is conceptually specified but
is exactly the kind of feature that's easy to get subtly wrong in
implementation (race conditions between reconnect-sync and a
simultaneous access-grant revocation). Flag this for extra scrutiny
during the first security audit pass once built.
