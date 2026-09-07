# ArkWorkers.app — Testing Strategy

**Status:** v1 — strategy defined ahead of code; concrete test files
added as features are built.

---

## 1. What Must Be Tested Before Anything Is Called "Done"

Priority order, given this app's actual risk profile:

1. **Authorization (highest priority)** — every Space/Asset/Task endpoint
   tested against: correct role + no grant (should fail on restricted
   spaces), correct role + valid grant (should succeed), wrong role
   entirely (should fail). This is the single most important test
   surface in the whole app — see `SECURITY.md` §4.
2. **Offline sync conflict handling** — simulate two devices completing
   the same task offline, reconnecting in different orders; verify the
   first-sync-wins + logged-discard behavior from `ARCHITECTURE.md` §5.
3. **Resumable upload** — simulate a dropped connection mid-upload,
   verify resume-from-offset actually resumes rather than restarting.
4. **Rate limiting** — verify the concrete limits from `SECURITY.md`
   §9.1 actually trigger at the stated thresholds, not just "some" limit.

## 2. Test Types

- **Unit tests** (Laravel) — Policy classes especially; these are the
  security-critical logic and deserve the most thorough unit coverage.
- **Feature/integration tests** (Laravel) — full request/response cycle
  through actual routes, not just isolated classes.
- **Frontend component tests** — the upload flow and offline-state
  handling are the highest-value things to test given their complexity.
- **Manual QA checklist** — run before every deploy (see
  `DEPLOYMENT.md` §6 for the post-deploy smoke test).

## 3. Automated Security Testing

Once there's a real codebase, run
`shared-protocols/security-scanning-harness.md` per its cadence table —
PR-level for changed files, nightly on core branches, weekly full sweep
before any release.

## 4. Not Yet Decided

```
[ ] Test framework choice for Laravel (Pest vs. PHPUnit)
[ ] Frontend test framework (Vitest, Jest)
[ ] CI integration — run tests automatically on every push?
```
