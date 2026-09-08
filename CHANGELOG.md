# ArkWorkers.app — Changelog

All notable changes to the project. Format loosely follows Keep a
Changelog (keepachangelog.com) — newest at top.

---

## [Unreleased]

### Documentation Phase (pre-code) — COMPLETE
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
