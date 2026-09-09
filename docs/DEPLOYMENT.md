# ArkWorkers.app - Deployment

**Status:** v1, live. First deploy done this session, see below for
the actual infrastructure and the real gotchas hit getting there.

---

## 1. Environments

**Decided:** single environment, no separate staging/production split
for now. Revisit once the app is stable and has real users beyond
testing.

## 1.5 Live Infrastructure (as of first deploy)

- **VPS:** shared Contabo box (hostname `vmi3473287`, IPv4
  `169.58.93.100`), also hosting an unrelated project, "ARC Music"
  (Next.js, ports 3002/3003, arkmusicstream.com). ArkWorkers was added
  alongside it without touching ARC Music's Nginx sites, database, or
  certificates, all confirmed still working after our changes.
- **OS:** Ubuntu 22.04.5 LTS
- **Access:** SSH as a dedicated superuser, `arkdev` (password-based
  sudo, not passwordless). Deploys happen by giving `arkdev` exact
  commands to run and paste output back, no direct automated access.
- **Web server:** Nginx (already installed, reused). Two new server
  blocks added: `arkworkers.app` (serves the built React SPA as static
  files from `arkworkers-web/dist`) and `api.arkworkers.app` (Laravel,
  proxied to PHP-FPM via `/run/php/php8.3-fpm.sock`).
- **PHP:** 8.3.33, installed fresh via `ppa:ondrej/php`. Was not
  present at all before this deploy, since ARC Music is Next.js.
- **Database:** PostgreSQL 14, already installed and running, reused
  rather than freshly installed. New isolated database `arkworkers`
  plus user `arkworkers_user`, fully separate from whatever ARC
  Music's database is, own credentials, own privileges.
- **Composer:** installed fresh, was not present.
- **Deploy method:** git-pull plus migrate plus restart, no CI/CD.
  Code pulled via a dedicated read-only GitHub Deploy Key scoped to
  just this repo, not a personal access token (those expire or get
  revoked mid-session, a deploy key doesn't and can't push).
- **SSL:** Let's Encrypt via certbot (already installed, reused),
  covering `arkworkers.app`, `www.arkworkers.app`, and
  `api.arkworkers.app` on one certificate, auto-renewing.
- **Domain:** `arkworkers.app`, registered via Namecheap, DNS managed
  there, not on the VPS.

## 2. Pre-Deploy Checklist (every deploy)

Pulled directly from `SECURITY.md` §11 and §7 (pre-deployment checklist
in `shared-protocols/SECURITY-BASELINE.md`), do not skip on the
assumption "it's a small update":

```
[x] .env confirmed not committed, secrets confirmed in environment vars
[x] Database migrations reviewed for destructive changes (no dropped
    columns/tables without a backup taken first), first deploy, no
    prior data existed to lose
[x] Security headers (SECURITY.md §9.3) verified present after the
    Nginx config went live, caught HSTS missing after certbot's
    --redirect (it only adds the redirect, not HSTS), added manually,
    reverified
[x] Rate limits (SECURITY.md §9.1) verified still active, unchanged
    from what was already tested, no separate reverification needed
    this deploy
[ ] Backup taken immediately before deploy, verified restorable, NOT
    YET SET UP, see §2.5, this is a real gap on a now-live site
```

## 2.5 Backup Schedule (3-2-1, per SECURITY.md §11)

**Not yet set up. This is a real, live gap now that the site is
public.** There is currently no automated backup of the production
PostgreSQL database or uploaded proof media. Set this up before
relying on this deployment for anything beyond testing.

```
[ ] Nightly: automated encrypted DB + media backup to an off-VPS cloud
    provider (different company than the VPS host)
[ ] Monthly (or after any major milestone): download latest backup to a
    physical external drive, stored off the church network
[ ] Quarterly: actually test-restore from the physical copy to a
    scratch environment, confirm it works, don't assume it does
[ ] Decide and document: who is responsible for the monthly physical
    backup step (a person, not just "the system"), this is the one
    step in the chain that isn't automatable
```

## 3. Deploy Steps

**Decided:** simple git-pull based deploy, no CI/CD.

```bash
cd /var/www/arkworkers
git pull

cd arkworkers-api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
sudo chown -R www-data:www-data storage bootstrap/cache

cd ../arkworkers-web
npm install
npm run build

sudo systemctl reload nginx
```

No zero-downtime strategy set up (blue-green, etc.), a `git pull` plus
rebuild causes a brief window where the frontend `dist/` is being
overwritten. Acceptable at this scale and stage, revisit if this
becomes a real problem in practice.

## 4. Rollback Procedure

**Decided (2026-09):** git-based rollback for code, backup-restore for
data — two different mechanisms for two different failure modes, don't
conflate them.

**Code-only bad deploy** (bug in the new code, data is fine):
```bash
cd /var/www/arkworkers
git log --oneline -5          # find the last known-good commit
git checkout <good-commit-sha>

cd arkworkers-api
composer install --no-dev --optimize-autoloader
# Only run migrate if the bad deploy's migrations need reverting too,
# check `php artisan migrate:status` first — don't blindly re-migrate
sudo chown -R www-data:www-data storage bootstrap/cache

cd ../arkworkers-web
npm install
npm run build

sudo systemctl reload nginx
```
Afterward, fix forward on a branch rather than staying on a detached
HEAD — `git checkout main` once the fix is ready, don't leave the server
pinned to an old commit indefinitely.

**Data-loss or corruption** (bad migration, bad data write): stop the
application first (`sudo systemctl stop php8.3-fpm` — prevents further
writes while diagnosing), restore the database from the most recent
verified backup per §2.5, then redeploy known-good code as above.
**This entire path is currently untestable** — §2.5 has no backups yet,
this section documents the mechanism, not a proven-working recovery,
until §2.5 is actually implemented and restore-tested.

## 5. Monitoring

- Once built: wire up the self-healing pipeline from
  `shared-protocols/self-healing-pipeline.md` (Sentry + Claude auto-PR)
  for production error capture.
- Uptime monitoring for the VPS itself (separate from application-level
  error tracking), needs a provider decision.
- Nothing set up yet. `storage/logs/laravel.log` on the server is the
  only current way to see what went wrong, checked manually.

## 6. Post-Deploy Verification

```
[x] Smoke test: login works, verified over real HTTPS with a real
    admin account, both the login response and a follow-up
    cookie-only authenticated request
[x] Confirm restricted-space data still inaccessible to non-granted
    roles, covered by the existing test suite, not manually
    re-verified against production data this deploy since no
    restricted-space data exists yet on the live database
[ ] Task list loads, photo upload works, not yet manually verified
    on a real device, the actual point of this deploy, do this next
```

## 7. Real Gotchas Hit This Deploy (see LESSONS.md for the full writeups)

- **Namecheap's "Host" field needs just the subdomain (`@` for root,
  `api` for a subdomain), never the full domain name.** Typing the
  full domain there silently creates a wrong record
  (`arkworkers.app.arkworkers.app`) that looks identical in the URL
  bar but never resolves as intended, no error shown.
- **`composer install --no-dev` breaks any seeder that calls
  `fake()`**, since Faker is dev-only. `DatabaseSeeder` had a leftover
  "Test User" from the original Laravel scaffold that had never
  mattered until the first real `--no-dev` install. Removed at the
  source rather than worked around on the server.
- **`connect-src 'self'` in the CSP silently breaks every API call**
  once frontend and backend are split across subdomains, since
  `'self'` only covers same-origin requests. Had to explicitly add
  `https://api.arkworkers.app` to `connect-src`, a real deviation from
  SECURITY.md's literal text, documented there directly (§8.3).
- **`certbot --nginx --redirect` does not add HSTS on its own.** It
  only adds the HTTP-to-HTTPS redirect. Added
  `Strict-Transport-Security` manually after noticing it missing in a
  post-deploy header check.
- **`SESSION_DOMAIN=.arkworkers.app` (the leading dot) is what makes
  the session cookie shared across the frontend and API subdomains.**
  Never came up in local dev, since dev ran both on `localhost` with
  different ports, not different domains. This would have silently
  broken cookie-based login in production if not caught.
