# ArkWorkers.app — Product Requirements Document (PRD)

**Church:** King's Palace & Ark of Jesus Ministries International
**Domain:** arkworkers.app
**Repo:** github.com/gabrielnig/Ark-Workers
**Status:** Draft v1 — from full requirements brainstorm (facility, assets, vehicles)

---

## 1. Purpose

ArkWorkers.app is a mobile-first PWA + Android app for managing the church's
buildings, assets, vehicles, and the people who clean/maintain them —
scheduling routines, logging completion (with photo/video proof), tracking
maintenance and expiries, and reporting daily on facility health.

The system must be **fully modular**: new Spaces, Asset Types, Asset
instances, and Routines can be added through the app itself, without code
changes.

---

## 2. Core Data Model (confirmed)

- **Space** — a physical zone (Sanctuary, Gallery, Children's Church, Office
  #, Kitchen, Restroom Block, Store Room, Prophet's Quarters room, etc.).
  Spaces can be added freely.
- **Asset Type** — a reusable definition (e.g. "AC Unit", "Drum Kit",
  "Projector", "Generator", "Vehicle"). Each Asset Type carries its own
  routine templates (cleaning routine, maintenance routine, checklist,
  trigger — time-based, usage-based, or shift-based).
- **Asset** — an individual instance of an Asset Type, assigned to a Space
  (e.g. "AC Unit — Sanctuary #3").
- **Routine** — a recurring task tied to an Asset Type or Asset (cleaning,
  inspection, servicing), with a schedule and required proof.
- **Task/Log** — an individual occurrence of a routine, completed by a staff
  member, with photo/video proof attached.

This model must support one-item-per-type granularity (e.g. Drum Kit,
Keyboard, Guitar, Speaker, Mixer, Projector, AC Unit, Fan are each their own
Asset Type) as confirmed during brainstorm.

---

## 3. Spaces & Facility Inventory

### 3.1 Main Sanctuary
- Single main sanctuary, under 300 seating capacity, movable chairs
- Tiled flooring
- Aluminum roofing (cleaning item)
- Rails; altar/pulpit furniture; banners/decor
- Fans + ~6 standalone AC units (with external condenser units) — dedicated
  to this space
- Full band setup: drums, keyboard, guitars, and other instruments
  (each its own Asset Type)
- Full PA system: mixer, speakers, stage lighting, projectors/screens

### 3.2 Gallery (separate Space)
- Distinct zone from the sanctuary floor
- Houses media team, sound system, and choir

### 3.3 Children's Church
- One room
- AV setup: TV/monitor + streaming/AV receiver box + speakers (live feed
  of main service)
- Furniture/supplies: cots, tables, chairs, toys, learning aids
- Dedicated AC/fans for this space
- Water dispenser present at this location

### 3.4 Admin Offices
- 3–5 office rooms (Pastor's office, secretary, finance, etc.)
- Standard setup per office: desks, computers, printers, dedicated AC
- Dedicated server/network room or IT closet (tracked separately)

### 3.5 Kitchen (Prophet's Quarters)
- No member-facing kitchen; the only kitchen is within the Prophet's
  Quarters (residential space on the property)
- Appliances: gas cookers, fridges/freezers, microwaves
- Gas cylinder(s) — requires refill-date tracking
- Used and cleaned **daily** by dedicated staff (separate from the general
  weekly Saturday church-wide clean)

### 3.6 Prophet's Quarters
- Modeled as its own multi-room Space, but only a few rooms (not a full
  building breakdown)
- **Requires restricted/private access controls** — different visibility
  rules from the rest of the facility (see Section 6, Roles & Permissions)

### 3.7 Restrooms
- Multiple restroom blocks across the property (e.g. near sanctuary, near
  children's church)
- Each block is its own Space with its own cleaning routine

### 3.8 Storage
- Store room(s) for extra chairs, decor, cleaning supplies, and event
  equipment — its own Space

### 3.9 Water Dispensers
- Standalone assets placed at multiple points: children's church, reception
  area near the auditorium, and possibly others — each tracked individually

### 3.10 Grounds / Exterior
- Parking lot
- Gate / gatehouse / security post
- Perimeter fence/wall
- Gardens/lawn
- Borehole / water pump
- Overhead water tank(s)
- Street/flood lighting
- CCTV / security camera system

---

## 4. Building Systems

### 4.1 Generators
- 2 generators, different capacities serving different zones (e.g. one for
  sanctuary, one for the Quarters)
- Tracking required: diesel refill log, service interval schedule, running
  hours per shift
- Backup power: ATS (automatic transfer switch) and/or inverter/solar backup
  system, tracked alongside generators

### 4.2 Air Conditioning (property-wide)
- All split/standalone units (no central/ducted system)
- Routine tracking: filter cleaning, refrigerant/gas checks, general
  servicing schedule
- Applies to sanctuary (~6 units), children's church, offices, Quarters

### 4.3 Electrical
- Distribution boards/panels, transformer (if present), meter(s) — tracked
  as assets with inspection routines
- Wiring/circuit faults (tripped breakers, wiring issues) logged through the
  same maintenance-request flow as other asset issues

---

## 5. Vehicle Fleet

- 6+ vehicles total
- Mixed fleet, including the Prophet's personal/official vehicle(s)
- Each vehicle assigned to a specific driver/staff member
- Tracking required per vehicle:
  - Fuel logs
  - Mileage
  - Service/maintenance schedule
  - Document expiry: insurance, roadworthiness, license renewal, vehicle
    particulars/registration papers

---

## 6. Roles & Permissions

Distinct access levels required for:
- Admin / Pastor
- Facility Manager
- Cleaning Staff
- Maintenance / Technician
- Security
- Drivers

**Special rule:** Prophet's Quarters data (spaces, assets, routines) must be
restricted — not visible/manageable by all roles by default. Permission
model needs a space-level (or zone-level) privacy flag, not just a global
role system.

---

## 7. Task Completion & Reporting

- **Proof of completion:** Photo/video proof is required for completed
  cleaning and maintenance tasks (applies broadly; may allow per-routine
  override later if some tasks don't need it).
- **Reporting cadence:** Daily summary reports covering completion rates,
  overdue tasks, and asset issues.
- **Cleaning cadence note:** General facility clean is weekly (Saturdays);
  Prophet's Quarters kitchen is cleaned daily by dedicated staff — the
  system must support routines at different frequencies per Space/Asset,
  not a single global schedule.

---

## 8. Open Items / To Confirm Later

- Exact list of asset types to seed at launch vs. added ad-hoc
- Whether photo/video proof requirement can be toggled per-routine
- Full document list for vehicles beyond roadworthiness/insurance/
  license/particulars (if any additional papers apply)
- Security/compliance requirements (next phase, per project roadmap)
- UI design system and direction (after security/compliance docs)

---

## 9. Next Steps (per agreed roadmap)

1. ✅ Requirements brainstorm (this document)
2. Security & compliance documentation
3. Finalize UI design system and direction
4. Begin implementation
