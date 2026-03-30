# APM environment configuration

This guide describes how to configure the Laravel APM application using environment variables. **Secrets belong only in `.env`**, which is gitignored. Use the committed template for new setups.

## Quick setup

From the `apm/` project root:

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edit `.env` with your database credentials, mail/Exchange settings, `BASE_URL` / `APP_URL` (must match how users reach the staff portal and APM), and `JWT_SECRET` (if you did not run `jwt:secret`).

## Canonical sample file

The full list of variables with **example (non-secret) values** is in the repository root:

**[`../.env.example`](../.env.example)** (path from this file: `apm/.env.example`)

Copy that file to `.env` and replace placeholders. The sections below summarize what each group is for.

## Variable groups

| Area | Variables (representative) | Purpose |
|------|----------------------------|---------|
| **Application** | `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`, `BASE_URL`, `CI_BASE_URL` | App identity, debug mode, public URLs for links and redirects. `BASE_URL` is the CodeIgniter staff portal base; APM uses it for session integration and logout links. |
| **Database** | `DB_*` | MySQL (typical) connection for APM. |
| **Session / queue / cache** | `SESSION_*`, `QUEUE_CONNECTION`, `CACHE_*`, `REDIS_*` | Laravel session storage (`database` driver needs `sessions` table), queue driver (`database` is common), optional Redis. |
| **JWT** | `JWT_SECRET`, optional `JWT_TTL`, `JWT_REFRESH_TTL` | Bearer tokens for `/api/apm/v1`. Generate with `php artisan jwt:secret`. |
| **Staff portal** | `CI_*`, `STAFF_API_USERNAME`, `STAFF_API_PASSWORD`, optional `STAFF_API_TOKEN`, `STAFF_UPLOADS_PATH` | Reading CodeIgniter sessions from the shared DB; optional staff API and uploads path for resolving assets. |
| **Mail / Exchange** | `MAIL_*`, `USE_EXCHANGE_EMAIL`, `EXCHANGE_*` | Outbound email via Microsoft Graph (`exchange_oauth`). Register an app in Azure AD and set tenant, client ID, secret, and redirect URI. |
| **PHPMailer** | `PHPMailer_*` | SMTP fallback when not using Exchange. |
| **Firebase** | `FIREBASE_PROJECT_ID`, optional `FIREBASE_CREDENTIALS` | FCM push for pending approvals; place service account JSON at `storage/app/firebase-credentials.json` by default. See [FIREBASE_PUSH_NOTIFICATIONS.md](./FIREBASE_PUSH_NOTIFICATIONS.md). |
| **Features** | `ALLOW_QUARTER_CONTROL`, `ALLOW_ACTIVITY_OPERATIONS`, `SHOW_QUOTES`, `NOTIFICATION_CC_ADMIN_ASSISTANTS` | UI and notification behaviour toggles. |
| **Retention** | `LOGS_RETENTION_PERIOD` | Audit log retention (see `config/audit-logger.php`). |
| **AWS** | `AWS_*` | Optional S3 or other AWS services. |

## URLs checklist

- **`APP_URL`**: Must be the full base URL of the APM Laravel app (e.g. `https://example.org/staff/apm/`).
- **`BASE_URL`**: Staff (CodeIgniter) app base, typically one segment above APM (e.g. `https://example.org/staff/`).
- **`EXCHANGE_REDIRECT_URI`**: Must match an Azure “Redirect URI” for the mail app registration.
- **`CI_SESSION_*`**: Must match the CodeIgniter session cookie name and table columns used by the staff portal so APM can validate shared sessions.

## Security

- Do not commit `.env`, `.env.backup`, or `storage/app/firebase-credentials.json`.
- Rotate `JWT_SECRET`, Exchange client secrets, and mail passwords if they are exposed.
- In production, set `APP_DEBUG=false` and `APP_ENV=production`.

## Related documentation

- [Deployment Guide](./DEPLOYMENT.md)
- [API Documentation](./API_DOCUMENTATION.md) — API base URL derives from the same host as `APP_URL`.
- [Firebase / FCM](./FIREBASE_PUSH_NOTIFICATIONS.md)
- [Queue Setup](./QUEUE_SETUP_GUIDE.md)
