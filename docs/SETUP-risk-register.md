# Risk Register setup

Sibling CBP app at `/staff/risk-register` (Laravel API + Vue SPA).

## Prerequisites

- MySQL database (can share host with Staff Portal; dedicated `risk_register` DB preferred)
- Staff Portal CBP module `risk_register` + permissions 118–121
- Apache rewrite already maps `risk-register` like helpdesk

## Configure

1. Copy `modules/risk-register/backend/.env.example` → `.env` (or reuse portal DB for local).
2. Set `APP_URL` to the public API base (e.g. `http://localhost/staff/risk-register/backend`).
3. Optional Share API: `STAFF_API_BASE_URL`, `STAFF_API_TOKEN`, `STAFF_API_USERNAME`, `STAFF_API_PASSWORD`.
   If unset and `divisions` exist on the same DB connection, org mirror falls back to local tables.
4. Frontend production env: `VITE_STAFF_PORTAL_BASE_PATH=/staff/risk-register/` and
   `VITE_RISK_REGISTER_API_BASE_URL=/staff/risk-register/backend`.

## Migrate & seed

```bash
cd modules/risk-register/backend
php artisan migrate --force
php artisan db:seed --class=RiskLookupSeeder --force
```

## Import Excel (one-time)

Workbook: `modules/risk-register/Copy of Africa CDC Risk Register Tracker 2026 Categorised.xlsx`

```bash
php artisan risk:import-excel --fresh
```

Owners default to division HOD when the business unit matches. Unmatched BUs are stored on `unmapped_business_unit`.

## SSO

Staff Portal CBP launch posts `staff_sso_jwt` to `/staff/risk-register/backend/sso/accept`.
The bridge stores `risk_register_api_token` for the SPA Bearer auth.

## Build SPA

```bash
cd modules/risk-register/frontend
npm ci
npm run build
```

Serve via `modules/risk-register/spa-static.php` (see module `.htaccess`).

## Operator checklist

- [ ] Assign risk permissions / groups in Staff Portal
- [ ] Set division Risk Focal Persons
- [ ] Configure SM Focal + Extra assignees on `/workflows`
- [ ] Run Excel import once; fix unmatched BUs
- [ ] Smoke: list risks, open drill-down, dashboard charts (Highcharts credits off)
