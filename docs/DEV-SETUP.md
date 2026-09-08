# ArkWorkers.app - Developer Setup

**Status:** backend is scaffolded and running (Phase 1 and 2 complete).
Frontend not yet started.

---

## Prerequisites

- PHP 8.3+, with `pdo_sqlite` (local dev/test), `pdo_pgsql`
  (staging/production), `gd` (image proof uploads in tests), `mbstring`,
  `xml`, `curl`, `zip`
- Composer 2.x
- PostgreSQL (staging/production only, SQLite is used for local dev
  and the automated test suite, both use the same database-agnostic
  migrations)
- Node.js (once the React frontend starts)

## Backend Setup (`arkworkers-api/`)

```
cd arkworkers-api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # local dev uses SQLite
php artisan migrate
php artisan serve
```

## Running Tests

```
cd arkworkers-api
php artisan test
```

Per `docs/TDD-PROTOCOL.md`, the full suite must pass before anything is
pushed or deployed. As of the last session: 71 tests, 144 assertions.

## Code Style

```
cd arkworkers-api
./vendor/bin/pint
```

Run before every commit. Also grep the diff for em dashes/en dashes
before considering a batch done, per the standing no-AI-writing-tells
rule (see `ai-context.md`).

## Frontend Setup (`arkworkers-web`) - not started yet

```
[ ] git clone <repo>
[ ] npm install
[ ] cp .env.example .env.local - point to local API URL
[ ] npm run dev
```

Plain CSS only, never Tailwind, per the standing UI rule in
`ai-context.md`, even though Google Stitch mockups are generated in
Tailwind.

## Android (Capacitor) Setup - not started yet

```
[ ] npx cap add android
[ ] npx cap sync
[ ] Open in Android Studio to run on device/emulator
```

## iOS (Capacitor) - Future, Not Needed at Launch

Not set up yet, deferred per `ARCHITECTURE.md` §1 and §9. Documented
here so it's not forgotten and so it's clear it's a low-effort addition,
not a rewrite, when the time comes:

```
[ ] Requires macOS + Xcode (cannot build/sign iOS apps on Linux/Windows)
[ ] npx cap add ios
[ ] npx cap sync
[ ] Open in Xcode to run on simulator/device
[ ] Apple Developer account needed before any device testing or
    App Store submission
```

Until this is actually started: avoid any Android-only native plugin or
assumption when writing frontend code (see `ARCHITECTURE.md` §1) so this
step stays this simple when it happens.

## Running Security Checks Locally

Before pushing any change touching auth, Space/Asset queries, or file
uploads, use the Quick-Use Security Prompts in
`shared-protocols/SECURITY-BASELINE.md` (Appendix) against the specific
files changed, don't wait for a scheduled scan to catch an
authorization bug.

## Common Issues

- **"GD extension is not installed"** when running tests that fake an
  image upload (`UploadedFile::fake()->image(...)`): install
  `php8.3-gd` (or the equivalent for your PHP version) and restart.
  Hit during Phase 2 proof-upload test development.

(Populate this section further as real setup problems get hit,
cross-reference `LESSONS.md` for anything that turns into a recurring
pattern.)
