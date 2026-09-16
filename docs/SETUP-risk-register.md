# Risk Register setup

Sibling CBP app at `/staff/risk-register` (Laravel API + Vue SPA).

## Prerequisites

- MySQL database (can share host with Staff Portal; dedicated `risk_register` DB preferred)
- Staff Portal CBP module `risk_register` + permissions 118–121
- Apache rewrite already maps `risk-register` like helpdesk

## Configure

1. Copy `modules/risk-register/backend/.env.example` → `.env`.
2. Default databases: production `risk_register`, demo `demo_risk_register` (override `DB_DATABASE` only if needed).
3. Set `APP_URL` to the public API base (e.g. `http://localhost/staff/risk-register/backend`).
4. Optional Share API: `STAFF_API_BASE_URL`, `STAFF_API_TOKEN`, `STAFF_API_USERNAME`, `STAFF_API_PASSWORD`.
   If unset and `divisions` exist on a linked Staff Portal connection, org mirror can fall back to local tables.
5. Frontend production env: `VITE_STAFF_PORTAL_BASE_PATH=/staff/risk-register/` and
   `VITE_RISK_REGISTER_API_BASE_URL=/staff/risk-register/backend`.

## Migrate & seed

```bash
cd modules/risk-register/backend
php artisan migrate --force
php artisan db:seed --class=RiskLookupSeeder --force
```

## Import Excel (one-time, UI preferred)

Use **Import** in the SPA (`manage_risks`): upload → preview → import matched → map unmatched BUs → apply.

CLI still available:

```bash
php artisan risk:import-excel --fresh
```

Disable further imports under **Settings** (hides Import nav and blocks API).

## Module DB naming convention

| App | Production | Demo |
|-----|------------|------|
| Staff Portal | `staff_tracker` | `demo_staff_tracker` |
| APM | `approvals_management` | `demo_approvals_management` |
| Helpdesk | `helpdesk` | `demo_helpdesk` |
| Finance | `finance` | `demo_finance` |
| Risk Register | `risk_register` | `demo_risk_register` |

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
