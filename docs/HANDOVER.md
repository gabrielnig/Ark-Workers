# ArkWorkers.app — Handover Document

**Purpose:** everything a new developer, contractor, or stakeholder needs
to get oriented on this project cold, without a live walkthrough.

---

## 1. What This Project Is

ArkWorkers is a facility-management app for King's Palace & Ark of Jesus
Ministries International (Lagos, Nigeria) — tracks cleaning/maintenance
tasks, physical assets (AC units, generators, instruments, vehicles), and
a 6+ vehicle fleet, used by cleaning staff, maintenance technicians,
security, and drivers via a mobile-first PWA and Android app.

## 2. Read These Documents, In This Order

1. `docs/PRD.md` — what the app does and why; the full requirements
   brainstorm covering every physical space and asset category
2. `docs/SECURITY.md` — the security/compliance spec, including Nigeria's
   NDPA legal requirements and the space-level restriction model (the
   Prophet's Quarters is the key example driving that design)
3. `docs/ARCHITECTURE.md` — the technical stack and how the PRD's
   requirements map onto actual data models and code structure
4. `docs/DESIGN-SYSTEM.md` — visual identity, component specs, and the
   finalized logo (assets in `docs/assets/logo/`)
5. `ai-context.md` (repo root) — the current state snapshot; read this
   FIRST if you only read one document, then go deeper via the above
6. `LESSONS.md` (repo root) — mistakes already made and fixed; check
   before re-solving a problem that's already been hit

## 3. Key People & Roles

- **Unique** — developer, digital branding consultant, owns this build
- **The church (King's Palace & Ark of Jesus Ministries)** — the client/
  end user organization; the Pastor/Prophet has a private residence
  (the Quarters) on the property with special data-access requirements

## 4. Repo Structure

- `gabrielnig/Ark-Workers` — this project's repo, all docs in `docs/`
- `gabrielnig/shared-protocols` — reusable engineering standards (security
  baseline, UI/UX standard, audit prompts, self-healing pipeline)
  referenced across ALL of the developer's projects, not just this one —
  read it for general patterns, but project-specific decisions always
  live in Ark-Workers' own docs

## 5. The Single Most Important Architectural Concept

Everything in this app is **modular**: Spaces, Asset Types, Assets, and
Routines are all user-creatable data, not hardcoded features. A new
auditorium, a new drum kit, a new AC unit — all just new records, never
new code. The **one exception requiring actual code-level care** is the
space-level restriction system (for the Prophet's Quarters and any future
restricted zone) — this must be enforced server-side via Laravel Policies
on every single query touching a Space, Asset, or Task, never client-side
only. See `SECURITY.md` §4 and `ARCHITECTURE.md` §4.

## 6. Current Status

Documentation phase complete. No code written yet. Stack decided
(Laravel + PostgreSQL backend, React PWA + Capacitor for Android, iOS
planned as a low-effort future addition). Industry research complete
(`RESEARCH.md`) — validated the architecture, added a hybrid PM-trigger
improvement. Backup strategy decided (3-2-1 rule, `SECURITY.md` §11).
**Next step: Phase 1 — scaffold the Laravel backend data model and
authorization Policies.**

## 7. What's NOT Decided Yet

See the "Open Decisions" sections at the end of `SECURITY.md`,
`ARCHITECTURE.md`, and `DESIGN-SYSTEM.md` — these need real answers from
Unique/the church before (or during) the relevant build step, not
assumptions made silently during implementation.

## 8. How to Keep This Document Useful

Update this file whenever: a new person joins the project, a major
architectural decision changes, or the project reaches a new phase
(e.g. "backend scaffolded" → "MVP deployed"). This is a snapshot
document, not a changelog — `CHANGELOG.md` is for version history,
this is for "what does someone need to know right now."
