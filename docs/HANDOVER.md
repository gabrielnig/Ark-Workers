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
6. `docs/BUILD-PLAN.md`, the live, checkbox-tracked source of truth
   for what's built vs. not, a real gap analysis from the actual code,
   not a wishlist
7. `docs/USER-STORIES.md`, every feature, per permission tier
   (Prospective Worker, Staff Member, Manager, Admin), each story
   tagged Built/Partial/Not built against real current state
8. `LESSONS.md` (repo root) - mistakes already made and fixed; check
   before re-solving a problem that's already been hit
9. `docs/TDD-PROTOCOL.md` - the binding rule for how code gets written
   and verified before it's pushed or deployed

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

**Live at https://arkworkers.app (frontend) and https://api.arkworkers.app
(backend).** The original pre-launch Phase 1, Phase 2, the auth/org-
structure rework, and Phase 3 (offline sync foundation, resumable
chunked upload) are all complete and deployed. Real Login, Sign-up,
Admin-requests, and My Work screens are built, tested against the
real backend, and verified over real HTTPS in production. Beyond
that, `docs/BUILD-PLAN.md`'s own Phase 1 (finish what was already
half-built) is also now complete: Routines (full CRUD, soft-delete
only so task/proof history survives permanently, including surviving
Asset pruning), Spaces (create/rename, breadcrumb drill-down), Asset
creation, Asset Types admin, and restricted-space access grants (a
restricted Space was previously unreachable by anyone except an Admin
once created, this closed that gap). A full UI visual overhaul
(Brevo-audited structural patterns plus a secondary color layer on
top of the existing palette) happened alongside this. 148 tests
passing (see `ai-context.md`/`CHANGELOG.md` for the current number,
this file is not kept in sync turn by turn). Frontend
(`arkworkers-web/`) has real screens for sign-in, sign-up, admin
approval, worker task management, Spaces, and Asset Types. See
`docs/DEPLOYMENT.md` for the live infrastructure and
`docs/BUILD-PLAN.md` for exactly what's built vs. not, checkbox by
checkbox. **Next step per `BUILD-PLAN.md`: Phase 2, Reporting.**
Real-device verification and a database backup schedule are still
open, both flagged in `DEPLOYMENT.md`.

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
