# Share / API JWT via Email-Password & Microsoft — Design

**Date:** 2026-08-14  
**Status:** Approved (conversation)  
**Scope:** Share JWT issuance for Swagger docs + SPA after email/password **or** Microsoft SSO.

## Goal

Users obtain a Share API JWT (`aud=share-api`, HS256 via `SsoJwt`) using either:

1. Work email + password, or  
2. Microsoft Entra SSO,

and can use that JWT in **Swagger Authorize** and in the **SPA** (same storage key as today’s password-login `sso_token`).

## Current state (baseline)

| Path | What you get |
|------|----------------|
| `POST /share/token` + HTTP Basic | Share JWT (`aud=share-api`) |
| `POST /api/v1/auth/login` | Sanctum token + SSO JWT (`sso_token`, staff session claims) |
| `GET /auth/spa-bridge` (after MS / web login) | Sanctum token only → SPA storage; **no** `sso_token` |
| `AuthenticateShareApi` | Accepts Basic, static `STAFF_API_TOKEN`, Share JWT (`aud`), or SSO JWT with `staff_id` |
| `/share/docs` | Swagger UI; schemes: basicAuth, bearerAuth, apiToken |

## Approach

**Session handoff + spa-bridge JWT** (not Passport OAuth2 in Swagger).

Reuse existing Microsoft web OAuth and password checks. Centralize Share JWT minting. Spa-bridge always writes Share JWT for the SPA. Swagger docs get a small auth bar that fills Bearer.

## Design

### A. Shared issuer

Add a small service (e.g. `Modules\Share\Services\ShareJwtIssuer` or method on existing Share service):

- Input: `PortalUser` (status active)
- Output: `{ access_token, token_type: Bearer, expires_in, aud }`
- Payload: `toSessionArray()` + `aud` = `config('share.jwt_audience')` + `sub` = user id
- TTL: `config('share.jwt_ttl')` (min 60)

Used by:

- Existing `POST /share/token` (Basic)
- New session-based issue endpoint
- Spa-bridge (and optionally keep SPA password-login `sso_token` as this Share JWT for consistency)

**Consistency note:** Prefer issuing **Share JWT** (`aud=share-api`) into `staff_portal.sso_token` for both password API login and spa-bridge, so one token works for Share API and CBP hand-off that already accepts Share/SSO JWTs with `staff_id`. Password login today uses SSO TTL / claims without forcing `aud=share-api`; align password `sso_token` to the Share issuer so SPA + Swagger use one shape.

### B. SPA after email/password or Microsoft

1. **Password (`POST /api/v1/auth/login`)**  
   Continue returning Sanctum `token` + `sso_token`. Change `sso_token` to come from the Share issuer (A) so Bearer works on `/share/*` without a second call.

2. **Microsoft / web session (`SpaBridgeController`)**  
   After creating Sanctum token, also mint Share JWT and pass it to the bridge view.  
   Bridge JS writes:
   - `staff_portal_api_token` (Sanctum) — unchanged  
   - `staff_portal.sso_token` (Share JWT) — **new**

3. Frontend auth store already persists `sso_token` when present; no new keys.

### C. Token endpoints (Swagger / API clients)

| Method | Path | Auth | Behavior |
|--------|------|------|----------|
| `POST` | `/share/token` | HTTP Basic (email + password) | Unchanged shape; use shared issuer (A) |
| `GET` | `/share/token` | Web session (`auth` middleware) | If `PortalUser` logged in → same JSON as POST; else `401` |

Optional (nice-to-have, not required for MVP): accept JSON body `{ email, password }` on `POST /share/token` when Basic is absent — easier for some clients; Swagger can keep Basic.

### D. Microsoft → Swagger docs

1. Swagger page link: **Sign in with Microsoft** →  
   `GET /auth/microsoft` with a safe return target stored in session, e.g. `share_docs_oauth_return` = `/share/docs`.

2. After successful MS callback, if return target is share docs:  
   mint Share JWT → redirect to `/share/docs#access_token=<jwt>` (or short-lived query, then strip).  
   Prefer **hash fragment** so the token is not sent to the server on subsequent navigations.

3. Docs page JS: on load, if `#access_token=` present, call Swagger UI `preauthorizeApiKey` / set `bearerAuth`, then `history.replaceState` to clear the hash.

4. If MS fails, redirect to `/share/docs?error=…` (no token).

5. Normal portal MS login (no share-docs return) keeps current behavior → spa-bridge → SPA.

**Allowlist** return URLs: only same-app paths under `/share/docs` (reject open redirects).

### E. Swagger UI bar (`/share/docs`)

Above Swagger UI:

- Email + password → `POST /share/token` with Basic → set Bearer  
- **Sign in with Microsoft** → flow (D)  
- Status: “Authorized” / Clear  

Update OpenAPI text: document Basic → token, session `GET /share/token`, and MS via docs bar. Keep `securitySchemes` as today (`basicAuth`, `bearerAuth`, `apiToken`).

### F. Out of scope

- Passport / OIDC password or auth-code schemes in OpenAPI  
- Changing Share response payloads for staff/divisions/directorates  
- Changing static `STAFF_API_TOKEN` behavior  
- Requiring password for Microsoft-only users (MS path is session/OIDC only)

## Security

- Share JWT still HS256 with `JWT_SECRET` / `APP_KEY`  
- Do not log raw JWTs  
- Clear token from URL hash after inject  
- Session `GET /share/token` requires authenticated portal user + CSRF not needed for GET of token in same-site cookie session (docs page is same origin as API)  
- Rate-limit `POST /share/token` if not already throttled (add throttle if missing)

## Tests

- Share issuer unit/feature: active user → JWT decodes with expected `aud` / `exp`  
- `POST /share/token` Basic still works  
- `GET /share/token` 401 guest; 200 authenticated  
- Spa-bridge view data includes Share JWT; frontend key documented  
- Password login `sso_token` has `aud=share-api` (decode in test)  
- MS return-to-docs: allowlist rejects external URLs (unit on redirect helper)

## Files (expected)

| Area | Files |
|------|--------|
| Issuer | `Modules/Share/Services/ShareJwtIssuer.php` (new) |
| Share API | `ShareReferenceApiController`, `Modules/Share/routes/share.php`, `openapi/share-api.yaml` |
| Docs UI | `ShareReferenceApiController::docs()` HTML/JS |
| Spa bridge | `SpaBridgeController`, `auth::spa-bridge` blade |
| MS return | `MicrosoftAuthController`, `MicrosoftAuthService` or `SpaRedirect` |
| SPA login token | `PortalSpaAuthController::tokenPayload` |
| Tests | Share + Auth feature tests |

## Success criteria

1. From `/share/docs`, email/password fills Bearer and `/share/get_current_staff` succeeds.  
2. From `/share/docs`, Microsoft sign-in fills Bearer the same way.  
3. After Microsoft (or web) login into the SPA, `staff_portal.sso_token` is set and works as Bearer on Share API.  
4. Password SPA login continues to work; `sso_token` remains Share-compatible.
