# Passport OIDC Provider + Legacy SSO Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Make Staff Portal an OAuth2/OIDC provider with Laravel Passport for new apps, while keeping Sanctum SPA tokens and legacy HS256 SSO JWT for existing CBP modules.

**Architecture:** Microsoft Entra authenticates humans into portal session; Passport issues authorization-code (+ PKCE) tokens to registered clients; `JWT_SECRET` SSO paths remain.

**Tech Stack:** `laravel/passport`, existing `PortalUser` (`HasApiTokens`), Sanctum, `App\Support\SsoJwt`.

## Global Constraints

- Do **not** remove Sanctum or SSO JWT
- SPA continues using Sanctum
- Client admin requires permission **17**
- Prefer authorization code + PKCE for public SPAs; confidential clients for server apps

---

### Task 1: Install and configure Passport

**Files:**
- Modify: `staff-portal/backend/composer.json` (via composer require)
- Modify: `Modules/Auth` or `app/Providers/AuthServiceProvider.php` / `AppServiceProvider`
- Modify: `config/auth.php` — API guard can remain sanctum for SPA; Passport uses its own middleware
- Create migrations from `php artisan passport:install` / `passport:keys`

- [ ] **Step 1: Require package**

```bash
cd /opt/homebrew/var/www/staff/staff-portal/backend && composer require laravel/passport --no-interaction
```

- [ ] **Step 2: Publish migrations, migrate, `passport:keys`, `passport:client --personal` if needed.** Follow Laravel 12 Passport docs for `Passport::enablePasswordGrant` (leave disabled) and OIDC/scopes.

- [ ] **Step 3: `PortalUser` implements `Laravel\Passport\HasApiTokens` carefully** — Sanctum also uses `HasApiTokens`. Prefer Passport’s trait alias or Laravel-recommended dual setup: many apps use Passport for OAuth clients and keep Sanctum for first-party SPA by not swapping the default API guard. Document chosen approach in `Modules/Auth/README-oauth.md`.

**Recommended dual-token approach:**
- Keep `auth:sanctum` on `/api/v1/*` for SPA.
- Mount Passport routes at `/oauth/*` (authorize, token, …).
- Optionally accept Passport Bearer tokens on `/api/v1/me` via middleware that tries Sanctum then Passport, **or** document that third-party apps use Passport `userinfo` / a dedicated `/api/v1/oauth/user` route protected by `auth:api` (Passport guard).

- [ ] **Step 4: Smoke** — create a test passwordless public client; complete authorize with an authenticated portal session.

---

### Task 2: OAuth clients admin API + Vue

**Files:**
- Create: `Modules/Auth/app/Http/Controllers/Api/V1/OAuthClientApiController.php`
- Modify: `Modules/Auth/routes/api.php`
- Create: `frontend/src/pages/auth/OAuthClientsPage.vue`
- Modify: `frontend/src/router/index.ts`, `portalNav.ts` (More → OAuth clients)
- Modify: `frontend/src/lib/authAdminApi.ts`

**CRUD:**
- List Passport clients (non-personal, or all non-revoked)
- Create: name, redirect URIs (newline/array), `public` boolean
- Reveal secret once on create for confidential clients
- Revoke / delete

- [ ] **Step 1: API with perm 17.**

- [ ] **Step 2: Vue page under More menu.**

- [ ] **Step 3: Build frontend.**

---

### Task 3: Docs for consumers

**Files:**
- Create: `staff-portal/docs/oauth-oidc-clients.md`

Document:
- Authorize URL, token URL, userinfo / me URL
- PKCE public client example
- How legacy SSO JWT still works and migration checklist for APM/Helpdesk

- [ ] **Step 1: Write doc with real `APP_URL` path prefixes (`/staff/staff-portal/backend`).**

- [ ] **Step 2: Manual: register a local test client and obtain a token.**

---

### Task 4: Regression

- [ ] Microsoft SPA login still works
- [ ] `POST /api/v1/sso/validate` and module SSO launch still work
- [ ] Passport authorize works for a logged-in user
