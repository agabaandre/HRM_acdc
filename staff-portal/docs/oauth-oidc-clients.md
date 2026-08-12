# OAuth / OIDC Clients

This backend now exposes Laravel Passport endpoints for third-party OAuth2 / OIDC-style clients while keeping the existing Staff Portal SPA on Sanctum and the legacy cross-app SSO JWT flow unchanged.

## Base URL

Local/default backend base URL:

`http://localhost/staff/staff-portal/backend`

Production should use the deployed backend host/path that matches `APP_URL` / `STAFF_PORTAL_BASE_URL`.

## OAuth endpoints

- Authorize: `GET /oauth/authorize`
- Token: `POST /oauth/token`
- Token refresh: `POST /oauth/token/refresh`
- Protected user endpoint for Passport bearer tokens: `GET /api/v1/oauth/user`

With the default local backend URL, those become:

- `http://localhost/staff/staff-portal/backend/oauth/authorize`
- `http://localhost/staff/staff-portal/backend/oauth/token`
- `http://localhost/staff/staff-portal/backend/oauth/token/refresh`
- `http://localhost/staff/staff-portal/backend/api/v1/oauth/user`

## Current auth split

- Staff Portal SPA: Sanctum bearer tokens on existing `auth:sanctum` routes such as `/api/v1/me`
- Legacy CBP apps: existing SSO JWT endpoints such as `/api/v1/sso/validate` and `/sso/callback`
- New OAuth clients: Passport on `/oauth/*` plus `/api/v1/oauth/user`

## PKCE client notes

Public SPA/mobile clients should use Authorization Code + PKCE against Passport.

High-level flow:

1. Redirect the signed-in user to `/oauth/authorize` with PKCE parameters.
2. Exchange the authorization code at `/oauth/token`.
3. Call `/api/v1/oauth/user` with `Authorization: Bearer <access-token>`.

Example parameters to plan for:

- `response_type=code`
- `client_id=<passport-client-id>`
- `redirect_uri=<registered-redirect-uri>`
- `scope=`
- `code_challenge=<pkce-challenge>`
- `code_challenge_method=S256`
- `state=<csrf-state>`

## Legacy migration notes

Existing SSO JWT consumers do not need to change yet.

Migration checklist for APM / Helpdesk / other CBP apps:

1. Register a Passport client for the app.
2. Prefer Authorization Code + PKCE for browser-based clients.
3. Swap JWT launch/token validation calls for OAuth authorize + token exchange.
4. Use `/api/v1/oauth/user` as the initial authenticated identity check.
5. Remove shared-secret JWT assumptions only after the app is fully cut over.

## TODO

- Add documented client registration steps once the OAuth client admin UI lands.
- Confirm whether OpenID discovery / JWKS endpoints will be exposed directly or via a thin wrapper in a follow-up task.
