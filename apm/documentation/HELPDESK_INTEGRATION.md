# Helpdesk (ITSM) integration with Staff & APM

The **Helpdesk** module is a separate Laravel 11 API plus Vue 3 SPA under `helpdesk/` in this repository. **Identity is always the Staff portal session:** users open Helpdesk (and other CBP modules) via **secure POST SSO** — Staff `home/launch_module` auto-POSTs a JWT to `/sso/accept` on each module (JWT never in the browser URL). An optional **HMAC** `POST /api/v1/auth/exchange` exists for server-only integrations (`HELPDESK_BRIDGE_SECRET` must never ship to browsers).

## URLs (typical development)

| App | URL |
|-----|-----|
| Staff (CodeIgniter) | `CI_BASE_URL` / `BASE_URL` — e.g. `http://localhost/staff/` |
| APM (this Laravel app) | `APP_URL` — e.g. `http://localhost/staff/apm/` |
| APM system settings (branding) | `{APP_URL}system-settings` |
| Helpdesk API | `http://localhost/staff/helpdesk/backend/api/v1/*` |
| Helpdesk SPA | `http://localhost/staff/helpdesk/` |

Canonical Helpdesk env template: `helpdesk/backend/.env.example`.  
Helpdesk integration overview: [`helpdesk/documentation/INTEGRATION.md`](../../helpdesk/documentation/INTEGRATION.md).

> **Looking for the helpdesk itself, not just the APM bridge?** See the [Helpdesk documentation hub](../../helpdesk/documentation/README.md):
> - [User Guide](../../helpdesk/documentation/USER_GUIDE.md) — requesters, agents & admins; includes a step-by-step ticket-creation walkthrough.
> - [Developer Guide](../../helpdesk/documentation/DEVELOPER_GUIDE.md) — stack, schema, REST API, extension points & runbooks.
> - [Architecture](../../helpdesk/documentation/ARCHITECTURE.md) — one-page overview.

## Authentication flow (primary: POST SSO launch)

1. User signs in on the **CodeIgniter Staff** app.
2. On **Home** or the **CBP Modules** dropdown, clicking Helpdesk / APM / Finance **POST**s to `home/launch_module` with `module_key`.
3. Staff renders `sso_launch_redirect`, which **auto-POST**s `staff_sso_jwt` to the module accept URL (e.g. `/staff/helpdesk/backend/sso/accept`).
4. The module verifies **`JWT_SECRET`** (must match Staff root `.env`), starts a session, and the Helpdesk SPA calls **`POST /api/v1/auth/staff-sso`** to obtain a **Sanctum** Bearer token.

Legacy `?token=` on the Helpdesk URL still works in non-production when `SSO_ALLOW_URL_TOKEN` is enabled.

Shared helper: `application/helpers/sso_launch_helper.php`.

## Optional: HMAC exchange (server-only)

Trusted backends can call `POST /api/v1/auth/exchange` with `sig = HMAC_SHA256_hex(HELPDESK_BRIDGE_SECRET, "{staff_id}|{ts}|{lowercase(email)}")` — never expose `HELPDESK_BRIDGE_SECRET` to SPAs or mobile apps.

## Aligning environment variables with APM & Finance

Use the **same naming** as APM where it avoids drift (especially mail and Microsoft 365):

| Purpose | Helpdesk / shared variable | Notes |
|--------|----------------------------|--------|
| Staff SSO JWT | `JWT_SECRET` (same value as Staff + Finance) | Required for `POST /api/v1/auth/staff-sso`. Copy from the Staff repository root `.env` into `helpdesk/backend/.env`. |
| Helpdesk permission filter | `HELPDESK_SSO_PERMISSION_CODES` | Comma list (default **`85,92,93`** — APM, Finance, Helpdesk-only). JWT `permissions` must include one. Staff JWT email is usually in **`work_email`**; Helpdesk resolves `email`, `work_email`, `private_email`, etc. |
| API signing (APM) | `JWT_TTL`, etc. | APM continues to use `tymon/jwt-auth` for its own REST API; naming stays aligned. |
| Node Finance parity | `SESSION_SECRET` | Used by the Finance service for sessions; set on Helpdesk only if you add shared signed cookies or callbacks — otherwise optional. |
| Exchange / Graph mail | `EXCHANGE_TENANT_ID`, `EXCHANGE_CLIENT_ID`, `EXCHANGE_CLIENT_SECRET`, `EXCHANGE_REDIRECT_URI`, `EXCHANGE_SCOPE`, `EXCHANGE_AUTH_METHOD` | Same Azure app registration pattern as APM (`apm/.env.example`). Helpdesk reads these via `config/helpdesk.php` for future Microsoft Graph mail; primary sending today is Laravel Mail (see below). |
| SMTP (fallback / primary) | `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_*` | Standard Laravel mailer. |
| Failover | `MAIL_MAILER=failover`, `MAIL_FAILOVER_MAILERS` | Comma-ordered list, e.g. `smtp,log` — try SMTP first, then log in development. |

See [Environment variables](./ENVIRONMENT.md) for the full APM variable reference.

## Mail: Exchange OAuth vs SMTP fallback (Helpdesk)

- **APM** can use a custom `exchange_oauth` mail path tied to Microsoft Graph.
- **Helpdesk** uses stock Laravel `config/mail.php`. Set `MAIL_MAILER=failover` and `MAIL_FAILOVER_MAILERS=smtp,log` so production attempts **SMTP** (e.g. `smtp.office365.com`) first and falls back to **log** (or a second SMTP) if the first transport fails.
- When you add a Graph-based transport to Helpdesk, you can prepend that mailer name to `MAIL_FAILOVER_MAILERS` (e.g. `graph,smtp,log`) after implementing the driver.

## Related docs

- [Environment variables](./ENVIRONMENT.md)
- [Deployment](./DEPLOYMENT.md)
- Helpdesk: [`helpdesk/README.md`](../../helpdesk/README.md) · [docs hub](../../helpdesk/documentation/README.md) · [User Guide](../../helpdesk/documentation/USER_GUIDE.md) · [Developer Guide](../../helpdesk/documentation/DEVELOPER_GUIDE.md) · [Architecture](../../helpdesk/documentation/ARCHITECTURE.md) · [Integration](../../helpdesk/documentation/INTEGRATION.md)
