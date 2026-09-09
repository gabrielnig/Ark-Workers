# ArkWorkers.app — User Guide

**Status:** stub — full guide written once the real screens are built
and can be screenshotted (mockups are approved, the actual React
screens aren't built yet, see `ai-context.md`). Structure and content
outline locked now, and kept in sync with the actual auth/org-structure
model, so nothing gets forgotten and nothing here describes a flow
that's since changed.

---

## Structure (per department/role, replacing the earlier fixed-role
outline now that departments and roles are both admin-configurable,
see `ARCHITECTURE.md` §4 and `ai-context.md` §2)

### Getting an account (everyone, before any of the below applies)
- How to submit a sign-up request (name, email, phone, which
  department(s) you're joining)
- What happens after you submit: an admin reviews it, you'll get an
  email once approved
- How to use the invite link: it's single-use and expires after 7
  days, set your password there to activate your account
- How to sign in afterward

### For ordinary department members (Cleaning, Maintenance, Security,
Driver, and similar)
- How to find your assigned tasks for the day
- How to complete a task and upload photo/video proof
- What to do if the upload fails or your connection drops mid-upload
  (should be genuinely simple — this is the resumable-upload flow from
  `DESIGN-SYSTEM.md` §6.1, but the guide needs to explain it in plain
  language for non-technical users, not yet built, Phase 3)
- What a "restricted zone" badge means and what to do if you need
  access to one you don't currently have
- What it means to belong to more than one department, and to have a
  different standing (Member, Supervisor, Coordinator, etc.) in each

### For anyone with a management-granting role in a department
(replaces the old fixed "Facility Manager" role, this is now
per-department and admin-configurable, see `ai-context.md` §2)
- Everything above, plus:
- How to create/edit Spaces, Asset Types, and Assets
- How to set up a new Routine and its schedule
- How to grant space access to a specific staff member (the Prophet's
  Quarters access-grant flow)
- How to view and act on the daily summary report

### For Admin
- Everything above, plus:
- Full system oversight, all departments' data
- Reviewing and approving/rejecting sign-up requests
- Creating/editing the master list of departments and roles, and which
  roles are available in which department
- Vehicle document expiry management

### For Drivers specifically
- Vehicle fuel/mileage logging
- Vehicle document status (what's expiring soon)

## Format

Once built: short, task-oriented sections with screenshots — "How do I
...?" headings, not a feature-by-feature dump. Written at the reading
level and technical comfort of the actual staff base (per the "warm,
non-technical" direction in `DESIGN-SYSTEM.md` §1).

## Delivery Format (decide before writing)

```
[ ] In-app help screens vs. a separate document/PDF vs. both
[ ] Language: English only, or does any staff member need another
    language supported?
```
