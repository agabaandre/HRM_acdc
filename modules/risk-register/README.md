# Africa CDC Risk Register

Sibling CBP app at `/staff/risk-register` (Laravel + Vue), SSO-launched from Staff Portal.

| URL | Serves |
|-----|--------|
| `/staff/risk-register/` | Vue SPA (`spa-static.php` / `public-spa`) |
| `/staff/risk-register/backend/` | Laravel API + `POST /sso/accept` |
| `/staff/risk-register/backend/sso/accept` | CBP SSO accept (staff_sso_jwt) |

See design: `docs/superpowers/specs/2026-09-16-risk-register-design.md`  
Plan: `docs/superpowers/plans/2026-09-16-risk-register.md`

## Local

1. Point `backend/.env` `APP_URL` at `http://localhost/staff/risk-register/backend`
2. Share `JWT_SECRET` with Staff Portal
3. Ensure root `.htaccess` rewrites `risk-register` like helpdesk
4. Open from Staff Portal CBP Modules → Risk Register
