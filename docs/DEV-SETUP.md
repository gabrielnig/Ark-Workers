# ArkWorkers.app — Developer Setup

**Status:** stub — filled in once the repos are scaffolded.

---

## Prerequisites

```
[ ] PHP version (Laravel's current LTS requirement — confirm at
    scaffold time)
[ ] PostgreSQL version
[ ] Node.js version (for the React frontend)
[ ] Composer, npm/yarn
```

## Backend Setup (`arkworkers-api`)

```
[ ] git clone <repo>
[ ] composer install
[ ] cp .env.example .env — fill in local DB credentials, never commit
    real secrets (per SECURITY.md §11)
[ ] php artisan key:generate
[ ] php artisan migrate --seed
[ ] php artisan serve
```

## Frontend Setup (`arkworkers-web`)

```
[ ] git clone <repo>
[ ] npm install
[ ] cp .env.example .env.local — point to local API URL
[ ] npm run dev
```

## Android (Capacitor) Setup

```
[ ] npx cap add android
[ ] npx cap sync
[ ] Open in Android Studio to run on device/emulator
```

## Running Security Checks Locally

Before pushing any change touching auth, Space/Asset queries, or file
uploads, use the Quick-Use Security Prompts in
`shared-protocols/SECURITY-BASELINE.md` (Appendix) against the specific
files changed — don't wait for a scheduled scan to catch an
authorization bug.

## Common Issues

(Populate this section as real setup problems get hit — cross-reference
`LESSONS.md` for anything that turns into a recurring pattern.)
