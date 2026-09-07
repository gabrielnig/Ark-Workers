# ArkWorkers.app — Architecture

**Status:** v1 — pre-build technical architecture
**Stack:** Laravel (PHP) + PostgreSQL backend · React PWA + Capacitor
(Android) frontend · self-hosted VPS, no third-party BaaS

---

## 1. Why This Stack

See the stack decision discussion — summary: Laravel's Policy/Gate system
maps directly onto the role + space-access-grant authorization model in
`SECURITY.md` §4; PostgreSQL's native RLS and JSON columns fit the
modular Asset Type system; React + Capacitor gives one codebase for both
the PWA and the Android app rather than two separate builds.

---

## 2. High-Level System Diagram

```
┌─────────────────────────┐        ┌─────────────────────────┐
│   React PWA (browser)   │        │  Capacitor Android App  │
│   - Service worker       │        │  (same React build,     │
│   - IndexedDB offline    │        │   native wrapper)        │
│     cache                │        │                          │
└───────────┬──────────────┘        └───────────┬──────────────┘
            │  HTTPS / JSON API                  │
            └──────────────────┬─────────────────┘
                                │
                    ┌───────────▼────────────┐
                    │   Laravel API (VPS)     │
                    │   - Sanctum auth        │
                    │   - Policies (space +   │
                    │     role authorization) │
                    │   - Queued jobs         │
                    │     (reports, expiry    │
                    │      checks)            │
                    └───────────┬────────────┘
                                │
                    ┌───────────▼────────────┐
                    │     PostgreSQL           │
                    │   (private network only, │
                    │    not internet-facing)  │
                    └──────────────────────────┘
                                │
                    ┌───────────▼────────────┐
                    │  Media storage           │
                    │  (task-proof photos/     │
                    │   video, outside web     │
                    │   root, auth-gated)      │
                    └──────────────────────────┘
```

---

## 3. Core Data Model

Directly implements the modular structure from `PRD.md` §2:

- **spaces** — id, name, parent_space_id (nullable, for drill-down),
  is_restricted (bool, per `SECURITY.md` §4.2)
- **space_access_grants** — user_id, space_id, granted_by, granted_at
  (explicit access to a restricted space, per `SECURITY.md` §4.2)
- **asset_types** — id, name, category, default_routine_template (JSON —
  flexible per-type checklist/routine definition)
- **assets** — id, asset_type_id, space_id, name, metadata (JSON, varies
  by type — e.g. vehicle plate number vs. AC unit refrigerant type)
- **routines** — id, asset_id (nullable) or asset_type_id (for
  type-level defaults), name, schedule (cron-like or trigger-based),
  requires_proof (bool)
- **tasks** — id, routine_id, assigned_user_id, due_at, completed_at,
  status (pending/completed/overdue)
- **task_proofs** — id, task_id, file_path, file_type, uploaded_at,
  chunk_upload_session_id (for resumable upload tracking)
- **users** — id, name, phone, role, pin_hash (or password_hash)
- **vehicles** — id, plate_number, assigned_driver_id, document_expiry
  dates (JSON or separate table per document type)
- **vehicle_logs** — id, vehicle_id, type (fuel/mileage/service),
  value, logged_at

Every table with a `space_id` or `asset_id` foreign key is subject to
the Policy-based authorization check from `SECURITY.md` §4 — no query
bypasses this by going through a different route.

---

## 4. Authorization Implementation (Laravel Policies)

```php
// SpacePolicy.php
public function view(User $user, Space $space): bool
{
    if (!$space->is_restricted) {
        return true;
    }
    return $user->hasRole(['admin', 'pastor'])
        || SpaceAccessGrant::where('user_id', $user->id)
            ->where('space_id', $space->id)
            ->exists();
}
```

Every controller action touching a Space, Asset, or Task routes through
its Policy — never a raw query filtered only by role. This is the
concrete implementation of the SQL pattern already specified in
`SECURITY.md` §4.2.

---

## 5. Offline Sync Strategy

- React Query (or SWR) with an IndexedDB persister caches only the
  routines/tasks relevant to the logged-in user's role and granted
  spaces — never a full data dump (per `SECURITY.md` §6.1).
- On reconnect, a sync endpoint re-validates: task/routine still exists,
  space access grant still valid, user session still authorized (per
  `SECURITY.md` §6.4) — before accepting any offline-queued completion.
- Conflict resolution: first-sync-wins, with the discarded duplicate
  logged (not silently dropped) — per `SECURITY.md` §6.4.

---

## 6. File Upload Architecture

- Chunked upload on the frontend (Uppy or equivalent), resumable from
  point of failure — per `UI-UX-STANDARD.md` §4 and `SECURITY.md` §5.1.
- Backend validates file signature/MIME server-side on chunk assembly,
  not just extension.
- Stored outside the web root; served only via an authenticated,
  policy-checked route — never a directly guessable public URL.
- EXIF/geolocation stripped on ingestion unless location verification is
  explicitly enabled (open decision in `SECURITY.md` §12).

---

## 7. Folder Structure (proposed)

```
arkworkers-api/          (Laravel backend)
  app/
    Models/
    Policies/
    Http/Controllers/Api/
    Jobs/                (daily reports, expiry checks)
  database/migrations/
  routes/api.php

arkworkers-web/           (React PWA, wrapped by Capacitor for Android)
  src/
    components/
    screens/
    hooks/               (offline sync, upload resumption)
    api/                 (typed API client)
  capacitor.config.ts
```

---

## 8. Cross-Reference

Read alongside:
- `PRD.md` — feature/data requirements this architecture implements
- `SECURITY.md` — the authorization, encryption, and offline-security
  rules this architecture must enforce
- `DESIGN-SYSTEM.md` — the component/visual spec the React frontend
  builds to
- `shared-protocols/SECURITY-BASELINE.md` — general security patterns

---

## 9. Open Decisions

```
[ ] Sanctum token auth vs. session-based auth for the PWA — affects
    exact offline-token-refresh behavior
[ ] Queue driver: database queue (simplest, no extra infra) vs. Redis
    (faster, one more service to maintain on the VPS)
[ ] Media storage: local disk vs. a self-hosted S3-compatible store
    (MinIO) — affects backup strategy from SECURITY.md §11
```
