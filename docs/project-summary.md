# ArkWorkers.app — Project Summary

Facility management web and mobile app for King's Palace & Ark of Jesus Ministries International. This document summarizes everything decided so far.

## What this app does

Manages church facilities end to end: buildings, rooms/zones, equipment, vehicles, cleaning schedules, and maintenance routines, with issue reporting and time-based escalation built in. Not doing event/room booking — that's explicitly out of scope.

## Core architecture requirement

The system must be configuration-driven, not hardcoded. An admin should be able to:
- Create a brand new **Asset Type** (e.g. "Music Studio," "Security Bike") from scratch, with no developer involvement
- Attach one or more **Routines** to that asset type (e.g. "Weekly Equipment Dusting")
- Build each Routine from a reusable library of **Checklist Items**, toggled on/off
- Set routine triggers as time-based, usage-based (mileage/run-hours), or both with "whichever comes first" logic

This asset-type/routine builder is the single most important part of the system to get right.

## Roles

Super Admin, Facility Manager/Supervisor, Technician/Maintenance Worker, Cleaner/Janitorial Staff, Driver, Leadership/Pastor (read-only), General Staff/Volunteer (report-only).

## Key modules

- Asset registry and asset detail pages
- Maintenance/preventive maintenance scheduling
- Cleaning zone management (recurring schedules, e.g. Saturday deep clean)
- Issue reporting with photo attachment and severity tagging
- Escalation engine: severity tiers, configurable SLA response times, auto-escalation up the chain when SLAs are missed
- Vehicle/fleet management: service scheduling by mileage or time, trip logging, fuel logging, document expiry tracking
- Inventory/parts tracking
- Reporting/dashboards per role
- Notifications: push plus SMS fallback (important given variable connectivity in Nigeria)
- Offline-first mobile support, since technicians/cleaners may work without reliable signal

## Detailed asset inventory (grounded research)

Covers sanctuary furniture and flooring, AC systems (weekly/monthly/quarterly/annual maintenance intervals), electrical systems (monthly visual checks through annual thermal imaging), generators — both primary and standby, with hour-based (250/500/1000 hour) and calendar-based servicing rules, adjusted for Harmattan season dust and Nigerian fuel quality issues — vehicles, and a full cleaning zone breakdown.

## Product naming

Working name went through several iterations: FaithKeep to Ark Manage to the final name, **ArkWorkers.app**. The domain is already owned.

## Design direction

Clean, corporate, trustworthy visual style. Navy/deep blue as the primary color, with a gold/amber accent used narrowly for signature moments (not overused). Comfortable, spacious layout since many users are volunteers or less tech-savvy staff. Module/category icons should be color-coded for scannability. Real photography (not just icons) on hero sections and asset/vehicle detail pages. Reusable components: buttons, inputs, toggles, status badges, a horizontal stepper for status progression (Reported to Assigned to In Progress to Completed), and sparkline mini-charts on dashboard stat cards.

Google Stitch was used to generate first-pass mockups and validated the asset-type/routine builder concept works visually. A reference screenshot set (a more polished competing app) raised the visual bar further — photography, the gold accent, module color-coding, the stepper component, and sparklines were all identified as gaps to close in the real build.

## Tech stack

Fully self-hosted on a private VPS, no third-party BaaS (Supabase and similar were explicitly ruled out — full data ownership is a hard requirement).

- **Web frontend:** React + Vite, plain CSS / CSS Modules (no Tailwind)
- **Mobile app:** React Native via Expo (mobile-first priority; PWA install capability also wanted for the web app)
- **Shared logic:** TypeScript across web, mobile, and backend via a shared types/logic package
- **Backend:** Node.js + TypeScript (Fastify or Express)
- **Database:** PostgreSQL with JSONB columns for the flexible asset-type/routine schema
- **Auth:** Self-built (Lucia Auth or Passport.js) plus Google OAuth
- **File storage:** MinIO (self-hosted, S3-compatible)
- **Realtime:** Socket.io or Postgres LISTEN/NOTIFY
- **Push notifications:** Firebase Cloud Messaging
- **SMS:** Termii or Africa's Talking (Nigeria-optimized, not Twilio)
- **Offline sync (mobile):** WatermelonDB or Expo SQLite
- **Reverse proxy:** Nginx with Let's Encrypt
- **Process management:** PM2 or Docker Compose
- **Backups:** automated nightly Postgres dumps plus file storage backups to a separate location — explicitly called out as a must-have since self-hosting means there's no managed platform doing this automatically

## Budget (draft, infrastructure/tooling only — excludes developer labor)

| Item | Cost |
|---|---|
| AI Coding Agents (3 subscriptions) | ₦96,000 |
| Database Design | ₦35,000 |
| Tracking and API Integration | ₦35,000 |
| Internet and Data Usage | ₦30,000 |
| **Total** | **₦196,000** |

VPS hosting and the domain (arkworkers.app) are already in place and are not included in this budget.

## Deliverables

- A mobile-first web app with PWA install capability
- An Android app

## Working process going forward

- This GitHub repo (private) is the source of truth for docs and code.
- Code and docs get pushed here; deploy commands are provided for manual execution on the VPS (no direct VPS access for Claude).
- GitHub access is via a fine-grained personal access token, issued fresh per session and revoked at the end of each session. Not persisted anywhere between sessions.
