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
only the Prophet's Quarters), visible/manageable only by Admin or
users with an explicit Space Access Grant. Pastor is a title with no
permission weight and does not bypass this, per the auth/org-structure
rework.

**Space Access Grant** — an explicit record granting a specific user
access to a specific restricted Space, without promoting them to an
Admin role. E.g. the one cleaning staff member who services the Quarters
kitchen daily.

**Zone-Level / Space-Level Security** — the authorization pattern where
access is checked against both the user's Admin status AND the specific
Space's restriction status + grants — not Admin status alone. The core
architectural concept of the whole authorization system (see
`SECURITY.md` §4).

**Department** — an admin-manageable organizational unit (Cleaning,
Maintenance, Choir, Sound, etc.). A worker can belong to several.

**Role (department role)** — an admin-manageable standing within a
department (Member, Supervisor, Coordinator, etc.), not the same
concept as the retired fixed `role` column. A worker holds one role per
department they belong to, which can differ across departments. A role
can be flagged to grant management permission app-wide.

**Management Permission** — the ability to create/edit Spaces, Assets,
Routines, and assign Tasks. Held by Admin, or by anyone whose role in
any department is flagged `grants_management`. Never determined by a
hardcoded role or department name.

**Admin** — a global permission flag, separate from department
membership, the only status that bypasses restricted-space visibility.

**Title** — a plain label on a worker (e.g. "Pastor") with zero
permission weight, distinct from both Role and Department.

**Account Request** — a worker's sign-up submission (name, email,
phone, requested departments), reviewed by an Admin before any
login-capable account exists. No password is collected at this stage.

**Invite Link** — the single-use, expiring link an approved applicant
receives by email to set their password and activate their account.
The link itself is the email-verification step.

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
