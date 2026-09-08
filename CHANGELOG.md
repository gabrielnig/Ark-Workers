# ArkWorkers.app — Changelog

All notable changes to the project. Format loosely follows Keep a
Changelog (keepachangelog.com) — newest at top.

---

## [Unreleased]

### Phase 1 follow-up: auth switched to email + password
- Replaced phone + PIN/OTP login (built and tested earlier this
  session) with email + password, plus an email-OTP verification step
  at signup. Reason: dropping SMS entirely for cost.
- Phone is now an optional profile field only, not used for auth
- Migration, User model, factory, AuthController, routes, and the full
  AuthTest suite reworked accordingly
- SECURITY.md §3.1 and ARCHITECTURE.md §3 updated to match
- 44 tests passing, 96 assertions, Pint clean

### Phase 1 (backend scaffold + auth) - COMPLETE
- Laravel 13 app scaffolded in `arkworkers-api/` (SQLite for local/test,
  PostgreSQL for staging/production per ARCHITECTURE.md §1)
- Migrations for the full core data model: users (phone/PIN, no
  email/password), spaces, space_access_grants, asset_types, assets,
  routines, tasks, vehicles, otp_codes, personal_access_tokens
- SpacePolicy, AssetPolicy, RoutinePolicy, TaskPolicy, all delegating
  through SpacePolicy for the space-restriction check per
  SECURITY.md §4.2, built and tested first per RESEARCH.md §7
- PIN and OTP login via Sanctum, rate limited at 5 attempts / 15
  minutes per SECURITY.md §3.1
- `docs/TDD-PROTOCOL.md` added: binding process document, tests
  written alongside implementation, full sandbox suite green before
  any push or deploy
- 44 tests passing, 102 assertions, Laravel Pint clean
- Self-audit pass after the suite was green found and fixed 3 issues,
  see LESSONS.md

### Documentation Phase (pre-code) - COMPLETE
- Added `PRD.md` — full requirements brainstorm
- Added `SECURITY.md` — security & NDPA compliance specification
  (later expanded with API/network hardening, incident response,
  field-level encryption, and 3-2-1 backup strategy)
- Added `DESIGN-SYSTEM.md` — visual identity and component standard
- Added logo assets (`docs/assets/logo/`) — final recolored mark, all
  variants (light/dark, monochrome, favicons)
- Added `ARCHITECTURE.md` — technical stack and data model (later
  updated with hybrid PM-trigger design and iOS/cross-platform notes)
- Added `RESEARCH.md` — industry research across CMMS, fleet
  management, church software, and mobile field-service apps
- Added `ai-context.md`, `LESSONS.md`, `HANDOVER.md`, `API.md`,
  `USER-GUIDE.md`, `DEPLOYMENT.md`, `DEV-SETUP.md`, `GLOSSARY.md`,
  this file
- Stack decided: Laravel + PostgreSQL backend, React PWA + Capacitor
  (Android now, iOS planned) frontend
- Backup strategy decided: 3-2-1 rule with a physical offsite copy

**No code written yet.** Phase 1 (backend scaffold + auth) begins next
session — see `ai-context.md` for full current state.
