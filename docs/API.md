# ArkWorkers.app - API Documentation

**Status:** Phase 1, 2, and the auth/org-structure rework live and
tested. Base URL and envelope conventions below are followed as-built.

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

### Auth
- `POST /auth/login` - email + password. Two response shapes depending
  on the caller's origin, decided automatically server-side, no client
  flag needed:
  - Browser/PWA request from the configured stateful frontend domain:
    sets a session cookie, response body is `{ user: ... }`, no token.
    Requires a CSRF token (`GET /sanctum/csrf-cookie` first, then send
    the `X-XSRF-TOKEN` header on this and every subsequent write)
  - Any other request (Capacitor Android, API clients, no matching
    Origin/Referer): response body is `{ token: ..., user: ... }`, use
    as a Bearer token in `Authorization` from then on
  - 403 if the account exists but isn't yet active (shouldn't normally
    happen post-invite-activation, invite activation itself verifies
    the email). Rate limited 5/15min per SECURITY.md §3.1
- `POST /auth/logout` (auth required) - ends the session (cookie
  clients) or revokes the current token (bearer clients), whichever
  applies
- `POST /auth/verify-email` - still exists but currently orphaned,
  nothing produces a valid code since self-service registration was
  removed, flagged as a cleanup item in `ai-context.md`

### Account requests (the admin-approval sign-up pipeline)
- `POST /account-requests` (public, rate limited 5/min) - name, email,
  phone (optional), `department_ids` (array, at least one). No
  password collected here. 422 if the email already has an account or
  already has a pending request
- `GET /account-requests` (Admin only) - list of pending requests with
  their requested departments
- `POST /account-requests/{id}/approve` (Admin only) - generates a
  single-use invite token (7-day expiry), emails it, 422 if the
  request was already reviewed
- `POST /account-requests/{id}/reject` (Admin only) - closes the
  request, no account is ever created

### Invites (public, consuming an approved request)
- `GET /invites/{token}` (rate limited 20/min) - returns the
  applicant's name/email/departments for the "complete your account"
  screen, read-only, pulled from the original request. 404 if the
  token is invalid, expired, or already used
- `POST /invites/{token}/activate` (rate limited 10/min) - password
  only, creates the real User, joins every requested department with
  the base Member role (the sign-up form doesn't collect a
  per-department role, an admin/department lead promotes from there),
  marks the invite consumed. Same 404 behavior as above for a
  bad/expired/reused token

### Spaces (auth required)
- `GET /spaces` - list, restricted spaces without a grant are excluded
  entirely, not just hidden
- `GET /spaces/{id}` - detail, 403 if restricted and no grant
- `POST /spaces` - Admin or a manager (any department role flagged
  `grants_management`)
- `PATCH /spaces/{id}`
- `DELETE /spaces/{id}` - Admin only, 409 if the space still has
  assets in it

### Assets (auth required)
- `GET /assets` - list, scoped the same way as spaces
- `GET /assets/{id}`
- `POST /assets` - checks management permission AND that the target
  space is visible to the requester
- `PATCH /assets/{id}`
- `DELETE /assets/{id}` - Admin only, soft delete, marks
  `decommissioned_at`. Permanently pruned 30 days later by a scheduled
  job, see `ai-context.md` for the reasoning

### Asset Types (auth required)
- `GET /asset-types` - any authenticated user
- `POST /asset-types` - Admin or a manager. This is the modular type
  system: a brand-new asset type needs no code change
- `PATCH /asset-types/{id}`

### Tasks (auth required)
- `GET /tasks` - scoped the same way as spaces/assets
- `GET /tasks/{id}`
- `POST /tasks` - manual/ad-hoc assignment (Admin or a manager). The
  automatic scheduler that generates tasks from a routine's
  calendar/meter trigger is Phase 4 scope
- `POST /tasks/{id}/complete` - assigned user or Admin/manager, and
  space access is still required even for the assigned user
- `POST /tasks/{id}/proofs/chunked/start` - begins a resumable upload
  session (filename, declared_mime_type, total_size, chunk_size).
  Max 50MB total. Returns a session_id
- `GET /tasks/{id}/proofs/chunked/{session}/status` - which chunk
  indexes the server already has, what a resuming client checks
  before re-sending anything
- `POST /tasks/{id}/proofs/chunked/{session}/chunks/{index}` - one
  chunk
- `POST /tasks/{id}/proofs/chunked/{session}/complete` - assembles
  every chunk in order, validates the assembled file's real
  signature and size (never individual chunk headers or the
  client-declared MIME type), creates the TaskProof, deletes the
  session and its chunk files. A small file is simply a 1-chunk
  upload, same code path, not a separate simple-upload endpoint

### Not yet built
- Vehicles endpoints (documents, fuel/mileage log) - data model exists,
  no controller yet
- Reports (`/reports/daily-summary`) - Phase 4 scope
- The automatic task-generation scheduler - Phase 4 scope

## Rate Limits

See `SECURITY.md` §9.1 for the concrete per-endpoint-class limits,
implement exactly those values, don't re-derive. Login is 5 attempts /
15 minutes per SECURITY.md §3.1. Account requests: 5/min. Invite show:
20/min. Invite activate: 10/min.

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
