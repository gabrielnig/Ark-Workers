# ArkWorkers.app — Lessons Learned

Running log of mistakes, surprises, and corrections during this build —
so the same mistake doesn't get made twice, by a human or an AI session.

**Format:** newest entries at the top. Each entry: what happened, what
the actual problem was, what to do differently going forward.

---

## Pre-Build / Documentation Phase

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
