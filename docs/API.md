# ArkWorkers.app — API Documentation

**Status:** stub — populate as endpoints are built. No code exists yet.

---

## Conventions (decide before first endpoint is built)

- Base URL: `https://arkworkers.app/api/v1/`
- Auth: Bearer token (Laravel Sanctum) in `Authorization` header
- All responses: JSON, `{ data: ..., meta: ... }` envelope for lists,
  `{ data: ... }` for single resources, `{ error: { message, code } }`
  for failures
- Every endpoint touching a Space/Asset/Task MUST pass through its
  Laravel Policy check — see `SECURITY.md` §4 and `ARCHITECTURE.md` §4.
  No endpoint returns data based on a raw ID lookup alone.

## Endpoint Groups (to be documented as built)

### Auth
- `POST /auth/login` — PIN or phone+OTP (per `SECURITY.md` §3.1)
- `POST /auth/logout`
- `POST /auth/refresh`

### Spaces
- `GET /spaces` — list, filtered by user's role + access grants
- `GET /spaces/{id}` — detail, 403 if restricted and no grant
- `POST /spaces` — admin/facility manager only
- `PATCH /spaces/{id}`

### Assets
- `GET /spaces/{id}/assets`
- `POST /assets`
- `PATCH /assets/{id}`

### Asset Types
- `GET /asset-types`
- `POST /asset-types` — admin only, defines the modular type system

### Routines & Tasks
- `GET /tasks` — filtered to assigned user by default
- `POST /tasks/{id}/complete` — requires proof upload per
  `SECURITY.md` §5.1
- `POST /tasks/{id}/proof` — chunked/resumable upload endpoint

### Vehicles
- `GET /vehicles` — scoped per `SECURITY.md` §4.4
- `GET /vehicles/{id}/documents`
- `POST /vehicles/{id}/fuel-log`

### Reports
- `GET /reports/daily-summary` — admin/facility manager only

## Rate Limits

See `SECURITY.md` §9.1 for the concrete per-endpoint-class limits —
implement exactly those values, don't re-derive.

## Changelog

Track breaking API changes here once the API is versioned/in use by the
mobile app — a breaking change to a deployed mobile client is a real
operational risk, not just a documentation note.
