# ArkWorkers.app - API Documentation

**Status:** Phase 1 and 2 endpoints live and tested. Base URL and
envelope conventions below are followed as-built.

---

## Conventions

- Base URL: `https://arkworkers.app/api/` (v1 prefix not yet added,
  flagged as an open decision below)
- Auth: Bearer token (Laravel Sanctum) in `Authorization` header
- Responses: `{ data: ... }` for single resources and lists,
  `{ message: ... }` for actions with no resource body
- Every endpoint touching a Space/Asset/Task passes through its
  Laravel Policy check, see `SECURITY.md` §4 and `ARCHITECTURE.md` §4.
  No endpoint returns data based on a raw ID lookup alone.

## Endpoint Groups (as built)

### Auth (public except logout)
- `POST /auth/register` - email, password, name, role, optional phone.
  Creates an unverified user and sends an email-OTP verification code
- `POST /auth/verify-email` - email + 6-digit code, marks the account
  verified
- `POST /auth/login` - email + password. 403 if not yet verified.
  Rate limited 5/15min per SECURITY.md §3.1
- `POST /auth/logout` (auth required) - revokes the current token

### Spaces (auth required)
- `GET /spaces` - list, restricted spaces without a grant are excluded
  entirely, not just hidden
- `GET /spaces/{id}` - detail, 403 if restricted and no grant
- `POST /spaces` - admin/pastor/facility manager only
- `PATCH /spaces/{id}`
- `DELETE /spaces/{id}` - admin/pastor only, 409 if the space still has
  assets in it

### Assets (auth required)
- `GET /assets` - list, scoped the same way as spaces
- `GET /assets/{id}`
- `POST /assets` - checks role AND that the target space is visible to
  the requester
- `PATCH /assets/{id}`
- `DELETE /assets/{id}` - soft delete, marks `decommissioned_at`.
  Permanently pruned 30 days later by a scheduled job, see
  `ai-context.md` for the reasoning

### Asset Types (auth required)
- `GET /asset-types` - any authenticated user
- `POST /asset-types` - admin/pastor/facility manager. This is the
  modular type system: a brand-new asset type needs no code change
- `PATCH /asset-types/{id}`

### Tasks (auth required)
- `GET /tasks` - scoped the same way as spaces/assets
- `GET /tasks/{id}`
- `POST /tasks` - manual/ad-hoc assignment (admin/pastor/facility
  manager). The automatic scheduler that generates tasks from a
  routine's calendar/meter trigger is Phase 4 scope
- `POST /tasks/{id}/complete` - assigned user or a privileged role,
  and space access is still required even for the assigned user
- `POST /tasks/{id}/proofs` - non-resumable file upload, validated by
  actual file content, stored outside the web root. Resumable/chunked
  upload is Phase 3 scope

### Not yet built
- Vehicles endpoints (documents, fuel/mileage log) - data model exists,
  no controller yet
- Reports (`/reports/daily-summary`) - Phase 4 scope
- The automatic task-generation scheduler - Phase 4 scope

## Rate Limits

See `SECURITY.md` §9.1 for the concrete per-endpoint-class limits,
implement exactly those values, don't re-derive. Login, email
verification, and OTP-related endpoints currently use 5 attempts /
15 minutes per SECURITY.md §3.1.

## Open Decisions
- Whether to add a `/v1/` prefix before the mobile app depends on a
  stable contract
- Response envelope for paginated lists (`meta` block) not yet needed,
  no endpoint returns enough records to paginate yet

## Changelog

Track breaking API changes here once the mobile app depends on this
API, a breaking change to a deployed mobile client is a real
operational risk, not just a documentation note. Nothing to log yet,
no external consumer exists.
