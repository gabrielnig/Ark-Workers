# ArkWorkers.app — User Guide

**Status:** stub — full guide written once the UI is built and can be
screenshotted. Structure and content outline locked now so nothing gets
forgotten.

---

## Structure (per role, per PRD.md §6)

### For Cleaning Staff, Maintenance/Technician, Security, Drivers
- How to log in (email + password, with an email verification code at
  signup)
- How to find your assigned tasks for the day
- How to complete a task and upload photo/video proof
- What to do if the upload fails or your connection drops mid-upload
  (should be genuinely simple — this is the resumable-upload flow from
  `DESIGN-SYSTEM.md` §6.1, but the guide needs to explain it in plain
  language for non-technical users)
- What a "restricted zone" badge means and what to do if you need access
  to one you don't currently have

### For Facility Manager
- Everything above, plus:
- How to create/edit Spaces, Asset Types, and Assets
- How to set up a new Routine and its schedule
- How to grant space access to a specific staff member (the Prophet's
  Quarters access-grant flow)
- How to view and act on the daily summary report

### For Admin/Pastor
- Everything above, plus:
- Full system oversight, all roles' data
- Vehicle document expiry management
- Any settings specific to the Admin role

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
