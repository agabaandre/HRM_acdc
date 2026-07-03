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

The Staff portal currently hands off to `/staff/helpdesk?token=…`. To test helpdesk2 with SSO, open:

`/staff/helpdesk2/?token=<jwt-from-portal>`

Or update `Home.php` when ready to switch the default module link.
