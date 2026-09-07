# ArkWorkers.app — Deployment

**Status:** v1 — process defined ahead of first deploy, per
`SECURITY.md` §11 (Infrastructure & VPS Hardening)

---

## 1. Environments

```
[ ] Confirm: single VPS with staging+production separated by
    environment config, or two separate VPS instances?
```

## 2. Pre-Deploy Checklist (every deploy)

Pulled directly from `SECURITY.md` §11 and §7 (pre-deployment checklist
in `shared-protocols/SECURITY-BASELINE.md`) — do not skip on the
assumption "it's a small update":

```
[ ] .env confirmed not committed, secrets confirmed in environment vars
[ ] Database migrations reviewed for destructive changes (no dropped
    columns/tables without a backup taken first)
[ ] Security headers (SECURITY.md §9.3) verified still present after
    any web server config change
[ ] Rate limits (SECURITY.md §9.1) verified still active
[ ] Backup taken immediately before deploy, verified restorable
```

## 3. Deploy Steps (fill in once the pipeline exists)

```
[ ] Git-based deploy (pull + migrate + restart) vs. CI/CD pipeline
    (GitHub Actions building and deploying automatically)?
[ ] Zero-downtime strategy: blue-green, or accept brief downtime for
    this scale of app?
```

## 4. Rollback Procedure

```
[ ] Define: how to revert a bad deploy (git revert + re-migrate down,
    or restore from the pre-deploy backup)?
```

## 5. Monitoring

- Once built: wire up the self-healing pipeline from
  `shared-protocols/self-healing-pipeline.md` (Sentry + Claude auto-PR)
  for production error capture.
- Uptime monitoring for the VPS itself (separate from application-level
  error tracking) — needs a provider decision.

## 6. Post-Deploy Verification

```
[ ] Smoke test: login works, task list loads, photo upload works
[ ] Confirm restricted-space data still inaccessible to non-granted
    roles (a quick manual check after any auth/policy-related deploy)
```
