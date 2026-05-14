# Architecture

## Layout (same idea as `finance/`)

```
helpdesk/
├── backend/          # Laravel 11 — REST JSON under /api
├── frontend/       # Vue 3.5 + Vite — consumes API (dev proxy /api → Laravel)
├── documentation/  # Specs, OpenAPI, runbooks
├── docker/         # Optional compose stack
└── package.json    # concurrently dev:all
```

## Backend

- **Laravel 11** with `routes/api.php` registered in `bootstrap/app.php` (prefix `/api`).
- **Sanctum** for SPA / token authentication (integrate with CI session hand-off as per INTEGRATION.md).
- **Predis** for Redis (`REDIS_CLIENT=predis`) — queues, cache, rate limits (production).
- **SQLite** default for local dev; **MySQL/PostgreSQL** supported via `.env`.

### Core schema (migrations)

`helpdesk_categories`, `helpdesk_sla_rules`, `helpdesk_profiles`, `helpdesk_tickets`, `helpdesk_ticket_comments`, `helpdesk_ticket_attachments`, `helpdesk_ticket_histories`, `helpdesk_ai_providers`, `helpdesk_ai_logs`, `helpdesk_faq_categories`, `helpdesk_faq_articles`, `helpdesk_whatsapp_messages`, `helpdesk_teams_messages`, `helpdesk_audit_logs`, `helpdesk_notifications`.

Default ticket categories are seeded from the URS.

## Frontend

- **Vue 3.5.34**, **Pinia**, **Vue Router**, **Axios**.
- **Vite** dev server proxies `/api` → `http://127.0.0.1:8000`.
- **PrimeVue or Vuetify** — add when building module screens (URS §4.2).
- **RTL / i18n** — add `vue-i18n` + layout direction switch for Arabic (URS §7).

## AI, WhatsApp, Teams

Provider registry and log tables exist; implement services + webhooks in later iterations per URS §10–14.
