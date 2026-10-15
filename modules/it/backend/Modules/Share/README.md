# Staff Share API

Laravel port of the legacy `/staff/share` reference endpoints used by APM, Helpdesk, and Finance sync/warm commands.

Public paths (via root `.htaccess` rewrite or direct backend):

- `/staff/share/…` → `/staff/backend/share/…`
- `/staff/backend/share/…`

## Endpoints

| Method | Path | Payload |
|--------|------|---------|
| GET | `/share/get_current_staff` | JSON **array** of staff+contract rows (+ `associated_divisions`) |
| GET | `/share/divisions` | JSON **array** of `divisions` rows |
| GET | `/share/directorates` | JSON **array** of directorates + nested `director` |
| GET | `/share/users` | Users list (Share reference) |
| GET | `/share/cbp_modules` | CBP modules launcher data |
| GET | `/share/get_signature` | Signature binary/metadata |
| GET | `/share/get_photo` | Photo binary/metadata |
| GET | `/share/helpdesk_agents_in_divisions` | Helpdesk agents by division |
| POST | `/share/mark_helpdesk_agents` | Mark helpdesk agents |
| POST | `/share/token` | Issue JWT (`access_token`) via HTTP Basic |
| POST | `/share/refresh_token` | Refresh JWT |
| GET | `/share/docs` | Swagger UI |
| GET | `/share/openapi.yaml` | OpenAPI 3 spec |

Optional path segment `/{STAFF_API_TOKEN}` is accepted (APM URL style).

Also exposed as REST under `/api/v1/…` where the SPA/Share module registers JSON routes (e.g. `GET /api/v1/cbp-modules`).

## Auth (any one)

1. **HTTP Basic** — `work_email` + password (`api_login` parity)
2. **Bearer JWT** — from `POST /share/token` or Staff SSO JWT (`JWT_SECRET`)
3. **Static token** — `Authorization: Bearer {STAFF_API_TOKEN}` or path token

## Consumer config (APM / Helpdesk / Finance)

Point sync at Laravel Share (recommended):

```env
# Host Apache
STAFF_API_INTERNAL_BASE_URL=http://localhost/staff/backend

# Inside Docker Compose network
STAFF_API_INTERNAL_BASE_URL=http://web/staff/backend

STAFF_API_TOKEN=your-static-token
STAFF_API_USERNAME=your@email
STAFF_API_PASSWORD=secret
```

Root `.htaccess` rewrites `/staff/share/{get_current_staff,divisions,directorates,users,cbp_modules,…}` to Laravel so consumers that still use `BASE_URL=http://localhost/staff/` keep working for those paths.

## Docs

Open [Swagger UI](http://localhost/staff/backend/share/docs).
