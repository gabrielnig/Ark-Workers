# ArkWorkers.app — Industry Research & Feature Analysis

**Purpose:** exhaustive research into existing facility management, CMMS,
fleet management, and church-specific software, to inform what
ArkWorkers should build and in what order.
**Method:** web research across four categories: general CMMS/facility
management, fleet/vehicle management, church-specific facility software,
and mobile field-service/offline task apps, plus preventive maintenance
scheduling theory.

---

## 1. General CMMS / Facility Management Software

**Category leaders examined:** UpKeep, MaintainX, Fiix (now Rockwell
Automation), Limble, eMaint.

### What they all do well (table stakes — ArkWorkers needs these)
- Digital work orders: create, assign, schedule, track to completion
- Preventive maintenance (PM) scheduling with automatic work-order
  generation
- Asset registry with maintenance history per asset
- Mobile-first execution — technicians work primarily from a phone
- Checklists/procedure libraries attached to work order types
- Reporting dashboards: completion rates, overdue items, downtime

### Where they differentiate (useful signal for prioritization)
- **MaintainX** — positioned as "the WhatsApp of maintenance": real-time
  team messaging tied to work orders, extremely low training overhead,
  strongest mobile UX in the category. This validates our own emphasis
  on simplicity for non-technical staff.
- **UpKeep** — strongest for complex asset hierarchies + IoT sensor
  integration (their "Edge" hardware). Not relevant to ArkWorkers at
  our scale — this is enterprise/industrial territory.
- **Fiix** — now essentially an industrial OT platform since the
  Rockwell acquisition; too heavy for a single-church deployment.
- **Common complaint across all three:** advanced features (full
  reporting, offline mobile, asset lifecycle tracking) are frequently
  paywalled behind higher tiers — a real pricing pattern to note, not
  a feature list.

### Relevance to ArkWorkers
ArkWorkers is, structurally, a lightweight single-tenant CMMS — but
purpose-built for one church rather than sold as a horizontal product.
We should absolutely borrow: the work-order/task lifecycle model, PM
scheduling with automatic generation, and the asset registry pattern —
all already reflected in `PRD.md` and `ARCHITECTURE.md`. We should
explicitly NOT build: IoT sensor integration, multi-tenant portfolio
management, or complex parts/inventory procurement — none of these are
in scope and building them would be over-engineering for a single
property.

---

## 2. Fleet Management Software

**Category leaders examined:** Samsara, Fleetio, Geotab, Verizon Connect.

### Core feature set across the category
- GPS/live location tracking (hardware-dependent — out of scope for us)
- Fuel usage tracking and reporting
- Maintenance reminders tied to mileage/time
- Digital vehicle inspections with photos
- Document/compliance management (licensing, permits, insurance expiry)
- Driver assignment and behavior scoring (Samsara's AI dashcams — well
  beyond our scope and budget)

### What's directly relevant to ArkWorkers' 6+ vehicle fleet
- **Fleetio's approach is the closest match to our scale and need:**
  fuel tracking, customizable inspections with photos, document
  management, mobile access for drivers — no GPS hardware dependency
  required to get most of the value.
- **Document expiry management pattern** (validated against our own
  PRD requirement): leading tools treat licensing/insurance/permit
  expiry as a first-class compliance object with proactive alerts, not
  just a field on the vehicle record — worth matching this pattern
  exactly rather than a simple "expiry date" column.
- **GPS/telematics hardware (Samsara, Geotab) is explicitly out of
  scope** — that requires purchasing and installing hardware trackers
  per vehicle, a cost and complexity ArkWorkers' PRD never asked for.
  Confirmed: our fleet requirements are fuel logs, mileage, service
  schedule, and document expiry only — no live tracking.

### Relevance to ArkWorkers
Our vehicle model (already in `PRD.md` §5 and `ARCHITECTURE.md` §3) is
correctly scoped against this research — we're building the
"Fleetio-lite" slice of the category (documents, fuel, mileage,
service), not the "Samsara" slice (live GPS, driver-behavior AI). No
scope changes needed here, just validation that we picked the right
subset.

---

## 3. Church-Specific Facility Management Software

**Category leaders examined:** Planning Center (Facilities/Rooms
module), ChurchTrac, Breeze ChMS, Church Community Builder, Rock RMS.

### Critical finding: this is a real market gap
Every church-specific tool researched is built around **room/resource
scheduling and booking** — avoiding double-bookings for events, setup/
teardown time blocks, calendar integration with services and ministry
events. This is a genuinely different problem from ArkWorkers':

| Church software solves | ArkWorkers solves |
|---|---|
| "Is the fellowship hall free at 3pm Saturday?" | "Was the fellowship hall floor actually mopped this morning?" |
| Room booking conflicts | Asset upkeep and task completion |
| Volunteer/event coordination | Staff task assignment and proof of work |
| Calendar-centric | Space/Asset/Routine-centric |

Some (Planning Center, ChurchTrac) claim "maintenance tracking" as a
feature, but based on the research, this appears to be basic task
lists bolted onto a scheduling-first product — not a purpose-built
asset/routine/proof-of-completion system with role-based restricted-zone
access control like ArkWorkers is designing.

### Relevance to ArkWorkers
**This is validating, not just informational.** ArkWorkers isn't
competing with Planning Center or ChurchTrac — it's solving a problem
they don't really address. No church-specific tool in this research
has: a modular Asset Type system, space-level restricted-zone access
control, or a photo/video proof-of-completion requirement tied to
task routines. This confirms the product's reason to exist rather than
suggesting features to copy.

**One thing worth borrowing regardless:** these tools are explicitly
designed around "ministry rhythms — recurring events, volunteer-led
setups, shared multipurpose spaces, weekend prep." ArkWorkers' Saturday-
clean vs. daily-Quarters-clean distinction (already in `PRD.md`) is
exactly this kind of rhythm-awareness — good sign we're already
thinking about it correctly, worth being deliberate about preserving
as a first-class scheduling concept rather than a one-off.

---

## 4. Mobile Field Service / Offline Task Completion Apps

**Category examined:** Arrivy, Oxmaint mobile, generic FSM apps,
SaberTask (cleaning/facility-specific time+task tracking).

### Confirmed best practices (directly validates SECURITY.md and
DESIGN-SYSTEM.md decisions already made)
- **Offline-first is treated as mandatory, not a nice-to-have** across
  every serious tool in this category: "core field actions — form
  completion, notes, photo capture — continue offline, then sync once
  connectivity returns." This matches our `ARCHITECTURE.md` §5 design
  exactly.
- **Conflict resolution on sync is an explicit, named feature** in the
  better tools ("conflict resolution rules let you choose whether local
  changes or server updates take precedence") — validates that our
  first-sync-wins-with-logged-discard approach (`SECURITY.md` §6.4) is
  solving a real, known problem, not a hypothetical one.
- **Photo/voice proof tied directly to task completion** is described
  as essential for compliance and dispute prevention — one tool's
  marketing copy is blunt about the failure mode: "no photo evidence of
  completed repairs → regulatory fine or insurance claim denied." This
  strongly validates the PRD's proof-of-completion requirement as
  correctly prioritized, not over-engineered.
- **High-contrast UI for outdoor visibility, designed for gloved hands**
  is explicitly named as a design requirement by multiple sources — this
  directly validates the accessibility requirements already locked into
  `DESIGN-SYSTEM.md` §2.5.
- **SaberTask specifically** (cleaning/facility-focused) combines GPS
  clock-in/out, photo documentation, and task lists — the closest
  single comparable to ArkWorkers' actual use case found in this
  research, though still generic multi-industry rather than
  church-specific.

### Relevance to ArkWorkers
This category validates more than it changes. The three hardest
technical pieces we already designed for — offline-first sync,
conflict resolution, and resumable proof uploads — are exactly what
the best tools in this adjacent category treat as core, non-negotiable
infrastructure. Nothing here suggests we're missing a fundamental
capability; it confirms we correctly identified the hard parts.

---

## 5. Preventive Maintenance Scheduling — Trigger Types

Research into PM scheduling theory across CMMS sources converged on
**three trigger types**, and this directly affects how `Routine`
scheduling should be modeled in `ARCHITECTURE.md`:

1. **Time-based (calendar)** — fires on a fixed interval (every 30/90/
   365 days). Best for: HVAC filters, general cleaning, most of
   ArkWorkers' current scope.
2. **Usage/meter-based** — fires when a measured value crosses a
   threshold (runtime hours, mileage, cycle count). Best for: the
   generator (runtime hours — already in `PRD.md` §4.1), vehicles
   (mileage-based service — already in `PRD.md` §5).
3. **Condition-based** — fires when a sensor reading crosses a
   threshold (vibration, temperature). **Out of scope** — requires
   IoT sensors ArkWorkers doesn't have and the PRD never requested.

**Important pattern found and worth adopting:** best-practice CMMS
platforms support **hybrid triggers** — a single asset can have both a
calendar interval AND a meter threshold, firing on whichever comes
first, then resetting both counters from the completion point. This is
a **direct, concrete improvement to propose for `ARCHITECTURE.md`'s
Routine model** — currently specified as "schedule (cron-like or
trigger-based)" without this hybrid-reset detail. Recommend updating
the `routines` table design to explicitly support both a
`calendar_interval` and a `meter_threshold` field simultaneously, with
whichever fires first triggering the task and resetting both.

**Also relevant:** research flagged a common failure mode — "when a PM
is missed with no system consequence (no alert, no escalation), it
simply disappears from the queue." This validates the PRD's daily
summary report and overdue-status design as solving a real, named
industry problem, not a hypothetical one.

---

## 6. Consolidated Feature List — What ArkWorkers Needs

Organized by priority tier based on this research, cross-referenced
against what's already in `PRD.md`/`ARCHITECTURE.md`.

### Tier 1 — Core / MVP (validated as essential by every category researched)
- [x] Space → Asset → Routine → Task data model *(already designed)*
- [x] Role-based + space-restricted authorization *(already designed)*
- [x] Photo/video proof of task completion *(already designed)*
- [x] Offline-first task completion with sync + conflict resolution
      *(already designed)*
- [x] Daily summary reporting with overdue visibility *(already designed)*
- [x] Vehicle fuel/mileage/document-expiry tracking *(already designed)*
- [ ] **Hybrid PM triggers (calendar + meter, whichever fires first)**
      — new recommendation from this research, not yet in
      `ARCHITECTURE.md`
- [ ] **Overdue-task escalation** (don't let a missed PM silently vanish
      from the queue — surface it with increasing visibility) — new
      recommendation, partially covered by "Overdue" status but worth
      an explicit escalation rule (e.g. notify Facility Manager after
      24 hours overdue, not just show a red badge)

### Tier 2 — Strong value, not yet in PRD, worth considering for a
near-term phase after MVP
- [ ] **In-app messaging tied to a task** (MaintainX's standout
      differentiator) — e.g. a cleaning staff member flagging "the
      mop bucket is broken" directly on a task, visible to the Facility
      Manager, rather than a separate communication channel
- [ ] **Asset history view** — a simple timeline per asset showing every
      past routine/task completion, not just the "last serviced" date
      already planned — useful for spotting patterns (e.g. an AC unit
      that keeps needing filter changes early)
- [ ] **PM compliance rate as a tracked KPI** (industry target: 90%+) —
      a specific number to put on the admin daily/weekly report beyond
      raw completion counts

### Tier 3 — Explicitly out of scope (confirmed by research, not
recommended even later)
- IoT sensor integration / condition-based maintenance triggers
- GPS/live vehicle tracking and driver-behavior scoring
- Multi-property/multi-tenant portfolio management
- Parts inventory procurement and purchase-order workflows
- Customer-facing scheduling/invoicing (this is an internal staff tool,
  not a service business)

---

## 7. Recommended Build Plan

Based on this research plus the existing documentation, here's the
phasing recommendation:

### Phase 1 — Foundation (backend + auth, no UI polish yet)
- Laravel scaffold: migrations for Spaces, Asset Types, Assets,
  Routines, Tasks, Users, Vehicles
- Authorization Policies (the space-restriction model) — build and
  **test this first and most thoroughly**, before any other feature,
  since every other feature depends on it being correct
- Basic auth (PIN/OTP per `SECURITY.md` §3.1)

### Phase 2 — Core Task Loop (the actual daily-use MVP)
- Task list view, task detail, task completion
- Photo/video proof upload (non-resumable version first — get the happy
  path working before adding resumability)
- Basic Space/Asset CRUD for Admin/Facility Manager

### Phase 3 — Reliability Layer (what makes it usable in the real
conditions the research confirmed matter most)
- Offline-first caching and sync
- Resumable/chunked upload (upgrade Phase 2's upload)
- Conflict resolution logic
- **This phase is the one the research most strongly says not to skip
  or rush** — every serious competitor treats offline-reliability as
  core infrastructure, not a later add-on

### Phase 4 — Scheduling & Reporting
- PM routine scheduling with hybrid triggers (calendar + meter)
- Daily summary report
- Vehicle fuel/mileage/document-expiry tracking + alerts
- Overdue-task escalation

### Phase 5 — Polish & Secondary Features
- In-app task-level messaging (Tier 2 recommendation)
- Asset history timeline (Tier 2 recommendation)
- PM compliance rate KPI on reports

### Phase 6 — Platform Expansion
- Android packaging via Capacitor
- iOS packaging via Capacitor (per prior discussion — low-effort if
  Phase 1–5 followed the cross-platform discipline in `ARCHITECTURE.md`)

**Why this order:** authorization first because everything else is
worthless if it's wrong; the core task loop before reliability because
you need something to make reliable; reliability before scheduling
because a beautifully-scheduled task that fails to save when the wifi
drops helps nobody; polish last because it's genuinely optional for
launch, unlike everything before it.

---

## 8. Cross-Reference

Read alongside:
- `PRD.md` — confirms this research validates rather than contradicts
  the original requirements gathering
- `ARCHITECTURE.md` — the hybrid-trigger recommendation (Section 5,
  6) should be folded in as a specific update
- `SECURITY.md` — offline-sync and conflict-resolution research
  (Section 4) validates the existing design
