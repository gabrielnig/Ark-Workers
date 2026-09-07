# ArkWorkers.app — Design System

**Status:** v1 — visual identity and component standard
**Built on:** `shared-protocols/UI-UX-STANDARD.md` (interaction patterns,
psychological UX laws, resumable upload standard — inherited, not
repeated here)
**Direction:** Warm, approachable, high-legibility — built for
non-technical field staff (cleaning, maintenance, security) using phones
outdoors, in low light, and often mid-task with dirty or gloved hands.

---

## 1. Design Rationale

Two real constraints shaped every choice below, more than any aesthetic
preference:

1. **The primary user is not at a desk.** They're holding a phone one-handed
   while carrying a mop, standing in bright outdoor light checking the
   generator, or in the dim server closet. Contrast and touch-target size
   aren't nice-to-haves here — they're the difference between a task
   getting logged correctly or not at all.
2. **The tone has to feel like a helpful colleague, not an audit tool.**
   Staff are filling this in daily, often after physical work. A cold,
   clinical dashboard aesthetic (however "professional") works against
   adoption. Warm doesn't mean decorative — it means the interface reads
   as encouraging rather than surveillant, even though under the hood
   it's tracking completion rigorously.

Green was chosen for growth/upkeep association, but a literal "SaaS
green" (`#22C55E`-style) reads corporate-generic and clashes with warm.
Paired it with a warm neutral base and a clay/amber accent for secondary
actions and highlights, avoiding both the common AI-generated cream+terracotta
combination and the flat-corporate-green dashboard look.

---

## 2. Color System

### 2.1 Core Palette

| Token | Hex | Role |
|---|---|---|
| `--color-moss-900` | `#1F3D2B` | Deep green — headers, primary text on light surfaces in high-contrast contexts |
| `--color-moss-600` | `#3F7D4F` | Primary brand/accent — primary buttons, active states, links |
| `--color-moss-300` | `#9CC9A6` | Light green — success backgrounds, subtle highlights |
| `--color-clay-500` | `#C97B4A` | Secondary accent — warnings, secondary CTAs, in-progress states |
| `--color-sand-100` | `#F7F3EC` | Warm off-white — light mode background |
| `--color-bark-800` | `#332B24` | Warm near-black — dark mode background, body text on light mode |

This is deliberately not the cream-plus-terracotta AI-generated default —
the base is a softer sand rather than near-white cream, the accent is a
muted clay rather than saturated terracotta, and green (not the accent) is
the dominant brand color throughout.

### 2.2 Status Colors (task/asset state — used constantly across the app)

| State | Color | Token |
|---|---|---|
| Completed | `--color-moss-600` (`#3F7D4F`) | Task/routine marked done |
| Pending / due today | `--color-clay-500` (`#C97B4A`) | Not yet done, still on time |
| Overdue | `#B33A3A` (new: `--color-rust-600`) | Past due, needs attention |
| Restricted (Prophet's Quarters zones) | `#6B5B95` (new: `--color-plum-500`) | Visual flag on any restricted-space UI, distinct from the operational palette entirely so it never gets confused with a normal state |

Restricted-zone content should use the plum flag as a small badge/border
treatment, not restyle the whole screen — the goal is a clear "you're in a
restricted area" signal, not a jarring theme change.

### 2.3 Light Mode

| Token | Value |
|---|---|
| Background | `--color-sand-100` (`#F7F3EC`) |
| Surface (cards) | `#FFFFFF` |
| Primary text | `--color-bark-800` (`#332B24`) |
| Secondary text | `#6B6259` |
| Border | `#E4DDD0` |
| Primary accent | `--color-moss-600` |

### 2.4 Dark Mode

| Token | Value |
|---|---|
| Background | `--color-bark-800` (`#332B24`) |
| Surface (cards) | `#3F362D` |
| Primary text | `#F7F3EC` |
| Secondary text | `#C4BAAC` |
| Border | `#524739` |
| Primary accent | `--color-moss-300` (lightened for dark-surface contrast) |

### 2.5 Accessibility Requirement — Outdoor Legibility

Standard WCAG AA (4.5:1) is the **floor**, not the target. Given real
outdoor-glare use:
- Primary text on background: target **AAA (7:1)** where feasible —
  verified against both light and dark palettes above.
- Status colors (2.2) must remain distinguishable to color-blind users —
  pair every status color with an icon or shape, never color alone (a
  checkmark for completed, a clock for pending, an exclamation for
  overdue, a lock for restricted).
- Touch targets minimum 44×44px per Fitts's Law guidance in the shared
  UI-UX standard — non-negotiable given gloved/wet hands during cleaning
  tasks.

---

## 3. Typography

- **Family:** Nunito (primary, all UI text) — a rounded, friendly
  sans-serif that supports the warm direction without sacrificing
  legibility at small sizes. Single family throughout; weight does the
  work of hierarchy rather than mixing typefaces.
- **Scale** (mobile-first, following a ~1.25 ratio):

| Level | Size | Weight | Use |
|---|---|---|---|
| Display | 28px | 800 (ExtraBold) | Screen titles only |
| H1 | 22px | 700 (Bold) | Section headers |
| H2 | 18px | 700 (Bold) | Card/subsection headers |
| Body | 16px | 500 (Medium) | Default UI text — never smaller for primary content |
| Body Small | 14px | 500 (Medium) | Secondary/meta text |
| Label | 13px | 700 (Bold) | Form labels, status badges — sentence case, never all-caps (all-caps labels read as generated-template chrome and hurt legibility at a glance outdoors) |

- Line length: keep body text under 80 characters even on tablet-width
  screens — most screens here are narrow phone viewports anyway, so this
  mainly governs tablet/admin views.
- No decorative type treatments (no single-word accent coloring inside
  headlines, no tracked-out eyebrow labels above headers) — every piece
  of text on a task-logging screen has to be immediately parseable, not
  stylized.

---

## 4. Shape & Elevation

- **Corner radius:** 12px on cards and containers, 8px on buttons and
  inputs, 999px (full pill) on status badges and the navigation pills
  inherited from the shared UI-UX standard.
- **Shadows:** soft, warm-toned (not pure black) — `rgba(51, 43, 36, 0.08)`
  for resting cards, `rgba(51, 43, 36, 0.14)` on elevated/active elements.
  Avoid the generic flat grey `rgba(0,0,0,.1)` shadow used identically on
  every card regardless of hierarchy — reserve stronger shadow for
  genuinely elevated elements (modals, the active task card), keep list
  items closer to flush with the background.
- **Borders:** 1px, using the palette's border token — used to separate
  content within a card (e.g. checklist items) rather than shadow alone,
  since shadows can wash out in bright outdoor light.

---

## 5. Navigation & Layout

Inherits the pill-based navigation pattern from
`shared-protocols/UI-UX-STANDARD.md` Section 2, with ArkWorkers-specific
application:

- **Top-level pills:** Spaces / Tasks / Reports / (Admin-only: Vehicles,
  Staff) — fixed navigation bar, sub-view scales beneath it.
- **Space drill-down:** Space → Assets in that space → Asset's routines/
  tasks. Breadcrumb-style back navigation, not deep modal stacking, so a
  distracted user mid-shift can always tell where they are.
- **Restricted-space visual treatment:** any Space with `is_restricted`
  set (per `SECURITY.md` §4.2) shows the plum badge (2.2) in its card and
  in its header — visible at every level of the drill-down, not just on
  entry.
- **Bottom-anchored primary action:** on task-detail screens, the
  "Complete Task" / "Upload Proof" action stays fixed at the bottom of
  the viewport — thumb-reachable per Fitts's Law, since this is the
  single highest-frequency action in the whole app.

---

## 6. Component Notes (ArkWorkers-Specific)

### 6.1 Task Card
- Status badge (2.2) top-right of card, icon + color per the
  accessibility requirement above.
- Shows: task name, space, due/completed time, assigned staff (if
  relevant to viewer's role).
- Tapping opens the task detail with the upload flow from
  `UI-UX-STANDARD.md` Section 4 (resumable upload standard) — this is the
  single most-used component in the app and should get the most design
  attention and testing.

### 6.2 Asset Card
- Icon representing asset type (AC unit, instrument, vehicle, etc.) —
  build a small icon set per Asset Type category rather than a generic
  file/box icon for everything, since staff scan these visually while
  moving through a space.
- Shows last-serviced/last-cleaned date prominently — this is the
  at-a-glance value of the whole system.

### 6.3 Daily Summary Report (Admin view)
- Uses the moss/clay/rust status colors as a simple completion-rate bar,
  not a dense data table — the PRD's daily reporting requirement is meant
  to be scannable in under a minute by a Pastor/Admin, not analyzed like
  a BI dashboard.

---

## 7. What's Inherited, Not Restated Here

The following live in `shared-protocols/UI-UX-STANDARD.md` and apply to
ArkWorkers as-is — not duplicated in this document:
- Loading states (skeletons, spinners, progress bars, optimistic UI)
- OTP input handling
- Password field behavior
- Data table pagination
- Form validation timing (lazy-then-eager)
- The full resumable file-upload standard (critical for task-proof
  uploads — see Section 6.1 above for where it plugs in)

---

## 8. Open Decisions

```
[ ] Icon set: commission/select a specific icon library (e.g. Phosphor,
    Lucide) consistent with the rounded, warm direction — not yet chosen
[ ] Confirm Nunito licensing/self-hosting approach for the PWA (avoid a
    render-blocking Google Fonts request on slow mobile connections)
[ ] Empty-state illustrations: worth a small custom illustration set for
    "no tasks today" / "space fully clean" moments, or keep text-only?
```

---

## 9. Next Steps (per agreed roadmap)

1. ✅ Requirements brainstorm (PRD.md)
2. ✅ Security & compliance documentation (SECURITY.md)
3. ✅ UI design system and direction (this document)
4. Begin implementation
