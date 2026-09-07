# ArkWorkers.app — Security & Compliance Document

**Church:** King's Palace & Ark of Jesus Ministries International
**Stack:** Mobile-first PWA + Android app, self-hosted on private VPS, no
third-party BaaS
**Status:** v1 — derived from PRD.md, shared-protocols/SECURITY-BASELINE.md,
OWASP ASVS/MASVS, and Nigeria Data Protection Act (NDPA) 2023
**Prepared as:** exhaustive pre-build security specification

---

## 1. Why This Document Exists

ArkWorkers is self-hosted on a private VPS with no managed platform safety
net (no managed auth provider, no managed WAF, no BaaS-provided row-level
security). Every control in this document has to be deliberately built —
nothing is inherited for free. It also handles data that is unusually
sensitive for a facility-management app:

- **Personal data of staff and drivers** (assignments, task logs, possibly
  phone numbers) — subject to Nigeria's NDPA 2023.
- **A private residential space** (the Prophet's Quarters) with an explicit
  requirement for restricted, non-default visibility — this is an
  access-control problem, not just a data problem.
- **Vehicle documents** (insurance, roadworthiness, registration/particulars)
  with expiry dates — sensitive administrative data, not public.
- **Photo/video proof of task completion**, captured and uploaded from
  mobile devices, often over unreliable connections, by non-technical
  field staff (cleaning, maintenance, security).

This document is the security counterpart to `docs/PRD.md` and should be
read alongside `shared-protocols/SECURITY-BASELINE.md` (the general
cross-project standard) — this file exists to apply that standard
concretely to ArkWorkers' actual data model and adds the Nigeria-specific
legal layer and mobile/offline-specific controls the general baseline
doesn't cover.

---

## 2. Regulatory & Legal Compliance — Nigeria Data Protection Act (NDPA) 2023

The NDPA 2023 (effective June 12, 2023) is Nigeria's binding data
protection law, enforced by the Nigeria Data Protection Commission (NDPC).
It applies to ArkWorkers because the app processes personal data of
Nigerian data subjects (staff, drivers, and indirectly, the Prophet and
family via the Quarters records).

### 2.1 Applicability & Classification
- ArkWorkers is very unlikely to hit the **"Data Controller/Processor of
  Major Importance"** threshold (2,000+ data subjects in 12 months, or
  1,000+ in 6 months) given the church's staff size — but the core
  obligations below apply regardless of that threshold; only the
  heavier obligations (mandatory DPO registration, annual Compliance
  Audit Report filing with NDPC) are threshold-gated.
- Re-check this classification if the platform is ever extended to serve
  multiple churches/branches (a multi-tenant future), since data subject
  count would then aggregate.

### 2.2 Core Obligations (apply regardless of size)
- **Lawful basis for processing** — for staff, this is generally
  contractual/employment necessity; document this per data category
  collected (name, phone, task logs, photos, vehicle assignment).
- **Data minimization** — only collect what's operationally necessary.
  Concretely: don't collect staff home addresses, national ID numbers, or
  banking details unless a specific feature requires it — none of the
  requirements gathered so far need this.
- **Consent for anything beyond core operational use** — e.g. if staff
  photos/task-proof media were ever reused for non-operational purposes
  (training materials, marketing), separate explicit consent is required.
- **Data subject rights** — staff must be able to know what's held about
  them and request correction; build this as an admin-mediated process
  initially (a staff member asks the Facility Manager/Admin), not
  necessarily a self-service portal at launch.
- **Security safeguards proportionate to sensitivity** — encryption,
  backups, access control, testing (see Sections 3–6).
- **Breach notification** — notify NDPC within **72 hours** of becoming
  aware of a breach likely to pose high risk to individuals; notify
  affected individuals directly if high risk is present. Maintain a
  **breach register** (cause, impact, remediation) regardless of whether
  a breach ever needs external reporting — internal record-keeping is a
  compliance expectation on its own.
- **Data Protection Impact Assessment (DPIA)** — not required at
  ArkWorkers' scale/risk profile today (no automated decision-making, no
  large-scale profiling, no biometric processing), but should be revisited
  if any future feature adds biometrics (e.g. facial-recognition gate
  access) or automated staff performance scoring.

### 2.3 Practical NDPA Checklist for ArkWorkers
```
[ ] Document lawful basis for each category of personal data collected
[ ] Data minimization reviewed — no unnecessary fields in Staff/Driver models
[ ] Privacy notice drafted for staff (what's collected, why, how long kept)
[ ] Breach register created (even if empty) — template + process defined
[ ] Data retention periods defined per data type (see Section 7)
[ ] Vehicle document data (insurance/roadworthiness) access-restricted to
    Admin/Facility Manager roles only, not all staff
[ ] Re-evaluate "Major Importance" threshold if platform scope expands
    beyond one church/property
```

---

## 3. Identity, Authentication & Session Security

### 3.1 Authentication
- Passwords: prioritize length over complexity rules; minimum 12
  characters recommended; never block paste in password fields (per
  `UI-UX-STANDARD.md`); use `autocomplete="new-password"`.
- Passwords stored via a strong adaptive hash (bcrypt/argon2), never
  reversible encryption, never plaintext.
- Given the low-tech-literacy staff base (cleaning, security, drivers),
  favor **PIN + device-bound session** or **phone-number + OTP** login over
  complex password requirements — reduces support burden without reducing
  security, since risk is mitigated by device possession + short session
  lifetimes instead of password complexity.
- Rate-limit login attempts: max 5 attempts per 15 minutes per
  account/IP, per the shared security baseline.

### 3.2 Sessions
- Session tokens are short-lived, refreshed via secure refresh tokens
  (httpOnly, Secure, SameSite cookies for the PWA web context; secure
  device keystore for the Android app).
- Sessions tied to a device identifier where practical, since staff
  devices are often shared or fixed per role/shift rather than 1:1
  personal devices — this needs an explicit product decision (see
  Section 9, Open Decision #1).
- Auto-logout after a defined inactivity period for roles with access to
  sensitive zones (Admin, Facility Manager) — shorter timeout than for
  Cleaning Staff/Security roles doing routine task logging.

### 3.3 Multi-Factor Considerations
- Not required at launch for all roles, but strongly recommended for
  **Admin/Pastor** role given it can see/manage the restricted Prophet's
  Quarters data and all financial/vehicle documents.

---

## 4. Authorization & the Row/Zone-Level Security Model

This is the most architecturally important section for ArkWorkers,
because the data model is not simple per-user ownership — it's
**role-based access layered with zone-level (Space-level) restriction**.

### 4.1 Roles (from PRD Section 6)
Admin/Pastor, Facility Manager, Cleaning Staff, Maintenance/Technician,
Security, Drivers — each needs distinct, enforced-server-side permissions.
Client-side role checks (hiding a button) are UX only and must never be
the actual security boundary.

### 4.2 The Prophet's Quarters Restriction — Concrete Implementation
The PRD flags the Quarters as needing "different rules — more
private/limited tracking." This must be implemented as an explicit
**space-level visibility flag**, not folded into the general role system:

```sql
-- Every query against Spaces, Assets, Routines, and Tasks must filter
-- through both the user's role AND the space's restriction flag —
-- never role alone.

SELECT * FROM assets
WHERE space_id = $requested_space_id
  AND (
    (SELECT is_restricted FROM spaces WHERE id = $requested_space_id) = false
    OR $authenticated_user_role IN ('admin', 'pastor')
    OR $authenticated_user_id IN (
         SELECT user_id FROM space_access_grants
         WHERE space_id = $requested_space_id
       )
  );
```

- Add a `space_access_grants` table so specific individuals (e.g. the one
  cleaning staff member who services the Quarters kitchen) can be granted
  access to a restricted space **without** being promoted to Admin — this
  matches the PRD's note that the Quarters kitchen is cleaned daily by
  dedicated staff, distinct from the general Saturday crew.
- This same pattern (space-level restriction + explicit access grants)
  should be the template for any future restricted zone, not a one-off
  special case hardcoded for the Quarters alone — keeps the system
  modular per the PRD's core requirement.
- Restricted-space data must also be excluded from generic dashboard
  aggregates, daily summary reports, and search results for any role
  without a grant — a restriction that only hides the detail view but
  still surfaces the space's name/existence in an overview list is not
  actually restricted.

### 4.3 General IDOR Prevention
Every asset, task, vehicle, and routine record must be scoped by
authenticated user + role + space grant on every read/write — never by a
client-supplied ID alone. Apply the pattern from
`shared-protocols/SECURITY-BASELINE.md` Section 4 uniformly across every
API route.

### 4.4 Vehicle & Driver Data Access
- Vehicle documents (insurance, roadworthiness, registration/particulars)
  and fuel/mileage logs: visible to Admin, Facility Manager, and the
  vehicle's assigned driver only — not the full staff list.
- Document expiry alerts (Section 7) should notify Admin/Facility Manager
  by default; the assigned driver optionally, depending on your
  preference — flagging as an open decision (Section 9).

---

## 5. Input, Upload & File Handling Security

### 5.1 Task Proof Photo/Video Uploads
This is a core, high-frequency data flow (per PRD Section 7 — proof
required on every completed task) and deserves specific hardening:

- **File type validation server-side**, not just by file extension —
  validate actual file signature/MIME type on the server before
  accepting.
- **File size limits** enforced both client-side (fast feedback) and
  server-side (actual enforcement) — mobile video files can be large;
  define a sane cap (e.g. 50–100MB) and compress/transcode on upload
  where feasible to control VPS storage growth.
- **Storage isolation** — uploaded media stored outside the web root or
  behind authenticated access; never served from a directly guessable
  public URL. Access to a specific task's media must go through the same
  role + space-grant check as the task record itself.
- **Resumable upload handling** per `UI-UX-STANDARD.md` Section 4 is a UX
  requirement, but has a security dimension too: partial/resumed uploads
  must be re-validated (type, size) on completion, not trusted based on
  the initial chunk's headers.
- **Metadata stripping** — strip EXIF/geolocation metadata from uploaded
  images before storage unless location data is an intentional feature
  (e.g. verifying a task was completed on-site) — if location verification
  is wanted, capture it explicitly and deliberately rather than relying on
  incidental EXIF data, which is inconsistent across devices.

### 5.2 General Input Validation
- Strict server-side schema validation on every form/API payload (per
  shared baseline Section 4).
- Parameterized queries only — no string-concatenated SQL anywhere,
  regardless of how the query is generated (including AI-assisted code).

---

## 6. Mobile / PWA / Offline-Specific Security (OWASP MASVS-aligned)

ArkWorkers is explicitly mobile-first PWA + Android, used by field staff
who may have inconsistent connectivity. This maps to OWASP's MASVS
categories:

### 6.1 MASVS-STORAGE (data-at-rest on device)
- Any data cached locally for offline task completion (routine checklists,
  pending photo uploads not yet synced) must be stored in the platform's
  sandboxed, app-private storage — never in a location accessible to
  other apps.
- **Sync as little as possible to the device.** Field staff devices should
  only receive the routines/tasks relevant to their assigned spaces and
  role — not a full data dump of the whole system. This is the same
  space-level access-grant logic from Section 4, applied to what gets
  synced for offline use, not just what's queryable online.
- Restricted-space data (Quarters) should generally **not** sync to
  general staff devices for offline use at all, given its sensitivity —
  only to the specific individuals with an explicit access grant.
- PWA offline storage (IndexedDB/Cache API) is not guaranteed encrypted by
  the browser — do not cache highly sensitive fields (vehicle document
  numbers, staff personal details) in offline storage; only cache what's
  operationally needed for task completion.

### 6.2 MASVS-NETWORK (data-in-transit)
- TLS 1.3 enforced for all traffic, including the PWA and Android app's
  API calls to the self-hosted backend — no plaintext HTTP anywhere, even
  internally.
- Certificate validity monitored; renewal automated (e.g. Let's Encrypt +
  auto-renewal cron) since this is self-hosted with no managed platform
  handling it automatically.

### 6.3 MASVS-AUTH (on-device)
- Session/refresh tokens stored in the Android app's secure keystore, not
  in plain SharedPreferences or local files.
- PWA: refresh tokens in httpOnly cookies (inaccessible to JS, mitigating
  XSS token theft), not localStorage.

### 6.4 Offline Sync Conflict & Integrity
- When a device reconnects after offline task completion, the sync
  process must re-validate the task/routine still exists, the space
  access grant is still valid, and the completing user's session is still
  authorized — a device that was valid when it went offline may not still
  be valid when it reconnects (role changed, access revoked, etc.).
- Handle sync conflicts (e.g. two staff both mark the same task complete
  offline) with a defined resolution rule (first-sync-wins with a visible
  log of the discard, not silent overwrite) rather than leaving it
  undefined.

---

## 7. Data Retention & Lifecycle

| Data Type | Suggested Retention | Notes |
|---|---|---|
| Task completion logs + proof media | 12–24 months rolling | Balance audit value vs. VPS storage cost; archive/compress older media rather than deleting outright if storage allows |
| Vehicle fuel/mileage logs | Life of vehicle + 1 year | Useful for resale/valuation history |
| Vehicle documents (insurance, roadworthiness, particulars) | Current + prior 2 renewal cycles | Older than that has no operational value |
| Staff account data | Duration of employment/service + defined post-departure window | Align with NDPA data minimization — don't keep indefinitely after someone leaves |
| Breach register entries | Indefinite | Compliance record, not operational data |
| Daily summary reports | 12 months, then aggregate/archive | Raw daily detail less useful long-term than trend data |

Define exact numbers with the church's admin team before launch — the
table above is a starting proposal, not a final policy.

---

## 8. Infrastructure & VPS-Specific Hardening

Since ArkWorkers is self-hosted with no managed BaaS (per project
overview), the full burden of infrastructure security sits with this
deployment specifically:

- **Environment/secrets** — DB credentials, JWT signing secrets, and any
  third-party API keys (SMS/OTP provider, etc.) live only in environment
  variables, never committed to the repo; `.env` confirmed in
  `.gitignore` from day one of the codebase.
- **Firewall** — VPS firewall restricts inbound traffic to only the ports
  actually needed (443/HTTPS, SSH on a non-default port with key-based
  auth only, no password SSH).
- **Database access** — DB not exposed to the public internet; accessible
  only from the application server (localhost or private network).
- **Backups** — automated, encrypted backups, stored off the primary VPS
  (separate storage/provider) so a VPS compromise doesn't also destroy
  backup integrity. Test restore procedure periodically — an untested
  backup is not a reliable backup.
- **Rate limiting & VPS resource protection** — per shared baseline,
  token-bucket rate limiting on all public endpoints; this matters more
  on a single VPS than on auto-scaling managed infrastructure, since
  resource exhaustion here means real downtime, not just cost.
- **Logging** — application and access logs retained and reviewed;
  logs must never contain raw passwords, tokens, or full vehicle document
  numbers in plaintext.

---

## 9. Open Decisions Needed Before Build

```
[ ] Device-bound sessions: are staff devices personal (1:1) or shared/
    role-based (e.g. one tablet per shift)? Changes the session model.
[ ] Vehicle document expiry alerts: notify assigned driver directly, or
    route only through Admin/Facility Manager?
[ ] Task-proof media retention window: confirm final number with church
    admin (12 vs 24 months proposed above).
[ ] Location verification on task-proof photos: intentional feature or
    explicitly not wanted? Determines EXIF/geolocation handling.
[ ] Does the Quarters kitchen staff member(s) get a standing space-access
    grant, or is it re-approved periodically?
```

---

## 10. Cross-Reference

This document should be read alongside:
- `docs/PRD.md` — the underlying data/feature requirements this security
  spec is protecting
- `shared-protocols/SECURITY-BASELINE.md` — the general cross-project
  security standard (STRIDE, OWASP Top 10, IDOR patterns, pre-deploy
  checklist, quick-use prompts)
- `shared-protocols/security-scanning-harness.md` — for running an
  automated vulnerability scan against the codebase once it exists
- `shared-protocols/ai-context-protocol.md` — for the ongoing architectural
  audit process as the project develops

---

## 11. Next Steps (per agreed roadmap)

1. ✅ Requirements brainstorm (PRD.md)
2. ✅ Security & compliance documentation (this document)
3. Finalize UI design system and direction
4. Begin implementation
