# Portal-owned mail hub (hybrid dispatch)

**Date:** 2026-10-02  
**Status:** Approved approach — awaiting spec review  
**Approach:** Portal mail hub (dispatch by default; encrypted config fetch for local fallback)

## Goals

1. **Staff Portal** (`/staff/settings/email-servers`) is the single source of truth for outbound mail providers and secrets.
2. **Default send path for CBP modules** (APM, Helpdesk, Finance, Risk Register): call Staff Portal to **dispatch** mail.
3. **Fallback:** modules may **fetch + decrypt** the active provider config and send locally (keep existing mailer code paths).
4. **Provider priority when resolving “active”:**
   1. Active **HTTP** (Africa CDC Email Server / [notifications.africacdc.org](https://notifications.africacdc.org/api/documentation)) if configured and healthy  
   2. Else the provider marked **Default** in the UI  
   3. Else Exchange → SMTP/Zoho → other active drivers  
5. Preferred / seeded configs live in the portal DB; **UI edits win** over env seeds (env only fills empty fields / first-run seed).
6. HTTP uses Integration **client_id** / **client_secret** (e.g. `staff-portal` as in Integration Settings).

## Non-goals

- Removing Exchange/SMTP drivers or module-local mailer implementations.
- Migrating every historical queue row overnight (existing queued jobs keep working; new sends use the hub client).
- Changing the notifications.africacdc.org product API (we only consume it).

## Current state

- Portal already has `portal_email_providers`, Settings UI, `PortalMailer`, `HttpNotificationsMailClient` (`/integrations/auth/token` + `/integrations/send`).
- Modules still prefer root/module `.env` (`MAIL_TRANSPORT`, `EXCHANGE_*`, `MAIL_HTTP_*`) and send independently.
- Share API (`/staff/backend/share/…`) authenticates modules (Basic / Bearer / static token) but has **no mail endpoints**.

## Architecture

```
┌─────────────┐  POST /share/mail/send (default)
│ APM         │──────────────────────────────────┐
│ Helpdesk    │  GET  /share/mail/active-config  │
│ Finance     │  (encrypted, TTL cache)          ▼
│ Risk Reg.   │                        ┌──────────────────┐
└─────────────┘                        │ Staff Portal     │
       │ fallback local send           │ EmailProviders   │
       │ after decrypt                 │ + PortalMailer   │
       └──────────────────────────────►└────────┬─────────┘
                                                │
                    ┌───────────────────────────┼──────────────────────┐
                    ▼                           ▼                      ▼
         notifications.africacdc.org      Microsoft Graph           SMTP
         (HTTP primary)                   (Exchange secondary)      (secondary)
```

### Resolution order (`resolveForSend`)

1. Explicit provider UUID (optional request field / admin test).  
2. Else first **active `http`** provider that passes a cheap health check (has `client_id`+`client_secret` or env fallback; optional cached token probe).  
3. Else `is_default` active provider.  
4. Else first active by priority list: `exchange`, `smtp`, `zoho`, …  
5. Else env-only virtual provider (legacy `MAIL_*` / `EXCHANGE_*` / `MAIL_HTTP_*`) so greenfield still works before UI seed.

UI “Set default” does **not** override a healthy HTTP provider for automatic resolution; it is step 3. Admins who want Exchange-only must deactivate HTTP or leave HTTP unconfigured.

### Config precedence (secrets)

For each field on a stored provider:

1. Non-empty value from **portal UI / DB** (encrypted at rest).  
2. Else env fallback (`MAIL_HTTP_*`, `EXCHANGE_*`, `MAIL_*`) — same as today.  
3. Seed on install/setup: create an inactive-or-active HTTP row from env **only if no HTTP provider exists**; never overwrite UI-edited rows.

## Staff Portal APIs (Share)

Auth: existing `AuthenticateShareApi` (Basic, Bearer JWT, `STAFF_API_TOKEN`).

### `POST /share/mail/send`

Dispatch via `PortalMailer` using resolution order above.

Request (JSON):

```json
{
  "to": ["user@example.org"],
  "subject": "…",
  "html": "<p>…</p>",
  "text": "optional plain",
  "cc": [],
  "bcc": [],
  "attachments": [
    { "name": "a.pdf", "content_base64": "…", "content_type": "application/pdf" }
  ],
  "provider_uuid": null,
  "idempotency_key": "optional-string"
}
```

Response `200`: `{ "success": true, "driver": "http", "provider_uuid": "…" }`  
Errors: `401`, `422`, `503` (no usable provider / upstream failure).

Notes:

- HTTP driver: attachments unsupported by notifications API — omit + log (current `PortalMailer` behavior) or return `422` if attachments required; prefer omit+warn for parity.  
- Optional short idempotency cache (e.g. 10 min) keyed by module + key.

### `GET /share/mail/active-config`

Returns encrypted active provider payload for **local** send fallback.

Response `200`:

```json
{
  "success": true,
  "data": {
    "provider_uuid": "…",
    "driver": "http",
    "from_address": "notifications@africacdc.org",
    "from_name": "Africa CDC",
    "expires_at": "ISO-8601",
    "ciphertext": "base64…",
    "iv": "base64…",
    "tag": "base64…"
  }
}
```

Encryption:

- AES-256-GCM using a shared key derived from Staff Portal `APP_KEY` **or** dedicated `STAFF_MAIL_CONFIG_KEY` in root `.env` (preferred for rotation).  
- Modules receive the same key via setup / root env inheritance (`shared/load-staff-root-env.php`) — **not** over the wire.  
- Ciphertext contains JSON: `{ driver, config, from_address, from_name }`.  
- TTL: `expires_at` ≤ 1 hour; modules must refetch after expiry.  
- Do **not** return plaintext secrets in Share JSON.

### `GET /share/mail/health` (optional)

Returns `{ "ok": true, "driver": "http", "provider_uuid": "…" }` for probes without sending.

## Module client

Shared PHP helper (copy or thin package under `shared/` / each module `App\Services\StaffPortalMailClient`):

1. Prefer `POST {STAFF_API_INTERNAL_BASE_URL}/share/mail/send` with existing Share auth.  
2. On network/`5xx`/timeout: `GET …/share/mail/active-config`, decrypt, call local mailer (`HttpNotificationsMailClient` / Exchange / SMTP) matching `driver`.  
3. Cache decrypted config in memory/APCu until `expires_at`.  
4. Env kill-switch: `STAFF_MAIL_DISPATCH=portal|local|auto` (default `auto` = hybrid).

Wire call sites gradually:

| Module | First touchpoints |
|--------|-------------------|
| Staff Portal | unchanged (uses `PortalMailer` directly) |
| APM | Graph/notification mail services, approval reminders |
| Helpdesk | ticket notifications |
| Finance | payslip / notification jobs |
| Risk Register | notification mailers if any |

## Settings UI / setup

- Default **create** driver in Email servers UI: **`http`** (not `exchange`).  
- Setup wizard / seed: upsert HTTP provider from `MAIL_HTTP_CLIENT_ID` / `SECRET` / `BASE_URL` when missing; set active; mark default if no other default.  
- Document that Integration Settings client id/secret (screenshot: Name “Staff Portal”, Client ID `staff-portal`) map to `MAIL_HTTP_*` / HTTP provider config.  
- Keep Exchange/SMTP as secondary options in the same UI.

## Security

- Share mail routes require Share auth only (service accounts already used by modules).  
- Rate-limit `mail/send` per client (e.g. 60/min) to reduce abuse if a token leaks.  
- Audit log: module identity, recipient count (not full body), driver, success/failure.  
- Never log client secrets or decrypted config.

## Rollout

1. Portal: resolution order + Share endpoints + encryption key + UI default driver `http`.  
2. Shared client in one module (Helpdesk or APM) behind `STAFF_MAIL_DISPATCH=auto`.  
3. Flip remaining modules.  
4. Optionally stop documenting per-module mail secrets as primary; root env remains seed/fallback only.

## Test plan

- [ ] With only HTTP configured: portal send + Share `mail/send` succeed via notifications API.  
- [ ] Deactivate HTTP, set Exchange default: resolution uses Exchange.  
- [ ] Kill portal Share: module fallback decrypts config and sends locally.  
- [ ] UI edit of HTTP secret survives setup re-seed (no overwrite).  
- [ ] Unauthorized Share call → 401.  
- [ ] Expired ciphertext → module refetches.  

## Open points (fixed by this spec)

| Topic | Decision |
|-------|----------|
| Dispatch model | Hybrid: portal send default, local after decrypt |
| Priority | HTTP healthy → UI default → Exchange → SMTP |
| Config ownership | Portal DB; env seeds only when empty / missing row |
| Encryption key | `STAFF_MAIL_CONFIG_KEY` or `APP_KEY` via root env |
