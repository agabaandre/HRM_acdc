# Staff Share API (APM)

Laravel port of CI3 `/staff/share` reference endpoints used by APM sync commands.

## Endpoints

| Method | Path | Payload |
|--------|------|---------|
| GET | `/share/get_current_staff` | JSON **array** of staff+contract rows (+ `associated_divisions`) |
| GET | `/share/divisions` | JSON **array** of `divisions` rows |
| GET | `/share/directorates` | JSON **array** of directorates + nested `director` |
| POST | `/share/token` | Issue JWT (`access_token`) via HTTP Basic |
| GET | `/share/docs` | Swagger UI |
| GET | `/share/openapi.yaml` | OpenAPI 3 spec |

Optional path segment `/{STAFF_API_TOKEN}` is accepted (APM URL style).

## Auth (any one)

1. **HTTP Basic** — `work_email` + password (CI `api_login` parity)
2. **Bearer JWT** — from `POST /share/token` or Staff SSO JWT (`JWT_SECRET`)
3. **Static token** — `Authorization: Bearer {STAFF_API_TOKEN}` or path token

## APM config

Point sync at Laravel (recommended):

```env
STAFF_API_INTERNAL_BASE_URL=http://localhost/staff/staff-portal/backend
STAFF_API_TOKEN=YWZyY2FjZGNzdGFmZnRyYWNrZXI
STAFF_API_USERNAME=your@email
STAFF_API_PASSWORD=secret
```

Root `.htaccess` also rewrites `/staff/share/get_current_staff|divisions|directorates|…` to Laravel so existing `BASE_URL=http://localhost/staff/` keeps working for those paths.

## Docs

Open [Swagger UI](http://localhost/staff/staff-portal/backend/share/docs).
