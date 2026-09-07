# ArkWorkers.app — Glossary

Every domain-specific term used across the project's documents, defined
once here so nobody redefines or misuses them later.

---

**Space** — a physical zone in the facility (Sanctuary, Gallery,
Children's Church, an Office, a Restroom Block, the Prophet's Quarters,
etc.). Spaces can have a parent Space (drill-down hierarchy) and can be
flagged `is_restricted`.

**Asset Type** — a reusable, user-definable category of physical item
(e.g. "AC Unit," "Drum Kit," "Vehicle"). Carries default routine
templates. New Asset Types can be added without code changes.

**Asset** — one specific instance of an Asset Type, assigned to a Space
(e.g. "AC Unit — Sanctuary #3").

**Routine** — a recurring task definition tied to an Asset or Asset Type
(a cleaning routine, a maintenance routine), with a schedule and a
requires_proof flag.

**Task** — one occurrence of a Routine, assigned to a user, with a due
date and completion status.

**Task Proof** — the photo/video evidence attached to a completed Task.

**Restricted Space** — a Space with `is_restricted = true` (currently
only the Prophet's Quarters), visible/manageable only by Admin/Pastor
roles or users with an explicit Space Access Grant.

**Space Access Grant** — an explicit record granting a specific user
access to a specific restricted Space, without promoting them to an
Admin role. E.g. the one cleaning staff member who services the Quarters
kitchen daily.

**Zone-Level / Space-Level Security** — the authorization pattern where
access is checked against both the user's role AND the specific Space's
restriction status + grants — not role alone. The core architectural
concept of the whole authorization system (see `SECURITY.md` §4).

**Crypto-Shredding** — the PII deletion pattern where per-user encryption
keys are destroyed on account deletion rather than editing backup
archives directly (see `SECURITY.md` §8).

**NDPA** — Nigeria Data Protection Act (2023) — the legal framework
governing personal data handling for this project (see `SECURITY.md` §2).

**NDPC** — Nigeria Data Protection Commission — the regulator enforcing
the NDPA.

**Stitch** — Google's AI design tool, used to generate the visual
mockups referenced in `DESIGN-SYSTEM.md`.

**shared-protocols** — the separate repo (`gabrielnig/shared-protocols`)
holding reusable engineering standards used across ALL of this
developer's projects, not just ArkWorkers.
