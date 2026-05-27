# Helpdesk — Developer Guide

Technical reference for engineers building on, deploying, or debugging the helpdesk module. Pair this with [USER_GUIDE.md](./USER_GUIDE.md) (end-user perspective), [ARCHITECTURE.md](./ARCHITECTURE.md) (high-level layout), and [INTEGRATION.md](./INTEGRATION.md) (SSO + Staff Share API + webhooks).

## Table of contents

1. [Stack & layout](#stack--layout)
2. [Local development](#local-development)
3. [Routing in production (Apache)](#routing-in-production-apache)
4. [Authentication & SSO](#authentication--sso)
5. [Authorization model](#authorization-model)
6. [Database schema reference](#database-schema-reference)
7. [REST API reference](#rest-api-reference)
8. [Ticket lifecycle internals](#ticket-lifecycle-internals)
9. [Services & jobs](#services--jobs)
10. [Frontend architecture](#frontend-architecture)
11. [Public TV dashboard](#public-tv-dashboard)
12. [Audit log & ISO logging](#audit-log--iso-logging)
13. [Extending the module](#extending-the-module)
14. [Operations / runbooks](#operations--runbooks)

---

## Stack & layout

```
helpdesk/
├── backend/                # Laravel 11 — REST JSON API
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/{,Admin/,Auth/,Webhooks/}
│   │   ├── Http/Requests/                 # Form Request validation classes
│   │   ├── Http/Resources/                # API response shapers
│   │   ├── Models/                        # Eloquent models (Helpdesk*)
│   │   ├── Services/                      # TicketAssignmentService, TicketNumberGenerator, …
│   │   ├── Jobs/                          # ScanTicketForAiSignals, dispatched async
│   │   └── Policies/                      # HelpdeskTicketPolicy
│   ├── routes/api.php                     # All /api/v1/* routes (no web.php usage)
│   ├── config/helpdesk.php                # Module config (SSO, Staff Share API, integrations)
│   ├── database/migrations/2026_*.php     # Schema (see § Database schema reference)
│   ├── .htaccess + server.php + index.php # Apache mount under /staff/helpdesk/backend/
│   └── .env.example                       # Required keys (JWT_SECRET, STAFF_API_*, …)
├── frontend/               # Vue 3.5 + Vite SPA
│   ├── src/views/                         # Page components (one per route)
│   ├── src/components/                    # Layout + settings panels + shared UI
│   ├── src/stores/{auth,app}.ts           # Pinia stores
│   ├── src/router/index.ts                # Vue Router (route guards)
│   ├── src/lib/{api,sso}.ts               # axios + JWT helpers
│   └── vite.config.ts                     # base = "/staff/helpdesk/", proxy /api → backend
├── documentation/                          # ← you are here
│   ├── README.md                          # Index
│   ├── USER_GUIDE.md                      # Requester / agent / admin walkthroughs
│   ├── DEVELOPER_GUIDE.md                 # This file
│   ├── ARCHITECTURE.md                    # 1-page architecture overview
│   ├── INTEGRATION.md                     # SSO, Staff Share API, webhooks
│   └── openapi.yaml                       # OpenAPI 3 stub
├── docker/                                 # (Optional) compose stack
├── package.json                            # `dev:all` via concurrently
└── setup.sh                                # Bootstrap helper
```

**Hosting model:** the API and the built SPA are **both served by Apache** under the same vhost as the Staff portal — there is no separate Node dev server in production. The Vite dev server (`:5174`) is optional and only used for HMR while iterating.

| Layer | Tech |
|---|---|
| API | Laravel 11, Sanctum (Bearer abilities `helpdesk:*`), Predis (Redis), Eloquent |
| Queues / cache | Redis (`REDIS_CLIENT=predis`) — graceful fallback to file/sync in dev |
| DB | MySQL in production; SQLite supported for tests |
| Frontend | Vue 3.5.34, TypeScript, Pinia, Vue Router, Axios, Quill (rich text), maatwebsite/excel for exports |
| Build | Vite 8 (base `/staff/helpdesk/`) |

---

## Local development

```bash
# 1. Backend
cd helpdesk/backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# 2. Frontend (build for Apache or run Vite for HMR)
cd ../frontend
npm install --cache ./.npm-cache --legacy-peer-deps
npm run build            # writes frontend/dist/ served by Apache
# OR
npm run dev              # starts Vite on http://localhost:5174 with /api proxy
```

Smoke tests:

```bash
curl -i http://localhost/staff/helpdesk/backend/api/v1/health
curl -i http://localhost/staff/helpdesk/                       # SPA via Apache
curl -i http://localhost:5174/                                  # SPA via Vite (HMR)
```

### Required env keys (`backend/.env`)

| Key | Purpose |
|---|---|
| `APP_URL` | `http://localhost/staff/helpdesk/backend` (must include the subpath). |
| `JWT_SECRET` | **Must match** the Staff portal root `.env`; verifies the `?token=` SSO payload. |
| `HELPDESK_BRIDGE_SECRET` | HMAC secret for the server-only `POST /auth/exchange` bridge. Never expose to browsers. |
| `STAFF_API_BASE_URL`, `STAFF_API_TOKEN`, `STAFF_API_USERNAME`, `STAFF_API_PASSWORD` | Staff Share API credentials (Basic Auth + token). Copy from `apm/.env` so directory sync matches APM. |
| `HELPDESK_SSO_PERMISSION_CODES` | CSV of Staff permission IDs allowed to open Helpdesk. Default `85,92,93`. |
| `HELPDESK_SSO_STAFF_ROLE_IDS_ADMIN` | Staff role-group IDs auto-mapped to helpdesk `admin`. Default `10`. |
| `HELPDESK_DEFAULT_AGENT_DIVISION_IDS` | Divisions whose staff become agents on SSO unless an admin pins them otherwise. |
| `HELPDESK_REFERENCE_CACHE_TTL` | Seconds to cache Staff Share API responses. Default `300`. |
| `MAIL_MAILER`, `MAIL_FAILOVER_MAILERS`, `MAIL_*` | Outbound mail for ticket notifications + resolution-confirmation links. |
| `LOG_STACK` | Add `iso_json` to enable the JSON Lines audit channel. |

---

## Routing in production (Apache)

| URL | Served from | File |
|---|---|---|
| `/staff/helpdesk/` | `helpdesk/frontend/dist/index.html` | `helpdesk/.htaccess` (SPA fallback) |
| `/staff/helpdesk/assets/*` | `helpdesk/frontend/dist/assets/*` | same |
| `/staff/helpdesk/backend/api/v1/*` | `helpdesk/backend/public/index.php` | `helpdesk/backend/.htaccess` → `server.php` |
| `/staff/helpdesk/screen` | SPA fallback (public route, no chrome) | `helpdesk/.htaccess` |

Key facts:

- `helpdesk/backend/.htaccess` rewrites **every** URL under `/backend/` to `server.php`. The `Authorization` header is explicitly preserved (`SetEnvIf Authorization`) so Sanctum Bearer tokens reach PHP.
- `vite.config.ts` sets `base: '/staff/helpdesk/'` so the production bundle's asset URLs (and Vue Router `createWebHistory`) work under the subpath.
- The SPA's axios client reads `VITE_HELPDESK_API_BASE_URL` (defaults to `/staff/helpdesk/backend`) so calls resolve to the Apache-served API on the same host (no CORS).
- The Staff portal home page (`application/modules/home/controllers/Home.php`) builds the SSO redirect URL: `<host>/staff/helpdesk?token=<JWT>` — override the subpath via the `HELPDESK_SPA_PATH` env if you mount the SPA elsewhere.

---

## Authentication & SSO

Two flows are supported; the SPA always uses (1).

### 1. Staff portal JWT → Sanctum token (browser SSO)

```
Staff portal home  ─►  /staff/helpdesk/?token=<HS256 JWT, JWT_SECRET signed>
SPA boot           ─►  POST /api/v1/auth/staff-sso { token }
StaffSsoController ─►  verify JWT, check HELPDESK_SSO_PERMISSION_CODES
                   ─►  upsert User + HelpdeskProfile
                   ─►  return { token: Sanctum bearer with abilities ['helpdesk:*'] }
```

Role assignment rules in `StaffSsoController::resolveRole`:

1. If `is_designated_agent = true` on the profile, **keep** the role as `agent` (override-lock).
2. Else if the JWT's `role` claim matches `sso_staff_role_ids_admin` → `admin`.
3. Else if the JWT's `division_id` matches `default_agent_division_ids` → `agent`.
4. Otherwise the role from the existing profile is preserved (or `user` for first-time logins).

### 2. Server-to-server bridge

`POST /api/v1/auth/exchange` accepts `{ payload, sig }` where `sig = HMAC_SHA256(payload, HELPDESK_BRIDGE_SECRET)`. Useful when CI/APM backends need an API token without bouncing a user through the browser. **Never expose the secret to the browser.**

### Sanctum tokens

The SPA's bearer token is stored in `localStorage` (`stores/auth.ts → applyToken`) and sent on every API request via the axios interceptor in `lib/api.ts`. Token name is `helpdesk-staff-sso` (or `helpdesk-bridge` for exchange flow) so they can be audited and revoked individually.

---

## Authorization model

There are **three layers** of authorization, applied top to bottom:

1. **Route middleware** — `auth:sanctum` on every non-public route. Public endpoints (`/health`, `/auth/*`, `/public/*`, `/webhooks/*`, `/avatar/*`, `/categories`) are explicitly outside the group.
2. **Controller trait checks** — `AuthorizesHelpdeskAdmin` (requires `role === admin`) and `AuthorizesKbManager` (requires `admin` OR `can_manage_kb`) guard the admin namespace.
3. **Policy checks** — `HelpdeskTicketPolicy` (registered via `AuthServiceProvider`) covers per-action ticket gates: `view`, `update`, `delete`, `comment`, `commentInternal`, `attachFiles`, `submitResolution`. Each policy method enforces role + ticket ownership rules (e.g. requesters can only view & comment on tickets they're the requester of).

In addition, individual controllers do tactical validation:

- `TicketController::ensureReassignAllowed` rejects reassignment if status is not in `REASSIGNABLE_STATUSES` (open/pending/in_progress) and confirms the actor has admin or `can_reassign_tickets`.
- `TicketController::update` strips `status`, `priority`, `assigned_user_id`, and `category_id` from the input array when the actor is a regular user.
- `TicketCommentController::store` rejects `is_internal = true` if the actor isn't staff.

---

## Database schema reference

Tables are created in chronological order; later migrations add columns. Names are kept verbose for clarity.

| Table | Purpose | Key columns |
|---|---|---|
| `helpdesk_categories` | Issue categories shown to requesters. | `name`, `slug`, `sort_order`, `is_active` |
| `helpdesk_sla_rules` | Response/resolution targets, optionally per category. | `category_id?`, `response_minutes`, `resolution_minutes`, `business_hours JSON`, `is_active` |
| `helpdesk_profiles` | One row per `users.id`. Helpdesk-specific persona. | `user_id UNIQUE`, `staff_id UNIQUE`, `role`, `directorate_id`, `division_id`, `duty_station`, `sap_no`, `is_designated_agent`, `can_manage_kb`, `can_reassign_tickets`, `synced_at` |
| `helpdesk_tickets` | The aggregate. | `ticket_number UNIQUE`, `category_id`, `subject`, `description`, `priority`, `status`, `source`, `requester_staff_id`, `requester_name`, `requester_email`, `assigned_user_id`, `directorate_id`, `division_id`, `created_by_user_id`, `agent_logged_for_requester`, `first_response_at`, `resolved_at`, `closed_at`, `sla_response_due_at`, `sla_resolution_due_at`, `resolution_summary`, `resolution_confirm_token UNIQUE`, `resolution_confirmed_at`, `resolution_submitted_by_user_id`, `resolved_by_user_id`, `meta JSON` |
| `helpdesk_ticket_sequences` | Per-year counter for ticket numbers. | `year PRIMARY`, `last_seq` |
| `helpdesk_ticket_comments` | Public + internal comments. | `ticket_id`, `user_id?`, `author_staff_id?`, `is_internal`, `body` |
| `helpdesk_ticket_attachments` | One row per uploaded file. | `ticket_id`, `disk`, `path`, `original_name`, `size_bytes`, `mime_type`, `uploaded_by` |
| `helpdesk_ticket_histories` | Append-only event log. | `ticket_id`, `user_id?`, `event`, `payload JSON`, `created_at` (no `updated_at`) |
| `helpdesk_agent_categories` | Many-to-many between agents and categories (routing). | `user_id`, `category_id` |
| `helpdesk_kb_articles` | Knowledge-base questions/answers. | `category_id`, `question`, `answer`, `sort_order`, `is_active`, `created_by_user_id`, `updated_by_user_id` |
| `helpdesk_settings` | KV store for the admin settings UI. | `key UNIQUE`, `value` (TEXT, encrypted for secrets) |
| `helpdesk_audit_logs` | ISO 27001 / 27014 evidence. | `user_id?`, `staff_id?`, `action`, `auditable_type/id`, `ip_address`, `user_agent`, `correlation_id`, `new_values JSON`, `created_at` |
| `helpdesk_ai_providers`, `helpdesk_ai_logs` | Provider registry + per-call log. | encrypted `api_key`, `prompt`, `response`, `cost` |
| `helpdesk_whatsapp_messages`, `helpdesk_teams_messages` | Inbound/outbound message archive. | `ticket_id?`, `direction`, `external_id`, `payload JSON` |
| `helpdesk_notifications` | In-app notification feed. | `user_id`, `title`, `body`, `data JSON`, `read_at` |
| `helpdesk_faq_categories`, `helpdesk_faq_articles` | Legacy FAQ tables (kept; current FAQ lives in `helpdesk_kb_articles`). |

Migration files in `backend/database/migrations/`:

- `2026_05_13_211229_create_helpdesk_core_tables.php` (bulk of the schema)
- `2026_05_13_213634_create_helpdesk_ticket_sequences_table.php`
- `2026_05_14_120000_create_helpdesk_settings_table.php`
- `2026_05_15_100000_helpdesk_agents_resolution_workflow.php` (resolution columns + agent-category pivot)
- `2026_05_20_120000_helpdesk_audit_logs_correlation_id.php`
- `2026_05_21_120000_add_sap_no_to_helpdesk_profiles.php`
- `2026_05_26_120000_create_helpdesk_kb_articles.php` (+`can_manage_kb` on profile)
- `2026_05_26_140000_add_is_designated_agent_to_helpdesk_profiles.php`
- `2026_05_26_160000_add_can_reassign_tickets_to_helpdesk_profiles.php`

---

## REST API reference

Base path: `/staff/helpdesk/backend/api/v1` (often shortened to `/api/v1` below).

### Public (no auth)

| Method | Path | Notes |
|---|---|---|
| GET  | `/health` | Liveness, branding palette, integration "configured" flags. |
| POST | `/auth/exchange` | HMAC bridge for trusted backends. Body: `{ payload, sig }`. |
| POST | `/auth/staff-sso` | Browser SSO. Body: `{ token: <Staff JWT> }`. |
| POST | `/public/tickets/confirm-resolution` | Body: `{ token }`. Moves ticket to `resolved`. |
| GET  | `/public/screen` | Aggregate TV dashboard feed. `throttle:120,1`. No PII. |
| GET  | `/avatar/{user}` | Signed staff photo. `throttle:300,1`. |
| GET  | `/categories` | Active categories. |
| GET/POST | `/webhooks/whatsapp` | Meta verification (GET) + inbound (POST). |
| POST | `/webhooks/teams/activities` | Azure Bot inbound. |

### Authenticated (`auth:sanctum`)

| Method | Path | Roles | Notes |
|---|---|---|---|
| GET  | `/me` | any | Returns `MeResource` (user + profile + flags). |
| GET  | `/reference-data` | staff | Divisions + directorates (cached). |
| GET  | `/reference-data/staff` | staff | Directory list. Query: `directorate_id`, `division_id`, `q`. |
| GET  | `/kb/articles` | any | List/search KB. Query: `q`, `category_id`. |
| GET  | `/kb/articles/{article}` | any | Show one article. |
| GET/POST/PUT/DELETE | `/admin/kb/articles[…]` | admin or `can_manage_kb` | KB CRUD. |
| GET  | `/tickets` | any | Index (scoped by role). |
| POST | `/tickets` | any | Create ticket (see § ticket lifecycle internals). |
| GET  | `/tickets/{ticket}` | any (policy) | Show ticket. |
| PUT  | `/tickets/{ticket}` | staff (policy) | Update ticket. Requesters: comments only. |
| DELETE | `/tickets/{ticket}` | admin (policy) | Delete. |
| GET  | `/tickets/{ticket}/eligible-agents` | admin or `can_reassign_tickets` | List candidates for reassign. |
| POST | `/tickets/{ticket}/reassign` | admin or `can_reassign_tickets` | Body: `{ assignee_id, reason }` (reason ≥ 10 chars). Only `open`/`pending`/`in_progress`. |
| GET  | `/tickets/{ticket}/comments` | any (policy) | List comments (internal hidden from requesters). |
| POST | `/tickets/{ticket}/comments` | any (policy) | Body: `{ body, is_internal? }`. |
| POST | `/tickets/{ticket}/attachments` | any (policy) | `multipart/form-data` file ≤ 10 MB. |
| POST | `/tickets/{ticket}/submit-resolution` | staff (policy) | Body: `{ resolution_summary }`. |
| GET  | `/reports/agent-dashboard` | staff | KPI report. |
| GET  | `/reports/my-requester` | any | Requester self-report. |
| GET  | `/reports/admin-summary` | admin | Org-wide counts. |
| GET  | `/reports/export` | staff | Excel download. Query: `scope=all|mine|assigned`. |
| GET/PUT | `/admin/settings` | admin | KV store. Secrets are encrypted server-side via `Crypt`. |
| GET  | `/admin/agents` | admin | Agent roster + category routing + flags. |
| PUT  | `/admin/agents/{user}` | admin | Update agent. Body: `{ categories[], can_manage_kb, can_reassign_tickets }`. |
| GET  | `/admin/agents/division-candidates` | admin | Staff in `default_agent_division_ids`. |
| POST | `/admin/agents/designate` | admin | Body: `{ staff_id }`. Sets `is_designated_agent = true`. |
| DELETE | `/admin/agents/designate/{staffId}` | admin | Unset. |
| GET/POST/PUT/DELETE | `/admin/categories[…]` | admin | CRUD. Delete blocked when tickets exist. |
| GET/POST/PUT | `/admin/sla-rules[…]` | admin | SLA management. |
| POST | `/admin/reference-sync` | admin | Warm/clear Staff Share API cache. |
| GET  | `/admin/audit-logs` | admin | Paginated audit log. |

For canonical request/response schemas see `documentation/openapi.yaml` (expand to full coverage as endpoints stabilise).

---

## Ticket lifecycle internals

### Creation flow (`POST /tickets`)

1. **`StoreTicketRequest`** validates input. Notable rules:
   - `category_id` required + must exist.
   - `description` ≤ 65 000 chars, may contain Quill-rendered HTML.
   - `priority` *prohibited* for end-users; allowed for staff (`low|medium|high|critical`).
   - `source` optional (`web|whatsapp|teams|email`); defaults to `web`.
   - `requester_staff_id` required when actor is **not** an end-user; integer ≥ 1.
2. **Requester resolution** — `StaffDirectoryLookupService::lookup($staffId)` calls the Staff Share API (cached). The returned name, work email, directorate, and division are stamped onto the ticket.
3. **Subject derivation** — `TicketSubjectGenerator::generate($category, $requesterName, $description)` builds the subject (clients can't override).
4. **Ticket number** — `TicketNumberGenerator::next()` locks `helpdesk_ticket_sequences` for the current year inside a transaction, increments `last_seq`, and formats `HD-{year}-{NNNNNN}`.
5. **Auto-assign** — `TicketAssignmentService::assignAgent($ticket, $dutyStation)` picks an agent from:
   - the agents handling the ticket's category (`helpdesk_agent_categories`),
   - then narrows by duty station,
   - then by least-loaded.
   When the actor is a staff member filing for themselves, the actor is assigned directly.
6. **AI signals (async)** — `ScanTicketForAiSignals` is dispatched onto the default queue if `ai_active` is on. The job is allowed to be a no-op (it logs to `helpdesk_ai_logs` and exits).
7. **History** — `HelpdeskTicket::booted()` writes a `ticket.created` row to `helpdesk_ticket_histories`. Subsequent updates fire `ticket.updated`, `ticket.reassigned`, `ticket.resolved`, etc., via `TicketHistoryLogger`.
8. **Notifications** — `TicketAssignmentNotifier` is fired on assignment changes (mail today; Teams/WhatsApp wired in follow-ups).

### State machine

```
              ┌────────────┐
              │   open     │◄─── default after create
              └─────┬──────┘
        agent edits │
                    ▼
     ┌────────┐  ┌────────────────┐
     │ pending│◄►│  in_progress   │
     └────┬───┘  └────────┬───────┘
          │               │
          │ submitResolution + require_resolution_confirmation=1
          ▼               ▼
   ┌──────────────────────────────────────┐
   │ awaiting_requester_confirmation       │──► requester confirms ──► resolved
   └──────────────────────────────────────┘                                │
                                                                  agent closes
                                                                          ▼
                                                                       closed
```

Reassignment is allowed only on `open`, `pending`, `in_progress` (constant `TicketController::REASSIGNABLE_STATUSES`).

### Resolution sub-flow

- Agent calls `POST /tickets/{id}/submit-resolution` with `{ resolution_summary }`.
- If `helpdesk_settings.require_resolution_confirmation = 1`:
  - status ← `awaiting_requester_confirmation`,
  - `resolution_confirm_token = bin2hex(random_bytes(32))` (unique),
  - email is sent to the requester containing `<APP_URL>/tickets/confirm-resolution?token=…`,
  - **public** route consumes the token and transitions to `resolved`.
- If the toggle is off, the ticket goes straight to `resolved` and `resolved_at`, `resolved_by_user_id`, `resolution_confirmed_at` are stamped.

---

## Services & jobs

| Class | Role |
|---|---|
| `Services\TicketNumberGenerator` | Per-year sequence with row-level lock. |
| `Services\TicketSubjectGenerator` | Builds the human-readable subject from category + requester + description. |
| `Services\TicketAssignmentService` | Auto-assigns an agent based on `helpdesk_agent_categories` + duty station + current load. |
| `Services\TicketAssignmentNotifier` | Sends mail (and channel notifications, when configured) on assignment changes. |
| `Services\TicketHistoryLogger` | Appends typed events to `helpdesk_ticket_histories`. |
| `Services\StaffDirectoryLookupService` | Wraps `StaffPortalReferenceClient` with normalisation (matches the APM contract). |
| `Services\StaffPortalReferenceClient` | HTTP client for the Staff Share API; caches responses (`HELPDESK_REFERENCE_CACHE_TTL`). |
| `Services\AiAgentPickerService` | Placeholder hook — returns a candidate `assigned_user_id` when `ai_agent_assignment_enabled = 1`. |
| `Services\AuditLogger` | Writes `helpdesk_audit_logs` rows + optional `iso_json` channel logs. |
| `Jobs\ScanTicketForAiSignals` | Async job dispatched after ticket create. Currently a stub. |

Queue: default Laravel queue connection (`QUEUE_CONNECTION=redis` in production, `sync` in dev unless overridden). Run with `php artisan queue:work`.

---

## Frontend architecture

### Boot

`src/main.ts`:

1. Creates Pinia, mounts axios defaults.
2. If the URL contains `?token=…`, calls `auth.exchangeStaffSso(token)` → exchanges for the Sanctum bearer, then strips the token from the URL.
3. On failure, renders an inline SSO error screen and links back to the portal (or redirects with `helpdesk_error=sso`).
4. Boots Vue Router and mounts `<App />`.

### Routing

`src/router/index.ts` exposes guards via `to.meta`:

- `public: true` — bypass all auth checks (used by `/tickets/confirm-resolution` and `/screen`).
- `requiresAuth: true` — must have a Sanctum token.
- `requiresAdmin: true` — must have `role === 'admin'`.
- `requiresStaff: true` — must be `agent | supervisor | admin | auditor`.
- `requiresKbManager: true` — `admin` or `can_manage_kb`.
- `chrome: false` — `App.vue` skips the top header / primary nav / footer (used by `/screen`).

### State

- `stores/auth.ts` — holds `token` (localStorage) and `me` (user + profile). Exposes `exchange`, `exchangeStaffSso`, `fetchMe`, `logout`, `invalidateSession`, `applyToken`. The interface `MeProfile` mirrors the `MeResource` payload and includes `can_manage_kb`, `can_reassign_tickets`, `is_designated_agent`.
- `stores/app.ts` — caches `/api/v1/health` (branding colours + integration hints) at boot.

### Pages

| Route | View | Notes |
|---|---|---|
| `/` | `HomeView` | KB search + role-aware quick links. |
| `/tickets` | `TicketsView` | List, search, filter. |
| `/tickets/new` | `TicketCreateView` | Quill editor + requester picker. |
| `/tickets/:id` | `TicketDetailView` | Timeline, comments, attachments, resolution form. |
| `/tickets/confirm-resolution` | `ConfirmResolutionView` | **Public**, token-consuming. |
| `/desk/agent` | `AgentDashboardView` | Agent workspace. |
| `/reports` | `ReportsView` | My tickets / Admin summary tabs. |
| `/knowledge-base/manage` | `KbManageView` | KB CRUD (admin / `can_manage_kb`). |
| `/screen` | `ScreenDashboardView` | **Public, no chrome**, full-bleed TV dashboard. |
| `/settings/{general,ai,agents,categories,jobs,integrations,logging}` | `SettingsLayoutView` + panels | Admin only. |

### Settings composables

`SettingsLayoutView` provides a single `helpdeskAdminSettings` composable to its children via `provide/inject`. Each panel calls the composable's `load`, `update`, and `save` helpers so HTTP traffic is centralised.

---

## Public TV dashboard

**Backend:** `App\Http\Controllers\Api\V1\PublicScreenController` at `GET /api/v1/public/screen`.

- Sits **outside** the `auth:sanctum` group, with `throttle:120,1` (120 requests / minute / IP).
- Returns aggregate-only data — see schema below. The controller's docblock states the PII contract; tests should fail if individual ticket content ever leaks.
- Uses two status sets:
  - `ACTIVE_STATUSES = ['open','pending','in_progress']`
  - `PENDING_STATUSES = ACTIVE_STATUSES + ['awaiting_requester_confirmation']`

Response shape:

```json
{
  "data": {
    "generated_at": "2026-05-26T13:30:00+00:00",
    "volumes":   { "open", "pending", "in_progress", "awaiting_confirm",
                   "unassigned", "created_today", "resolved_today", "closed_today", "total_active" },
    "wait":      { "avg_first_response_minutes", "longest_open_minutes",
                   "oldest_open_ticket_number", "oldest_open_priority", "window_label" },
    "sla":       { "sample_window_days", "response_within_sla_pct",
                   "resolution_within_sla_pct", "response_sample_size",
                   "resolution_sample_size", "breached_pending" },
    "by_priority": { "urgent", "high", "medium", "low" },
    "by_category": [ { "id", "name", "open" } ],   // top 8
    "workload":    [ { "id", "name", "open" } ],   // top 8 agents
    "trend":       [ { "day": "YYYY-MM-DD", "created", "resolved" } ],  // 30 days
    "csat":        { "avg_score": null, "responses": 0, "note": "…" }
  }
}
```

**Frontend:** `ScreenDashboardView.vue` polls every 15 s, flips to a "Reconnecting" pip after 60 s of stale data, and is mounted via the `public + chrome: false` route meta.

---

## Audit log & ISO logging

- Every write goes through `AuditLogger`, which inserts a `helpdesk_audit_logs` row with:
  - `actor` (user_id / staff_id),
  - `action` (e.g. `ticket.created`, `ticket.reassigned`, `settings.updated`),
  - `auditable_type/id`,
  - `ip_address`, `user_agent`,
  - `correlation_id` (UUID, also returned as `X-Correlation-ID` response header for tracing),
  - `new_values` JSON containing the diff and a UTC `@timestamp`.
- When `LOG_STACK` includes `iso_json`, the same payload is mirrored to `storage/logs/helpdesk-iso.jsonl` (JSON Lines, structured for ISO/IEC 27001 / 27014 evidence pipelines).
- Audit log is exposed read-only at `GET /api/v1/admin/audit-logs` (paginated, 40/page).

---

## Extending the module

### Adding a new ticket field

1. Migration: add the column to `helpdesk_tickets`.
2. Model: add to `$fillable` (and `$casts` if needed) on `HelpdeskTicket`.
3. Request: extend `StoreTicketRequest::rules()` and `UpdateTicketRequest::rules()`.
4. Resource: surface it from `app/Http/Resources/Api/V1/TicketResource.php`.
5. Frontend: add to `TicketCreateView.vue` and `TicketDetailView.vue`. Update the TypeScript `Ticket` interface in `frontend/src/types/ticket.ts`.

### Adding a new role / permission flag

1. Migration: add a nullable boolean to `helpdesk_profiles` (mirror `can_manage_kb` / `can_reassign_tickets`).
2. Model: extend `HelpdeskProfile::$fillable` and `$casts`, add a helper (e.g. `canDoX(): bool { return $this->role === ROLE_ADMIN || $this->can_do_x === true; }`).
3. Resource: expose the flag in `MeResource` and `AdminAgentResource`.
4. Controller: gate the relevant action and add a `Authorizes…` trait if it's used in many places.
5. Frontend: extend `MeProfile` interface in `stores/auth.ts` and add the toggle to `AgentsManagementPanel.vue`.

### Adding a new public dashboard widget

1. Extend `PublicScreenController` with a private method returning the aggregate. Keep the **no-PII** contract.
2. Slot it into the JSON payload under a new top-level key.
3. Render it in `ScreenDashboardView.vue` (add a card; mind the TV typography scale).

### Adding a webhook channel

1. Add a controller under `app/Http/Controllers/Api/V1/Webhooks/`.
2. Register a public route in `routes/api.php`. Verify the channel's signing scheme inside the controller — **don't** rely on Sanctum.
3. Persist inbound messages in a dedicated archive table (`helpdesk_*_messages`).
4. When you wire ticket creation from inbound messages, reuse `TicketAssignmentService` so routing rules stay consistent.

---

## Operations / runbooks

### Cache & queue

```bash
php artisan config:cache
php artisan route:cache
php artisan queue:work --queue=default,helpdesk
```

`php artisan helpdesk:reference-sync` *(if/when wrapped as an Artisan command)* — the same effect as `POST /admin/reference-sync`.

### Health checks

```bash
curl -fsS http://<host>/staff/helpdesk/backend/api/v1/health | jq .
curl -fsS http://<host>/staff/helpdesk/backend/api/v1/public/screen | jq '.data.generated_at'
```

### Common failure modes

| Symptom | Diagnosis | Fix |
|---|---|---|
| 401 on every API call after SSO | `JWT_SECRET` mismatch between Staff root `.env` and `helpdesk/backend/.env`. | Align secrets; re-deploy both. |
| 403 `helpdesk_error=sso` | Staff session has no permission `85/92/93`. | Grant in Staff RBAC. |
| Directory picker empty | Stale Staff Share API cache, or wrong `STAFF_API_*` creds. | Run **Settings → Jobs → Sync now** and check `storage/logs/laravel.log`. |
| Ticket number collision | Two creates won the race for `helpdesk_ticket_sequences`. | `TicketNumberGenerator` uses `SELECT … FOR UPDATE` inside a transaction — verify your DB engine supports row locks (InnoDB, not MyISAM). |
| `Authorization` header missing inside Apache | `.htaccess` reordered. | The `RewriteRule` for `Authorization` preservation must be **first**. See `backend/.htaccess`. |
| SPA 404s on deep links | Apache rewrite missing. | Verify `helpdesk/.htaccess` exists and `AllowOverride All` is set on the vhost. |
| Public screen reachable but stale | Cache layer in front (CDN, varnish) ignoring `Cache-Control`. | Set `no-store` on `/public/screen` at the CDN, or whitelist `Cache-Control` from Laravel. |

### Backups

The helpdesk DB tables are included in the standard MySQL backup (see [APM backup docs](../apm/README_BACKUP.md) for the rotation scheme — same database).

---

For higher-level context see [ARCHITECTURE.md](./ARCHITECTURE.md); for the SSO + Staff Share API integration see [INTEGRATION.md](./INTEGRATION.md); and for end-user walkthroughs (including step-by-step ticket creation) see [USER_GUIDE.md](./USER_GUIDE.md).
