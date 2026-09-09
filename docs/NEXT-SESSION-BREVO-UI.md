# ArkWorkers.app: Next Session, UI Overhaul via Brevo Design System Analysis

## Context to give Claude at the start of the session

Paste this, or something like it, to open the session:

> Continuing ArkWorkers.app. Read ai-context.md at the repo root first
> (github.com/gabrielnig/Ark-Workers), it has the full current state.
> The app is live at https://arkworkers.app and https://api.arkworkers.app.
> This session: a UI overhaul using Brevo's platform as a design
> reference. I'm going to use Claude for Chrome to analyze Brevo's
> actual interface, then we'll adapt what's genuinely good into
> ArkWorkers' existing design system, not replace it wholesale.

## The actual plan

1. Use Claude for Chrome to browse the real Brevo platform (not just
   describe it from training knowledge) and extract concrete,
   structured design information: color usage, typography, spacing,
   component patterns (buttons, forms, tables, navigation, cards),
   and overall layout conventions.
2. Compare that against ArkWorkers' existing locked design system
   (`docs/DESIGN-SYSTEM.md`, moss/sand/bark/clay palette, Plus Jakarta
   Sans + Work Sans, plain CSS, no Tailwind) the same way the Stitch
   export was compared against the My Work screen mockup earlier:
   adopt genuinely good structural ideas, strip anything that doesn't
   fit what ArkWorkers actually is or has already built.
3. This is **not** a rebrand. ArkWorkers keeps its own palette,
   typography, and identity. Brevo is a reference for structural/UX
   patterns (how they lay out dense data, spacing rhythm, component
   states, navigation conventions), not a source of colors or brand
   elements to copy directly. Watch for anything that doesn't match
   what's actually built or decided, same discipline as the earlier
   Stitch comparisons this session (don't inherit invented content,
   mismatched data models, or design decisions that were never made
   for ArkWorkers specifically).

## Claude for Chrome prompt (paste this into Claude for Chrome)

```
Go to app.brevo.com (or brevo.com if the app requires a login you
don't have, in which case explore whatever of the product is visible
publicly, including their marketing site's product screenshots).

Analyze the actual rendered interface, not just written descriptions
of the product. I need a structured design system breakdown I can
compare against an existing app's design system, covering:

1. Color usage: primary/accent colors and where each is used
   (primary actions, navigation, status indicators, backgrounds),
   approximate hex values if you can read them from computed styles
   or inspect element, not just guessed from the screenshot.

2. Typography: font family/families used, approximate size scale
   (headings vs body vs labels), weight usage, how hierarchy is
   established.

3. Layout structure: sidebar vs top nav, how the main content area is
   organized, how dense data (tables, lists) is laid out, spacing
   rhythm (tight vs generous), card/panel conventions.

4. Component patterns: button styles and states (primary/secondary/
   disabled), form input styling, table/list row design, status
   badges or pills, empty states, how they handle loading/pending
   states.

5. Navigation conventions: how the sidebar or main nav is structured,
   how active/current states are shown, any secondary nav patterns.

6. Anything distinctive about how they handle information density or
   hierarchy that feels deliberate rather than default.

Give me this as a structured breakdown I can hand to another AI
assistant to compare against a different app's existing design
system, not a general summary of what Brevo does as a product.
```

## Open questions to settle once the Chrome analysis comes back

- Which specific ArkWorkers screens is this overhaul actually for?
  (Admin dashboard? My Work? All of them? This wasn't specified when
  the plan was made, worth nailing down before building anything.)
- Same mockup-approval discipline as every other screen this
  session: whatever comes out of this analysis gets mocked up as an
  HTML file for review before any real component gets touched, not
  built directly from the Chrome analysis.
