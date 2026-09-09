# ArkWorkers.app — Lessons Learned

Running log of mistakes, surprises, and corrections during this build —
so the same mistake doesn't get made twice, by a human or an AI session.

**Format:** newest entries at the top. Each entry: what happened, what
the actual problem was, what to do differently going forward.

---

## Auth / Org-Structure Rework (Phase 3 prerequisite)

### A production build succeeding proves almost nothing about correctness
**What happened:** `npm run build` succeeded cleanly for the real
Login/Sign-up/Admin screens, which proved nothing about whether the
frontend and backend actually agreed on anything. Curling the exact
request shapes the compiled code sends (right headers, right sequence:
prime CSRF, then submit) against a live backend surfaced two real bugs
a clean build gave zero signal about: account-request submission would
have 419'd for every real user, and unauthenticated requests without
an explicit Accept header 500'd instead of cleanly 401ing.
**Lesson:** for any frontend/backend integration point, verify the
actual request/response contract over real HTTP with the exact headers
the real client sends, not just that the frontend compiles and the
backend's own test suite is green in isolation. A backend test suite
using `getJson()` can mask a bug that a plain `fetch()` call would hit,
since the test helper adds headers a real minimal client might not
send.

### A UI decision ("admin-approve sign-ups") turned into a full authorization rework
**What happened:** what started as "remove self-service registration,
add admin approval" surfaced that the existing single `role` enum
column couldn't represent the actual org structure once discussed in
detail (a worker in multiple departments, each with its own role,
department/role both admin-manageable). The fix touched the User
model, all four resource Policies, every factory, and 8 test files.
**Lesson:** before building a UI-driven change, check whether it's
compatible with the data model it sits on top of, not just whether the
screen looks right. "Just add an approval step" and "the underlying
permission model can represent this" are different questions, ask both.

### Laravel auto-bypasses CSRF whenever app.env is "testing", silently defeating the exact test meant to catch its absence
**What happened:** two new tests asserted a stateful login without a
valid CSRF token gets rejected with 419. Both passed immediately,
which should have been suspicious, not reassuring: `app()->runningUnitTests()`
returns true whenever `app.env === 'testing'`, which is always true
under PHPUnit, and Laravel's CSRF middleware skips validation entirely
in that case. The test could never have failed for the right reason.
**Lesson:** for any test asserting that framework-level protection
(CSRF, signed URLs, etc.) actually rejects something, check whether
the framework has a test-mode auto-bypass for that exact protection
before trusting a passing assertion. Force `app()->detectEnvironment`
or the relevant config to a non-testing value for just that assertion
if needed, and verify the test can genuinely fail (temporarily break
the code, confirm red, then fix).

### Mass-assignment protection silently drops fields instead of erroring
**What happened:** `User::create(['email_verified_at' => now(), ...])`
during invite activation silently produced a user with
`email_verified_at` still null, because that field isn't in `$fillable`.
Laravel drops non-fillable attributes from a mass-assignment array
without any error or warning by default.
**Lesson:** don't trust that a field passed to `::create()` actually
landed, especially for security-relevant fields deliberately kept off
`$fillable`. Assert the actual persisted value in a test, or set such
fields explicitly via `forceFill()` after creation, not through the
mass-assignment array.

### New public endpoints need an explicit rate-limiting check against SECURITY.md, every time
**What happened:** three new public endpoints (account request submit,
invite show, invite activate) shipped with zero rate limiting, missed
during initial implementation and only caught by rereading
SECURITY.md's public-endpoint requirement during the end-of-batch
audit, not proactively while writing the routes.
**Lesson:** add "check new public routes against SECURITY.md's rate-
limiting requirement" as a fixed item in the audit checklist, don't
rely on remembering it unprompted while writing the route. Same
applies to any other blanket security requirement stated once in the
docs but easy to forget applies to code written much later.

### Corrupted dictation input should stop work, not get guessed at
**What happened:** a few messages this session arrived as garbled/
binary-looking data instead of readable text, likely a dictation-app
glitch on Unique's end. The right response was to say plainly that the
message didn't come through and ask for it again, rather than attempt
to extract meaning from noise or silently proceed on the last clear
instruction without flagging the gap.
**Lesson:** when input is genuinely unparseable, say so directly and
ask for a resend, don't guess. Separately, some later self-reported
"I fixed that" claims (e.g. removing a UI element) turned out false on
verification, a `grep` after the edit would have caught it
immediately, so verify edits landed rather than trusting the edit
command succeeded just because no error was thrown.

## Phase 2 / Core Task Loop

### Data-loss policy needs deciding per entity, not assumed uniform
**What happened:** the Phase 1 audit fixed a cascade-delete that would
have wiped task history if a user was deleted. Building Asset CRUD in
Phase 2 surfaced the same class of problem on a different entity:
deleting an Asset cascaded through Routines and Tasks, wiping their
history too. The fix here was different from the Users fix, soft
delete with a 30-day grace period and scheduled pruning, rather than
blocking the delete outright.
**Lesson:** "don't lose data" is not one fix applied everywhere, it is
a decision made per entity based on what that entity's history means.
Users needed a permanent record with no deletion path at all, since
the whole point is tracking what a person did regardless of active
status. Assets needed the opposite: deletion is a normal, frequent
operation (decommissioning equipment), so blocking it outright would
be the wrong fix, soft delete plus a grace period preserves history
without preventing the everyday action. Ask which shape fits before
copying the previous fix.

## Phase 1 / Backend Scaffold

### Auth design changed mid-phase: PIN/phone-OTP replaced with email + password
**What happened:** Phase 1 built, tested, and shipped a phone-number +
PIN/OTP login flow per the original SECURITY.md §3.1. Partway through
the same session, the decision came to drop SMS entirely for cost
reasons, in favor of email + password login with an email-OTP
verification step at signup. Phone became an optional profile field.
**Lesson:** the migration, model, factory, controller, routes, and
every test in the auth layer had to be reworked, not just the parts
that looked auth-related. A cost/channel decision like "no SMS" cuts
across the whole login surface, not just one file, budget for a full
pass through migrations, models, controllers, and tests together
rather than patching pieces in isolation. SECURITY.md and
ARCHITECTURE.md were updated in the same session so the next session
does not build against the stale phone/PIN spec.

### A green test suite does not mean an audit is unnecessary
**What happened:** after building migrations, models, Policies, and
PIN/OTP auth with 42 tests passing, a deliberate senior-dev-style audit
pass (not prompted by any failing test) found three real issues: an
unused parameter left over from an earlier draft of AssetPolicy, a
cascade delete on tasks.assigned_user_id that would silently destroy
audit-relevant task history if a user was ever deleted, and a missing
rate limit on the OTP request endpoint that would let it be used to
SMS-bomb a phone number.
**Lesson:** passing tests only prove the code does what the tests
check for, not that nothing was missed. Run a deliberate audit pass
looking specifically for unused code, cascade/delete behavior on
audit-relevant tables, and missing rate limits on any endpoint that
sends something to a third party (SMS, email), at the end of a major
build phase, not just when something fails.

### Em dashes and over-explained comments crept into code, not just prose
**What happened:** while writing migrations, models, and Policy
classes, doc-comments and inline comments picked up em dashes and
over-explained "what" instead of "why", the same AI-writing tells
flagged for user-facing copy also show up in code comments if not
actively watched for.
**Lesson:** the "no em dashes, comments explain why not what" rule
applies to every line written, including code comments and commit
messages, not just UI-facing text. Grep for the em dash character
across the whole diff before considering a batch done.

## Pre-Build / Documentation Phase

### Backups need a physical/offline layer, not just cloud redundancy
**What happened:** initial `SECURITY.md` backup guidance specified
encrypted offsite backups but didn't address the scenario where both the
VPS and the cloud backup provider are simultaneously compromised or
inaccessible.
**Lesson:** for a self-hosted, single-point-of-failure deployment, the
3-2-1 rule (live copy + offsite cloud copy + physical/air-gapped copy)
is the actual standard, not just "backup to a different server." Always
ask whether a backup strategy has a true offline/air-gapped layer, not
just geographic/provider redundancy.

### Cross-reference renumbering breaks silently
**What happened:** while expanding `SECURITY.md` with new sections, three
internal cross-references ("see Section 9") went stale after later
sections got renumbered, and weren't caught until an explicit audit pass.
**Lesson:** any time a numbered-section document gets a new section
inserted (not appended), grep the whole file for "Section N" references
before considering the edit done. Don't rely on catching this by eye.

### Design tool color/font drift
**What happened:** Google Stitch, when given an exact 6-color hex
palette, generated a full derived Material Design 3 tonal palette instead
of using the values directly — and on the following correction round,
applied the fix to *new* screens/assets but not to the four *existing*
screens, because Stitch treated each addition as an isolated generation
context.
**Lesson:** when correcting an AI design tool's output, explicitly state
"regenerate the following existing files with only this change" rather
than assuming a correction request will propagate everywhere it should.
Verify by grepping the returned code for the exact expected values, not
by eyeballing screenshots.

### Recoloring a provided logo — background detection needs flood-fill,
not per-pixel distance
**What happened:** first attempt at cutting out a logo's background using
per-pixel color-distance thresholding left visible artifacts (a residual
background rectangle, then a speckled noise halo) because isolated
interior pixels happened to be near the background color threshold.
**Lesson:** for background removal/alpha cutout tasks, use connected-
component flood-fill from the image border to identify the *true*
contiguous background region, not a global per-pixel color distance
check — the latter misclassifies interior pixels that happen to be
color-similar to the background.

### Voice dictation naming errors
**What happened:** a dictated message named the app "Aquacast" instead of
"ArkWorkers" — almost led to building an entire design prompt around the
wrong name before catching it with a clarifying question first.
**Lesson:** when a dictated/voice message introduces a new proper noun
that doesn't match anything in prior context, confirm before proceeding
rather than assuming the new name is intentional.

---

## Template for New Entries

```
### [Short title]
**What happened:** [factual description]
**Lesson:** [what to do differently]
```
