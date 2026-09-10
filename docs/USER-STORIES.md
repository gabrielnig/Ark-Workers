# ArkWorkers.app: User Stories

**Status:** v1, written 2026-09-10 from `PRD.md`, `SECURITY.md` §4,
`ARCHITECTURE.md`, and the actual current build state in
`BUILD-PLAN.md`. Every story is tagged with its real status:

- **[Built]**: live in the app today, you can do this right now.
- **[Partial]**: some of the story works, the rest is called out
  explicitly in the story itself.
- **[Not built]**: described for completeness and future build
  reference, does not work yet. Included because "detailed and
  comprehensive" means covering the full intended system, not just
  what happens to exist so far, but every one of these is marked so
  this document is never mistaken for a description of the live app.

**A note on user types, reconciling PRD.md §6 with the actual model:**
`PRD.md` originally listed fixed roles (Admin/Pastor, Facility
Manager, Cleaning Staff, Maintenance/Technician, Security, Drivers).
That fixed list was **superseded** during security design
(`SECURITY.md` §4.1, `ai-context.md` §2) by a more flexible model:
**Departments and Roles are both admin-manageable lists, not a fixed
enum.** "Cleaning Staff" isn't a role in the system, it's a worker who
belongs to the "Cleaning" department with the "Member" role.
"Facility Manager" isn't a role either, it's any role in any
department that an admin has flagged `grants_management: true`
(the seeded roles are Member, Supervisor, Coordinator, with Supervisor
and Coordinator granting management by default). This document is
organized around that real model, not the PRD's original language,
with a mapping table below so nothing from the PRD gets lost in the
translation.

| PRD.md §6 language | Actual model |
|---|---|
| Admin | The global `is_admin` flag. Unrelated to department membership. |
| Pastor | A plain title with zero permission weight (`SECURITY.md` §4.1). Does not grant any access by itself. See the dedicated section near the end of this document. |
| Facility Manager | Any user with `hasManagementPermission()` true: `is_admin`, or a department role flagged `grants_management` (Supervisor/Coordinator by default, but this is admin-configurable, not hardcoded). |
| Cleaning Staff / Maintenance / Technician | A worker in the relevant department (Cleaning, Maintenance) with the Member role. Same base permission tier as any other Member, department membership only affects which tasks get assigned to them, not what screens they can see. |
| Security | A worker in the Security department. No security-specific screens exist yet (not in scope of what's built or currently planned beyond general task assignment). |
| Drivers | A worker in the Driver department. Vehicle-specific access rules apply once Vehicles is built, see that section. |

---

## 1. User tiers, at a glance

| Tier | Defined by | Can do |
|---|---|---|
| **Prospective Worker** | Not yet an account holder | Submit a sign-up request only |
| **Staff Member** | Any authenticated user, baseline | See and complete their own assigned tasks, browse non-restricted Spaces/Assets |
| **Manager** | `hasManagementPermission()` true (Admin OR a `grants_management` department role) | Everything a Staff Member can, plus create/manage Spaces, Assets, Asset Types, Routines |
| **Admin** | Global `is_admin` flag | Everything a Manager can, plus approve account requests, manage restricted-space access grants, and (once built) manage Departments/Roles/Staff |
| **Pastor** | A title, not a permission tier | Exactly whatever their actual tier grants them. See §7. |

Every permission check is enforced server-side (Laravel Policies).
Anything described below as visible or hidden in the UI is a
convenience, not the actual security boundary. The API rejects the
same request regardless of what the screen shows.

---

## 2. Prospective Worker: before an account exists

### 2.1 Request an account **[Built]**
As someone who wants to start using ArkWorkers, I want to submit a
sign-up request with my name, email, phone, and which department(s)
I belong to, so that an admin can review and approve me without me
needing a password yet.

**How it works today:** the Sign Up screen (`/signup`) collects full
name (with a baptismal-name placeholder), email, phone, and a
multi-select checklist of departments (a worker can belong to more
than one, e.g. both Cleaning and Ushering). No password is set at
this stage. Submitting creates an `account_request` row, visible to
admins on the Pending Requests screen. There is no self-service
password or instant login, this is by design, matching
`ways-of-working`'s "admin-approved only" decision.

### 2.2 Get notified and activate the account **[Built]**
As a prospective worker whose request was approved, I want to receive
an email with a one-time link, so that I can set my own password and
start using the app.

**How it works today:** on approval, an invite (`invites` table, a
single-use token) is emailed. Following the link (`InviteController`)
lets the person set a password and activates their `users` row.
Rejected requests do not receive this email.

---

## 3. Staff Member: the baseline tier

Everything here applies to every authenticated user, regardless of
department, since department membership doesn't change base
permissions, only which tasks get assigned.

### 3.1 See my own tasks for today **[Built]**
As a staff member, I want to open the app and immediately see what's
due today, so that I don't have to dig through a menu to find my work.

**How it works today:** `/my-work` is the default landing screen after
login. It shows only tasks assigned to the logged-in user (never
another worker's tasks, `SpacePolicy`/`TaskPolicy` scope this
server-side, nothing extra is ever synced to the device to begin
with). Filter pills switch between Today, Upcoming, Overdue, and
Completed. Each task card shows a border-left status stripe and a
paired icon+color badge (never color alone, for outdoor-glare
legibility): moss for on-track, clay for pending, rust for overdue,
plum for a restricted-space task.

### 3.2 Know when I'm looking at stale data **[Built]**
As a staff member working in a building with patchy connectivity, I
want a clear online/offline indicator, so that I know whether what
I'm looking at is current.

**How it works today:** a small "Connected, all changes synced"
status line sits under the page title, backed by a real online-status
hook, not a decorative dot.

### 3.3 Complete a task and attach proof **[Built]**
As a staff member, I want to mark a task complete and attach a photo
(or video), so that there's verifiable proof the work was actually
done, not just a checkbox someone could tick without doing it.

**How it works today:** tapping into a task opens the Task Detail
screen. Upload is chunked and resumable (Uppy-pattern, per
`ARCHITECTURE.md` §6) so a dropped connection mid-upload doesn't mean
starting over. The backend validates the actual file signature, not
just the extension, and strips EXIF/geolocation on ingestion.
Completing a task records who actually completed it (not just who it
was assigned to) and when. This distinction is deliberate and
covered by its own backend test, since a task can be completed by
someone other than the original assignee in some workflows.

### 3.4 Keep working when the connection drops **[Built]**
As a staff member cleaning a space with poor signal, I want to
complete a task offline and have it sync automatically once I'm back
online, so that a dead zone in the building doesn't block my work.

**How it works today:** an offline-completed task shows a
"pending sync" badge. On reconnect, the app re-validates the
task/routine still exists and the user's access is still valid before
accepting the queued completion (`ARCHITECTURE.md` §5). If two
completions genuinely conflict (e.g. synced from two devices), it's
first-sync-wins with the discarded duplicate logged, not silently
dropped, backed by a dedicated backend test.

### 3.5 Not lose my history when a routine changes **[Built]**
As a staff member who completed a task months ago, I want that record
to stay in my history even if the routine it belonged to is later
deleted, so that my work is never erased retroactively.

**How it works today:** deleting a Routine soft-deletes it, never a
hard delete, specifically so `Task`/`task_proofs` history stays
permanently intact and the routine's name still resolves when viewing
old task history. This was a deliberate decision made and fixed this
session, distinct from how a decommissioned Asset works (see §4.6).

### 3.6 Browse the building's Spaces and Assets **[Built]**
As a staff member, I want to look through the church's Spaces (rooms,
zones) and see what Assets are in each one, so that I understand the
physical layout I'm working in, not just my own task list.

**How it works today:** the Spaces screen shows a top-level grid of
Spaces, each with a "N sub-spaces · N assets" count so you know
there's something inside before clicking in. Drilling into a Space
shows a breadcrumb (not a stack of modals) and its direct sub-spaces
and Assets. Every Space and Asset card shows a real photo thumbnail
(not a generic icon), matched by Asset Type category where possible.

### 3.7 Never see a restricted Space I don't have access to **[Built]**
As a staff member without access to the Prophet's Quarters, I want
that Space to not appear in my Spaces list at all, not just be blocked
if I try to open it, so that its existence isn't even revealed to me.

**How it works today:** `SpacePolicy` excludes a restricted Space
from the list response entirely for a user without a grant, per
`SECURITY.md` §4.2's explicit requirement that a restriction not just
hide detail but hide existence.

### 3.8 See restricted-space tasks clearly marked, if I have access **[Built]**
As a staff member who *does* have an access grant to a restricted
Space (e.g. the one person who cleans the Quarters kitchen daily), I
want restricted tasks visibly flagged wherever they appear, so that I
always know I'm handling something sensitive.

**How it works today:** the plum badge (🔒 Restricted) appears on the
task card in My Work, and at every level of the Space drill-down, not
just on first entry.

### 3.9 Filter/search restricted content out of general views **[Built]**
As a staff member, I don't want restricted-space data to leak into a
dashboard, report, or search result I have general access to.

**How it works today:** the same policy-level exclusion in §3.7
applies everywhere, since it's enforced at the query layer, not
per-screen. (Reports don't exist yet, so this is currently proven only
for Spaces/Assets/Tasks lists, but the mechanism is the same one
Reports will use once built.)

### 3.10 See a task's detail without editing anything I shouldn't **[Built]**
As a staff member, I want to view a task's full detail (what it's for,
which Asset/Space it belongs to, due date, any prior notes) without
having any ability to edit the Space or Asset itself, so the system
guides me toward what I'm actually allowed to do.

**How it works today:** Task Detail is read-only for everything except
the completion action itself and proof upload. There is no edit
affordance for the underlying Asset or Space on this screen for a
Staff Member, matching `TaskPolicy`.

### 3.11 Understand my vehicle logs, if I'm a driver **[Not built]**
As a driver, I want to log fuel, mileage, and see the vehicle's
service/document status, so that I don't need a separate paper log.

**Not built yet.** `VehicleController` and `vehicle_logs` don't exist.
Once built, `SECURITY.md` §4.4 already specifies the access rule: a
vehicle's documents and logs are visible to Admin, a Manager, and that
vehicle's assigned driver only, not the full staff list.

### 3.12 See a Daily Summary of how the day went **[Not built]**
As a staff member (or anyone), I want a simple daily view of
completion rates and overdue items, so that facility health is
visible at a glance.

**Not built yet.** No backend job or endpoint exists. Once built, the
data-viz color palette (`DESIGN-SYSTEM.md` §2.6) is specifically ready
for this, and restricted-space data will be excluded per §3.9's rule.

---

## 4. Manager: any user with `hasManagementPermission()`

Everything in §3 applies here too, all of this is in addition to it.

### 4.1 Create a new Space **[Built]**
As a manager, I want to add a new Space (a room, a zone, a sub-area of
an existing Space), so that the facility's physical structure in the
app matches reality without needing a developer.

**How it works today:** the "+ Add a Space" button on the Spaces
screen (visible only to a user whose `can_manage` is true, checked
against the real server-side permission, not just `is_admin`) opens
an inline form: name, and a "Restricted" checkbox. The parent is
implied by wherever you currently are browsing, top-level if you're at
the root, a sub-space of whatever you've drilled into otherwise, no
separate picker needed since the context already determines it.

### 4.2 Add a new Asset to a Space **[Built]**
As a manager, I want to add a physical item (an AC unit, a mixing
console, a piece of furniture) to a specific Space, so that it can
have routines and task history tracked against it.

**How it works today:** the "+ Add an Asset" button appears once
you're inside a specific Space (an Asset always requires a real
`space_id`, there's no "unassigned" bucket, so this doesn't make sense
at the top level and isn't shown there). The name field is a combobox
suggesting every existing Asset name already in the system (so a
repeated name like "AC Unit - Wall Mount" doesn't need retyping every
time) while still allowing something genuinely new. The Asset Type is
a real dropdown of existing types.

### 4.3 Create a brand-new Asset Type with no developer involved **[Built]**
As a manager, I want to define a new kind of Asset (e.g. "Generator,"
"Projector," "Piano") on the fly, so that the system stays genuinely
modular the way the PRD requires, not limited to a fixed hardcoded
list.

**How it works today:** two ways to do this, deliberately overlapping.
The fast path is inline: picking "+ New asset type..." from the Asset
Type dropdown on the Add Asset form reveals a small panel right there
(name + a curated category dropdown, or "Other" with free text), no
navigating away, and the new type auto-selects itself once created.
The full path is `/admin/asset-types`, a dedicated list-and-create
screen, linked from the Add Asset form ("Manage all asset types") for
anyone who wants the fuller view. Both call the same
`AssetTypeController::store()`. Category is used for photo matching
(§3.6). A category outside the curated list still works correctly,
it just falls back to a default photo rather than breaking.

### 4.4 Create Routines for recurring work **[Not built, frontend only]**
As a manager, I want to define a recurring routine (e.g. "Clean the
AC filter every 30 days," or "Service the generator every 200 running
hours") on an Asset or Asset Type, so that tasks generate on schedule
automatically instead of someone remembering to create each one by
hand.

**Backend is built** (`RoutineController`, full CRUD, hybrid
calendar/meter triggers, 130+ backend tests). **No frontend exists
yet**: tasks are still being created directly, bypassing the routine
schedule model entirely. This is the single biggest gap between what
the backend supports and what a manager can actually do through the
app today.

### 4.5 Assign a task directly, outside the routine schedule **[Partial]**
As a manager, I want to hand a one-off task to a specific staff
member (something outside the normal recurring schedule), so that
ad-hoc work still gets tracked with the same proof/completion
discipline as routine work.

**Task creation exists at the API level** (`POST /tasks`), but there
is no manager-facing screen to do this through the app yet, it's the
same gap as §4.4, since without a Routines UI, direct task assignment
is currently the only way tasks get created at all, and even that has
no dedicated UI.

### 4.6 Decommission an Asset without losing its history **[Built, backend]**
As a manager, I want to remove an Asset that's no longer in service
(replaced, disposed of) without permanently erasing its maintenance
history the moment I do, so that past records stay auditable.

**How it works today (backend only, no delete button in the UI yet):**
`AssetController::destroy()` soft-deletes the Asset with a 30-day
grace period before a scheduled job permanently prunes it. This is
deliberately different from how Routine deletion works (§3.5, which
is soft-delete forever, never pruned). Asset decommissioning is meant
to eventually clear out, Routine history is meant to survive
permanently. **A related, unresolved risk:** if a decommissioned
Asset is later pruned, its Routines still cascade-delete with it,
which cascades again to Tasks, quietly reintroducing the exact
history-loss problem already fixed for direct routine deletion, just
through the asset-pruning path instead. Flagged in `BUILD-PLAN.md`,
not yet decided or fixed.

### 4.7 See who's assigned to what, across the team **[Not built]**
As a manager, I want a staff directory showing who belongs to which
department and what role they hold there, so that I can see coverage
without querying the database directly.

**Not built.** Departments/roles are currently seed data only, no
admin UI exists to view or edit them.

### 4.8 Manage vehicle fleet records **[Not built]**
As a manager, I want to see all vehicles, their assigned drivers, and
upcoming document expiries in one place, so that I catch a lapsed
insurance or roadworthiness certificate before it becomes a problem.

**Not built.** `vehicles` table exists, no controller, no logs, no
expiry-alerting job, no screen.

---

## 5. Admin: the global `is_admin` flag

Everything in §3 and §4 applies here too.

### 5.1 Review and approve/reject a sign-up request **[Built]**
As an admin, I want to see every pending account request with the
person's stated departments, and approve or reject each one, so that
account creation stays gated and I control who gets in.

**How it works today:** `/admin/requests` shows a stat row (awaiting
review / active workers / rejected this month), a searchable/filterable
table, and per-row Approve/Reject actions. Approving triggers the
invite email (§2.2). Loading uses skeleton rows matching the real row
geometry, not a spinner, and the empty state offers "Clear filters" as
a real action rather than a dead sentence when a search/filter
combination matches nothing.

### 5.2 Grant a specific person access to a restricted Space **[Built]**
As an admin, I want to grant one named person (e.g. the specific
cleaner who services the Prophet's Quarters kitchen) access to a
restricted Space, without promoting them to Admin or exposing the
Space to anyone else, so that the "dedicated staff, not the general
crew" pattern from the PRD is actually usable.

**How it works today:** viewing a restricted Space as an admin shows
an "Access Grants" panel. Searching by name or email (a minimal,
admin-only user lookup, not the full staff directory from §4.7, which
still doesn't exist) surfaces matching people with a "Grant access"
button next to each, disabled if they already have one. This closes
what was, until now, the single biggest practical gap in the whole
restricted-space feature: a restricted Space was previously
unreachable by anyone except an Admin, permanently, since there was no
way to create the exception.

### 5.3 Revoke a restricted-space access grant **[Built]**
As an admin, I want to remove someone's access to a restricted Space
(they changed roles, left that duty, etc.), so that access stays
current, not permanent by accident.

**How it works today:** the same Access Grants panel lists everyone
currently granted access, who granted it, and a Revoke button per row.
Revoking immediately removes that person's ability to see the Space,
enforced server-side (`SpacePolicy`), not just hidden from the list.

### 5.4 Create and manage Departments **[Not built]**
As an admin, I want to add, rename, or remove a Department (e.g. add
"Media Team" as a new department), so that the org structure can
evolve without a developer.

**Not built.** Departments are admin-manageable *at the data layer*
(a real, intentional design decision, `SECURITY.md` §4.1), but no UI
exists yet to actually do it.

### 5.5 Create and manage Roles, including which ones grant management **[Not built]**
As an admin, I want to define a new Role (or edit an existing one) and
toggle whether it grants management permission, so that the
permission model stays flexible the way it was designed to be.

**Not built.** Same situation as §5.4: the flag exists
(`roles.grants_management`), the UI to toggle it per-role doesn't.

### 5.6 Full asset-type management, not just create **[Partial]**
As an admin, I want to edit or retire an existing Asset Type, not just
create new ones, so that mistakes or obsolete types don't linger
forever.

**Create and list are built** (§4.3). `AssetTypeController::update()`
already exists on the backend. **No edit UI exists** on
`/admin/asset-types` yet, only create.

---

## 6. Cross-cutting: what nobody can do yet

These aren't tied to a specific tier, they're just not built for
anyone:

- **Messages**: a nav placeholder with no defined scope at all. Not
  in `PRD.md`. Before this gets built, it needs an actual requirements
  conversation (what kind of messaging, between whom, why), not an
  assumption based on an empty sidebar slot (`BUILD-PLAN.md` Phase 6).
- **Home**: nav placeholder, no defined purpose distinct from My Work
  yet.
- **Profile**: nav placeholder, no screen (viewing/editing your own
  name, phone, password).
- **Asset detail screen**: clicking an Asset row anywhere currently
  does nothing, deliberately (not a dead link). Asset editing,
  decommissioning through the UI, and the Asset → Routine/Task
  drill-down all depend on this screen existing.

---

## 7. A note on "Pastor"

Per `SECURITY.md` §4.1, **Pastor is a plain title with zero permission
weight.** It is not a role, not a department, and does not bypass any
check. This is worth stating explicitly because `PRD.md` §6 originally
grouped "Admin / Pastor" together, which could easily be
misread as "being the Pastor grants admin access." It does not. If the
person holding the title of Pastor needs Admin access, or management
permission in a department, that's granted the same way anyone else's
would be, explicitly, through the actual permission system, not
inferred from the title. A future Staff/Role admin screen (§4.7, §5.5)
could reasonably include a plain "title" text field for display
purposes (so "Pastor," "Secretary," etc. can show next to a name),
but that's a display label, never a permission source, and isn't built
yet.
