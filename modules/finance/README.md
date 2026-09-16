# Africa CDC Finance

Sibling CBP app at `/staff/finance` (Laravel + Vue), SSO-launched from Staff Portal.

| URL | Serves |
|-----|--------|
| `/staff/finance/` | Vue SPA (Funding Portfolio) |
| `/staff/finance/backend/` | Laravel API + `POST /sso/accept` |
| `/staff/apm/intramural-sap-budget-execution` | APM Intramural SAP Budget Execution dashboard |

## Features

- **Funding Portfolio** dashboard (Highcharts, credits disabled)
- **Portfolio data entry** tabs (Extramural, IC, Admin, Investments, AfEF) with demo seed
- **SAP import** — stores full export; Total rows sync to APM fund codes

## Env (Finance backend)

```
APM_BASE_URL=http://localhost/staff/apm
APM_API_PREFIX=/api/apm/v1
APM_API_EMAIL=
APM_API_PASSWORD=
```

## Local

1. Point `backend/.env` `APP_URL` at `http://localhost/staff/finance/backend`
2. Share `JWT_SECRET` with Staff Portal
3. `php artisan migrate` + `db:seed --class=FinancePortfolioDemoSeeder`
4. Build SPA: `cd frontend && npm run build`
5. Open from Staff Portal CBP Modules → Finance

## Design / plan

- Spec: `docs/superpowers/specs/2026-09-17-finance-funding-portfolio-sap-design.md`
- Plan: `docs/superpowers/plans/2026-09-17-finance-funding-portfolio-sap.md`
