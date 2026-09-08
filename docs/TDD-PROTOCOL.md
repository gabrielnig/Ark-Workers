# ArkWorkers.app - TDD & Sandbox Verification Protocol

**Status:** binding process document. Read alongside `TESTING.md`
(what to test) and `LESSONS.md` (mistakes already made). This document
covers how we work, not what to test.

---

## 1. The Rule

No code is written without a test proving it does what it claims, and
no code reaches the VPS without that test having actually run, green,
in the sandbox first. This applies to every migration, model, policy,
controller, and route added to the project. It applies most strictly
to authorization code, since that is the highest-risk surface in the
app (SECURITY.md 4).

## 2. The Cycle

For each unit of work (a policy, an endpoint, a migration):

1. Write the migration/model/class.
2. Write the test(s) for it before or immediately alongside the
   implementation, covering the happy path, the denial path, and the
   specific edge cases called out in SECURITY.md or ARCHITECTURE.md
   for that feature (not just a generic smoke test).
3. Run the full suite in the sandbox: `php artisan test`.
4. If anything fails, fix it in the sandbox and re-run. Nothing moves
   forward on a red suite.
5. Only once the full suite is green does that unit of work count as
   a checkpoint. Report the pass count to Unique before moving on.
6. Nothing is pushed to GitHub or deployed to the VPS on a red suite,
   ever, regardless of how small the change looked.

## 3. What Counts as a Passing Checkpoint

- The specific new test(s) for the feature pass.
- The entire existing suite still passes (no regressions introduced).
- For authorization code specifically: every role x restriction
  combination named in SECURITY.md 4 has an explicit test, not just
  the ones that happen to be convenient to write.

## 4. Framework

PHPUnit (Laravel's default), per the tests already in the repo. This
resolves the open decision flagged in `TESTING.md` 4.

## 5. Sandbox Environment

- SQLite in-memory-equivalent database for the automated suite. Fast,
  no external service dependency, safe to run repeatedly.
- Production and staging remain PostgreSQL per `ARCHITECTURE.md` 1.
  Migrations are written to be database-agnostic; if a feature ever
  needs a Postgres-only construct, that gets flagged and tested
  against Postgres specifically before merge, not assumed to behave
  identically.

## 6. What Does Not Need a Dedicated Test

A plain data model with no branching logic, no computed behavior, and
no role in an authorization decision (e.g. a model that is only
attributes, casts, and relationships) does not get a dedicated test
file just to have one. Testing it would only be testing Eloquent
itself, not our code, and adds maintenance weight without catching
real regressions. It is still exercised indirectly wherever it is used
via factories in other tests. The moment it gains real logic, that
logic gets a test written alongside it, per the cycle in Section 2.

## 7. Audit Trail

Every session's `CHANGELOG.md` entry states the test count at the end
of the session (e.g. "25 tests passing"). If a session ends with any
failing test, that is logged in `LESSONS.md`, not just fixed silently.
