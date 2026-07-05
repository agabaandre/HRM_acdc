# Helpdesk Client (MaterialPro shell)

MaterialPro-inspired UI for Africa CDC Helpdesk. Runs alongside the original SPA at `helpdesk/frontend/` without replacing it.

## URLs

| Environment | URL |
|-------------|-----|
| Production | `http://<host>/staff/helpdesk2/` |
| Original UI | `http://<host>/staff/helpdesk/` |
| Shared API | `http://<host>/staff/helpdesk/backend/api/v1/*` |

## What changed vs `frontend/`

- **Kept:** CBP top header (Staff portal bar), all routes, stores, API calls, kanban logic, screen dashboard
- **New:** MaterialPro-style card polish; same horizontal primary nav as the original helpdesk (below the CBP top bar)
- **Colors:** Same Africa CDC green palette (`#119a48`, `#0d7a3a`)

## Development

```bash
cd helpdesk/client
npm install --legacy-peer-deps
npm run dev
# http://localhost:5175 — API proxied to /staff/helpdesk/backend
```

## Production build

```bash
cd helpdesk/client
npm run build
# Output: helpdesk/client/dist/
# Served by /staff/helpdesk2/.htaccess
```

## Staff portal SSO

Helpdesk uses the same **POST SSO launch** as APM and Finance:

1. Staff portal `POST home/launch_module` with `module_key=helpdesk_itsm`
2. Auto-POST `staff_sso_jwt` to `/staff/helpdesk/backend/sso/accept`
3. Helpdesk verifies JWT and returns a Sanctum session

Legacy dev URL (when `SSO_ALLOW_URL_TOKEN` is enabled):

`/staff/helpdesk2/?token=<jwt-from-portal>`

### Session refresh

While the Helpdesk tab is open, `sessionRefresh.ts` loads `assets/js/cbp-session-refresh.js`, which calls Staff `GET /auth/refresh_sso_session` (same-site cookie) and re-exchanges the JWT via `POST /api/v1/auth/staff-sso` so the Sanctum token stays valid. Requires the user to remain logged into the Staff portal on the same host.

Use **`localhost`** in all `.env` files — not `Users-MacBook-Pro.local`. Rebuild after env changes: `npm run build`.
