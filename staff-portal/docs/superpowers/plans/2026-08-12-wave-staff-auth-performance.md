# Wave Index: Staff, Contracts, Audit, Passport, Performance Forms

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Implement **one plan at a time** in order below. Each plan is independently testable.

**Goal:** Deliver the approved wave in [`docs/superpowers/specs/2026-08-12-staff-contracts-auth-audit-performance-design.md`](../specs/2026-08-12-staff-contracts-auth-audit-performance-design.md).

**Architecture:** Staff-portal Laravel modules + Vue SPA. Microsoft remains human IdP; Passport adds standards OAuth for other apps; Sanctum + SSO JWT stay. Performance forms become native Vue over existing PHP services (no iframe).

**Tech Stack:** Laravel 12, Sanctum, Passport, Vue 3, Vuetify, MySQL legacy `staff` schema, Font Awesome 6, Highcharts (dashboard, unchanged).

## Global Constraints

- Do not change Leave module UI/routes
- Do not reference CI3 Livewire iframe for Performance after Plan D
- Keep legacy SSO JWT (`JWT_SECRET`) working alongside Passport
- Contract current statuses: `1` Active, `2` Due, `7` Under Renewal; Expired `3`; Renewed `6`
- Default staff directory filter: `category=main_staff`
- Commits only when the user asks (do not auto-commit unless requested)

## Plan order

| # | Plan file | Deliverable |
|---|-----------|-------------|
| 1 | [`2026-08-12-staff-directory-contracts.md`](./2026-08-12-staff-directory-contracts.md) | Category, directory UX, create staff, contract CRUD + uniqueness |
| 2 | [`2026-08-12-audit-logs-parity.md`](./2026-08-12-audit-logs-parity.md) | CI3-parity audit logs API + Vue |
| 3 | [`2026-08-12-passport-oidc-provider.md`](./2026-08-12-passport-oidc-provider.md) | Passport OIDC provider + clients admin + legacy SSO kept |
| 4 | [`2026-08-12-performance-forms-spa.md`](./2026-08-12-performance-forms-spa.md) | Full PPA/midterm/endterm Vue forms; remove iframe |

## Coverage check vs spec

| Spec section | Plan |
|--------------|------|
| §1 Passport + legacy SSO | Plan 3 |
| §2 Audit logs | Plan 2 |
| §3 Staff create + contracts | Plan 1 |
| §4 Category + directory UX | Plan 1 |
| §5 Performance full SPA | Plan 4 |
