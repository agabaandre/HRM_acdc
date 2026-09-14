# Central Business Platform (CBP) Documentation

Welcome to the Africa CDC Central Business Platform (CBP) documentation hub for the staff management system: **Staff Portal** (Laravel + Vue), **APM** (Laravel), **Finance** (Laravel + Inertia), and **Helpdesk** (Laravel + Vue).

## 📚 Platform Overview

1. **Staff Portal** (Laravel 12 + Vue 3) — Auth, directory, leave, payroll, performance, Share API, CBP module launcher  
2. **APM** (Laravel) — Approvals, matrices, document workflows  
3. **Finance** (Laravel + Inertia/React) — Advances, missions, budgets  
4. **Helpdesk** (Laravel 11 + Vue) — Ticketing, SLA, KB, public TV dashboard  

Apps live under `modules/`. Public URLs stay `/staff/`, `/staff/backend`, `/staff/apm`, `/staff/finance`, `/staff/helpdesk`.

## 🏗️ System Architecture

```
staff/
├── .htaccess                 # Maps /staff/* into modules/
├── modules/
│   ├── staff-portal/         # Laravel API + Vue SPA
│   ├── apm/                  # Approvals Management
│   ├── finance/              # Finance (Inertia)
│   └── helpdesk/             # Helpdesk / ITSM
├── shared/                   # StaffStorage, SCRIPT_NAME helper
├── docker/                   # CBP Compose image
├── docs/                     # STORAGE.md, CI.md, …
└── documentation/            # This hub
```

## 📖 Module Documentation

### 1. Staff Portal (Laravel + Vue)

Core portal for authentication, staff directory, HR workflows, and the Share reference API used by sibling apps.

**URLs:** `/staff/` (SPA) · `/staff/backend/` (API) · `/staff/share/…` (rewritten to backend)

**Documentation:**
- [Staff Portal README](../modules/staff-portal/README.md) — layout, setup, env, deploy  
- [Share API](../modules/staff-portal/backend/Modules/Share/README.md) — sync endpoints for APM/Helpdesk/Finance  
- [Systemd workers](../modules/staff-portal/docs/SYSTEMD.md) — queue + scheduler  
- [OAuth / OIDC clients](../modules/staff-portal/docs/oauth-oidc-clients.md)  
- [File storage & uploads](../docs/STORAGE.md)  
- [Environment variables (legacy notes)](../assets/ENVIRONMENT_VARIABLES.md)  

### 2. APM (Approvals Management)

Laravel system for approvals, workflows, and document processing.

> 📚 **Complete APM Documentation**: [modules/apm/documentation/README.md](../modules/apm/documentation/README.md)

**Quick Links:**
- [Approval Trail Management](../modules/apm/documentation/APPROVAL_TRAIL_MANAGEMENT.md)
- [Document Numbering System](../modules/apm/documentation/DOCUMENT_NUMBERING_SYSTEM.md)
- [Firebase / FCM push notifications](../modules/apm/documentation/FIREBASE_PUSH_NOTIFICATIONS.md)
- [Queue Setup Guide](../modules/apm/documentation/QUEUE_SETUP_GUIDE.md)
- [Systemd Queue Guide](../modules/apm/documentation/SYSTEMD_QUEUE_GUIDE.md)
- [Deployment Guide](../modules/apm/documentation/DEPLOYMENT.md)
- [Cron Setup](../modules/apm/documentation/CRON_SETUP.md)
- [Database Backup System](../modules/apm/README_BACKUP.md)

### 3. Finance Module

Laravel + Inertia/React for finance management.

> 📚 **Complete Finance Documentation**: [modules/finance/documentation/README.md](../modules/finance/documentation/README.md)

**Quick Links:**
- [Frontend Architecture](../modules/finance/documentation/FRONTEND_ARCHITECTURE.md)
- [Server Architecture](../modules/finance/documentation/SERVER_ARCHITECTURE.md)
- [Installation Guide](../modules/finance/documentation/INSTALLATION.md)
- [Migrations Guide](../modules/finance/documentation/MIGRATIONS.md)
- [Session Implementation](../modules/finance/documentation/SESSION_IMPLEMENTATION.md)

### 4. Helpdesk Module

Laravel 11 JSON API + Vue 3.5 SPA: ticketing, SLA, agent routing, knowledge base, public TV dashboard, ISO audit logging.

> 📚 **Complete Helpdesk Documentation**: [modules/helpdesk/documentation/README.md](../modules/helpdesk/documentation/README.md)

**Quick Links:**
- [User Guide](../modules/helpdesk/documentation/USER_GUIDE.md)
- [Developer Guide](../modules/helpdesk/documentation/DEVELOPER_GUIDE.md)
- [Architecture](../modules/helpdesk/documentation/ARCHITECTURE.md)
- [Integration](../modules/helpdesk/documentation/INTEGRATION.md)
- [OpenAPI stub](../modules/helpdesk/documentation/openapi.yaml)
- [Quick Start](../modules/helpdesk/README.md)

## 🚀 Getting Started

### For New Developers

1. **Staff Portal**
   - Read [modules/staff-portal/README.md](../modules/staff-portal/README.md)
   - `cd modules/staff-portal && ./setup.sh`
   - Match `JWT_SECRET` across all CBP apps

2. **APM**
   - [APM Documentation](../modules/apm/documentation/README.md)
   - `cd modules/apm && composer install` …
   - Point `STAFF_API_INTERNAL_BASE_URL` at `/staff/backend`

3. **Finance**
   - [Finance Documentation](../modules/finance/documentation/README.md)
   - `cd modules/finance && ./setup.sh`

4. **Helpdesk**
   - [Developer Guide](../modules/helpdesk/documentation/DEVELOPER_GUIDE.md)
   - Configure `/staff/helpdesk/` + `/staff/helpdesk/backend/`
   - Match `JWT_SECRET` and Staff Share API settings

### For System Administrators

1. **Production Deployment**
   - [Staff Portal README — Deployment](../modules/staff-portal/README.md#deployment)
   - [APM Deployment Guide](../modules/apm/documentation/DEPLOYMENT.md)
   - Optional: [Docker](../docker/README.md) · [CI / GHCR](../docs/CI.md)

2. **Infrastructure**
   - [APM Queue Setup](../modules/apm/documentation/QUEUE_SETUP_GUIDE.md)
   - [Staff Portal systemd](../modules/staff-portal/docs/SYSTEMD.md)
   - [APM Cron](../modules/apm/documentation/CRON_SETUP.md)

## 🔗 Integration Points

### Session Management

Authentication is owned by the **Staff Portal** Laravel app:

- **Staff Portal**: Primary auth (Sanctum / Microsoft OAuth) + SSO JWT (`JWT_SECRET`)
- **APM / Finance / Helpdesk**: POST SSO launch; background refresh via Staff `/auth/refresh_sso_session` (and app-specific refresh routes)

Cross-module client: `assets/js/cbp-session-refresh.js` (when present).

See:
- [Finance Session Implementation](../modules/finance/documentation/SESSION_IMPLEMENTATION.md)
- [APM Session Expiry Setup](../modules/apm/documentation/SESSION_EXPIRY_SETUP.md)
- [Helpdesk Integration (SSO + Staff Share API)](../modules/helpdesk/documentation/INTEGRATION.md)
- [Share API](../modules/staff-portal/backend/Modules/Share/README.md)

### Navigation Integration

- Staff Portal → APM / Finance / Helpdesk (`POST` launch module / SSO hand-off)
- Sibling apps → Staff Portal (CBP Home `/staff/`)

### Permission System

Shared permissions from Staff Portal (examples):

- Permission 92: Finance access  
- Permission 93: Finance settings  
- Permission 85 / 92 / 93: Helpdesk access (configurable)  
- Various APM workflow permissions  

## 📝 Common Tasks

### Setting Up Development Environment

1. **Staff Portal**
   ```bash
   cd modules/staff-portal
   ./setup.sh
   # or: npm run install:all && cd backend && php artisan migrate
   ```

2. **APM**
   ```bash
   cd modules/apm
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   ```

3. **Finance**
   ```bash
   cd modules/finance
   ./setup.sh
   ```

4. **Helpdesk**
   ```bash
   cd modules/helpdesk
   ./setup.sh
   ```

### Production Deployment

1. Configure Apache DocumentRoot / Alias so `/staff` is the CBP repo root (see root `.htaccess`).
2. Deploy each module under `modules/`; run `setup-production.sh` where provided.
3. Queue workers: APM + Helpdesk (+ Staff Portal systemd if used).
4. Optional container path: [docker/README.md](../docker/README.md).

## 🐛 Troubleshooting

1. **Session not transferring between modules** — verify shared `JWT_SECRET`; check SSO launch + refresh routes.  
2. **Share / sync failures** — confirm `STAFF_API_INTERNAL_BASE_URL` ends at `/staff/backend` (not `/staff` or `/staff/staff-portal/backend`).  
3. **SPA 404 / asset 500** — rebuild + `./scripts/publish-spa.sh` in `modules/staff-portal`.  
4. **Queue workers** — [APM Queue Troubleshooting](../modules/apm/documentation/QUEUE_TROUBLESHOOTING.md).  

## 📂 Documentation Structure

```
staff/
├── README.md
├── documentation/README.md              # This hub
├── docs/
│   ├── CI.md
│   └── STORAGE.md
├── modules/
│   ├── staff-portal/
│   │   ├── README.md
│   │   ├── docs/SYSTEMD.md
│   │   └── backend/Modules/Share/README.md
│   ├── apm/documentation/
│   ├── finance/documentation/
│   └── helpdesk/documentation/
├── docker/README.md
└── assets/ENVIRONMENT_VARIABLES.md
```

## 🔗 Quick Reference

### Module READMEs
- [Staff Portal](../modules/staff-portal/README.md)
- [APM](../modules/apm/README.md)
- [Finance](../modules/finance/README.md)
- [Helpdesk](../modules/helpdesk/README.md)
- [Root project README](../README.md)

### Documentation Indexes
- [APM](../modules/apm/documentation/README.md)
- [Finance](../modules/finance/documentation/README.md)
- [Helpdesk](../modules/helpdesk/documentation/README.md)

### Key configuration
- [docs/STORAGE.md](../docs/STORAGE.md)
- [docs/CI.md](../docs/CI.md)
- [docker/README.md](../docker/README.md)

### Operations scripts

| Script | Purpose |
|--------|---------|
| [scripts/storage/migrate-all.sh](../scripts/storage/migrate-all.sh) | Copy uploads to `/var/staffdata/{site-id}/` |
| [scripts/storage/fix-staff-storage-permissions.sh](../scripts/storage/fix-staff-storage-permissions.sh) | Host data directory permissions |
| [scripts/fix-laravel-storage-permissions.sh](../scripts/fix-laravel-storage-permissions.sh) | Laravel `storage/` under modules |
| [modules/apm/fix-storage-permissions.sh](../modules/apm/fix-storage-permissions.sh) | APM storage permissions |

## 📞 Support

1. Check the relevant module documentation  
2. Review troubleshooting above  
3. Logs:
   - Staff Portal: `modules/staff-portal/backend/storage/logs/`
   - APM: `modules/apm/storage/logs/`
   - Finance: `modules/finance/storage/logs/`
   - Helpdesk: `modules/helpdesk/backend/storage/logs/`

---

**Last Updated**: September 2026  
**Layout**: `modules/` monorepo (stable `/staff/*` URLs)
