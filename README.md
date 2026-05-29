<div align="center">

# 🌍 Africa CDC CBP
## Central Business Platform

**Africa CDC Staff Portal & Matrix Approval Management System**

![Landing Page](./assets/images/landing.png)

[![Documentation](https://img.shields.io/badge/Documentation-Complete-blue)](./documentation/README.md)
[![Staff Portal](https://img.shields.io/badge/Staff%20Portal-CodeIgniter-orange)](./application/)
[![APM](https://img.shields.io/badge/APM-Laravel-red)](./apm/)
[![Finance](https://img.shields.io/badge/Finance-Laravel%2BInertia-green)](./finance/)
[![Helpdesk](https://img.shields.io/badge/Helpdesk-Laravel%2BVue-teal)](./helpdesk/)

</div>

---

## 📋 Overview

The **Africa CDC Central Business Platform (CBP)** is a centralized platform designed to manage staff information, monitor task progress, and streamline approvals across divisions. It enables efficient performance tracking, leave management, and weekly activity planning to support organizational goals.

Integrated with the Matrix Approval Management module, the system ensures structured oversight of planned activities and budget allocations. Division focal persons can submit quarterly matrices outlining key deliverables, which then follow a defined multi-level approval workflow involving directors and senior management.

This system enhances **transparency**, **accountability**, and **timely decision-making** across Africa CDC's internal operations.

---

## 🏗️ System Architecture

The platform consists of four integrated modules working seamlessly together:

<div align="center">

### 🎯 Platform Modules

<table>
<tr>
<td align="center" width="25%">

### 👥 Staff Portal
**CodeIgniter-based Core System**

![Staff Portal](./assets/images/staffportal.png)

**Features:**
- ✅ User authentication & session management
- ✅ Staff profiles & directory
- ✅ Permission management
- ✅ Base infrastructure for all modules

[📖 Documentation](./application/) | [⚙️ Config](./assets/ENVIRONMENT_VARIABLES.md)

</td>
<td align="center" width="25%">

### 📋 APM
**Approvals Management System**

![APM](./assets/images/image.png)

**Features:**
- ✅ Approval workflows & matrices
- ✅ Document processing & numbering
- ✅ Budget approvals
- ✅ Activity tracking
- ✅ **REST API** (JWT) – pending approvals, documents with approval trails & attachment URLs, actions, memo list

[📖 Documentation](./apm/documentation/) | [📡 API Docs](./apm/documentation/API_DOCUMENTATION.md) | [🚀 Quick Start](./apm/README.md)

</td>
<td align="center" width="25%">

### 💰 Finance Module
**Modern Finance Management**

![Finance](./assets/images/finance.png)

**Features:**
- ✅ Staff advances
- ✅ Mission budgets
- ✅ Financial tracking
- ✅ Budget management

[📖 Documentation](./finance/documentation/) | [🚀 Quick Start](./finance/README.md)

</td>
<td align="center" width="25%">

### 🛎️ Helpdesk
**IT Service Desk & ITSM**

**Features:**
- ✅ Ticketing (web, WhatsApp, Teams sources)
- ✅ SLA targets, agent routing, AI signals
- ✅ Knowledge base + searchable FAQs
- ✅ Agent desk, reassignment, reports
- ✅ Public TV / lobby dashboard (no auth, no PII)
- ✅ ISO 27001 / 27014 audit logging

[📖 Documentation](./helpdesk/documentation/README.md) | [👤 User Guide](./helpdesk/documentation/USER_GUIDE.md) | [🧑‍💻 Developer Guide](./helpdesk/documentation/DEVELOPER_GUIDE.md) | [🚀 Quick Start](./helpdesk/README.md)

</td>
</tr>
</table>

</div>

---

## 🚀 Quick Start

### 👨‍💻 For Developers

<details>
<summary><b>Docker (Staff + APM)</b></summary>

- Full guide: [docker/README.md](./docker/README.md)
- Requires Docker Desktop or Docker Engine running (fix “Cannot connect to the Docker daemon” by starting Docker).
- By default the stack uses **MySQL on the physical host**: set `DB_HOST=host.docker.internal` (and matching DB names/users) in root `.env` and `apm/.env`.
- The `web` image includes **Ghostscript**, **Poppler**, and **LibreOffice** for APM PDF annex embedding (scanned PDFs and Word attachments). Rebuild after updates: `docker compose up -d --build`.

```bash
cp docker/compose.env.example .env
docker compose up -d --build
```

- Staff portal: `http://localhost:8080/` · APM: `http://localhost:8080/apm`

</details>

<details>
<summary><b>1. Set up Staff Portal (CodeIgniter)</b></summary>

- Configure database in `application/config/database.php`
- Review [Environment Variables](./assets/ENVIRONMENT_VARIABLES.md)
- Set up authentication and permissions

</details>

<details>
<summary><b>2. Set up APM Module (Laravel)</b></summary>

```bash
cd apm
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

📖 See [APM Documentation](./apm/documentation/README.md) for details.

</details>

<details>
<summary><b>3. Set up Finance Module (Laravel + Inertia)</b></summary>

```bash
cd finance
./setup.sh
```

📖 See [Finance Documentation](./finance/documentation/README.md) for details.

</details>

<details>
<summary><b>4. Set up Helpdesk Module (Laravel + Vue)</b></summary>

```bash
cd helpdesk
./setup.sh
# Backend
cd backend && composer install \
  && cp .env.example .env \
  && php artisan key:generate \
  && php artisan migrate --seed
# Frontend (built SPA served by Apache at /staff/helpdesk/)
cd ../frontend && npm install --cache ./.npm-cache --legacy-peer-deps \
  && npm run build
```

Smoke-test:

```bash
curl -i http://localhost/staff/helpdesk/                                # SPA
curl -i http://localhost/staff/helpdesk/backend/api/v1/health           # API
curl -i http://localhost/staff/helpdesk/backend/api/v1/public/screen    # Public TV dashboard
```

📖 See [Helpdesk Documentation](./helpdesk/documentation/README.md) (start with the [User Guide](./helpdesk/documentation/USER_GUIDE.md) or [Developer Guide](./helpdesk/documentation/DEVELOPER_GUIDE.md)).

</details>

### 🔧 For System Administrators

<details>
<summary><b>Production Deployment</b></summary>

1. Review [APM Deployment Guide](./apm/documentation/DEPLOYMENT.md)
2. Configure reverse proxy (see Finance docs)
3. Set up queue workers and cron jobs
4. Configure [Queue Setup](./apm/documentation/QUEUE_SETUP_GUIDE.md)
5. Set up [Cron Configuration](./apm/documentation/CRON_SETUP.md)
6. Configure Staff Portal scheduler (single cron entry):

```bash
* * * * * /usr/bin/php /path/to/staff/index.php jobs/run/tick >> /path/to/staff/application/logs/cron-tick.log 2>&1
```

Default scheduler timings are configured in `application/modules/jobs/controllers/Run.php`:
- `performance_notifications` at `07:00`
- `performance_approval_reminder` at `10:00` (daily pending approvals reminder)
- `mark_due_contracts` at `23:00`
- `staff_birthday` at `03:00`

</details>

---

## 📚 Documentation

<div align="center">

| 📖 Documentation | 📝 Description |
|-----------------|----------------|
| [**📚 Main Documentation Hub**](./documentation/README.md) | Central documentation for the entire platform |
| [**👥 Staff Portal Docs**](./assets/ENVIRONMENT_VARIABLES.md) | Configuration and setup guides |
| [**📋 APM Documentation**](./apm/documentation/README.md) | Laravel Approvals Management System |
| [**📡 APM API Documentation**](./apm/documentation/API_DOCUMENTATION.md) | REST API (JWT), endpoints, approval trails, attachments, Swagger at `/docs` |
| [**🌱 APM Environment Guide**](./apm/documentation/ENVIRONMENT.md) | `.env` setup and variable reference (`apm/.env.example`) |
| [**💰 Finance Documentation**](./finance/documentation/README.md) | Laravel + Inertia Finance Module |
| [**🛎️ Helpdesk Documentation**](./helpdesk/documentation/README.md) | IT Service Desk / ITSM module (Laravel + Vue) — index of all helpdesk docs |
| [**👤 Helpdesk User Guide**](./helpdesk/documentation/USER_GUIDE.md) | Requesters, agents & admins; includes step-by-step ticket creation |
| [**🧑‍💻 Helpdesk Developer Guide**](./helpdesk/documentation/DEVELOPER_GUIDE.md) | Architecture, schema, REST API, extension points & runbooks |

</div>

### 🔑 Key Guides

**APM API (integrations & approver apps):**
- [APM API Documentation](./apm/documentation/API_DOCUMENTATION.md) - Auth, endpoints, approval trails, attachments, examples
- [OpenAPI/Swagger spec](./apm/documentation/APM_API_OPENAPI.yaml) - Full request/response schemas; interactive docs at `/docs` when APM is running

**Infrastructure & Setup:**
- [Environment Variables](./assets/ENVIRONMENT_VARIABLES.md) - Configuration guide
- [APM Environment Guide](./apm/documentation/ENVIRONMENT.md) - `.env` setup and examples
- [APM Queue Setup](./apm/documentation/QUEUE_SETUP_GUIDE.md) - Queue worker configuration
- [Systemd Queue Guide](./apm/documentation/SYSTEMD_QUEUE_GUIDE.md) - Systemd queue management
- [Cron Configuration](./apm/documentation/CRON_SETUP.md) - Scheduled tasks
- [Database Backup System](./apm/README_BACKUP.md) - Automatic database backups and retention policies

**Operations (Staff Portal jobs):**
- `php index.php jobs/run/tick` - single entry-point scheduler for recurring jobs
- `php index.php jobs/run/performance_notifications` - queue PPA/Midterm/Endterm reminder emails
- `php index.php jobs/run/performance_approval_reminder` - queue + send daily performance approval reminder

**Architecture & Development:**
- [Finance Quick Start](./finance/documentation/QUICKSTART.md) - Install and SSO
- [Finance Laravel + Inertia](./finance/documentation/LARAVEL_INERTIA.md) - UI and adding pages
- [Auth Module Improvements](./application/modules/auth/README_IMPROVEMENTS.md) - Authentication features

---

## 🔗 Integration

All modules are seamlessly integrated through:

<div align="center">

| 🔐 **Session Management** | 🧭 **Navigation** | 🔒 **Permissions** |
|---------------------------|-------------------|-------------------|
| Shared authentication via Staff Portal | Cross-module navigation with token-based session transfer | Unified permission system across all modules |

</div>

See [documentation/README.md](./documentation/README.md) for detailed integration information.

---

## 📂 Project Structure

```
staff/
├── 📄 README.md                         # This file
├── 📚 documentation/                    # Main documentation hub
│   └── README.md                        # Central documentation index
├── 👥 application/                      # CodeIgniter Staff Portal
│   ├── modules/                         # Application modules (auth, share, staff, …)
│   │   └── share/                       # Share API (users, divisions, directorates, get_current_staff)
│   ├── config/                          # Database, routes, etc.
│   └── ...
├── 📋 apm/                              # Laravel APM module
│   ├── app/
│   │   ├── Http/Controllers/Api/        # APM API controllers (auth, documents, actions, …)
│   │   ├── Models/                      # Eloquent models (ApmApiUser, SpecialMemo, …)
│   │   ├── Services/                    # PendingApprovalsService, ApprovalService, …
│   │   └── Console/Commands/             # users:sync, divisions:sync, …
│   ├── routes/
│   │   ├── api.php                      # APM API routes (/api/apm/v1/…)
│   │   └── web.php                     # Web routes, /docs (Swagger UI)
│   ├── documentation/                   # APM documentation
│   │   ├── README.md                    # APM docs index
│   │   ├── API_DOCUMENTATION.md         # REST API guide (auth, endpoints, attachments)
│   │   ├── APM_API_OPENAPI.yaml         # OpenAPI 3.0 spec (Swagger)
│   │   ├── DEPLOYMENT.md, CRON_SETUP.md # Operations
│   │   └── ...                          # Approval trails, queues, etc.
│   ├── public/                          # Web root (storage link for uploads)
│   └── README.md                        # APM quick start
├── 💰 finance/                          # Laravel + Inertia Finance (like apm/)
│   ├── app/, routes/, resources/js/     # Application code
│   ├── public/, server.php, .htaccess   # Apache entry
│   ├── documentation/                   # Finance documentation
│   └── setup.sh                         # Install + build
├── 🛎️ helpdesk/                          # Laravel + Vue IT Service Desk / ITSM
│   ├── backend/                         # Laravel 11 JSON API (/api/v1/*)
│   │   ├── app/Http/Controllers/Api/V1  # Tickets, KB, reports, public screen, admin
│   │   ├── app/Models/                  # HelpdeskTicket, HelpdeskCategory, HelpdeskProfile, …
│   │   └── routes/api.php               # All REST endpoints
│   ├── frontend/                        # Vue 3.5 + Pinia SPA
│   │   ├── src/views/                   # Home, Tickets, AgentDashboard, Reports, ScreenDashboard, …
│   │   └── src/components/              # Layout + settings panels
│   ├── documentation/                   # Helpdesk documentation
│   │   ├── README.md                    # Index
│   │   ├── USER_GUIDE.md                # End-user walkthroughs (incl. ticket creation)
│   │   ├── DEVELOPER_GUIDE.md           # Architecture, schema, API, extension points
│   │   ├── ARCHITECTURE.md
│   │   ├── INTEGRATION.md
│   │   └── openapi.yaml
│   └── README.md                        # Helpdesk quick start
├── 🎨 assets/                            # Shared assets
│   └── images/                          # Images and graphics
└── ⚙️ system/                            # CodeIgniter system files
```

---

## 🎯 Features

<div align="center">

| ✨ Feature | 📋 Description |
|-----------|----------------|
| 🔐 **Unified Authentication** | Single sign-on across all modules |
| 📊 **Approval Workflows** | Multi-level approval processes |
| 🧾 **Staff History Reporting** | Contract-overlap history report with period filters and CSV/PDF export |
| 📡 **APM REST API** | JWT API for pending approvals, documents (with approval trails & attachment URLs), actions, memo list |
| 📲 **APM Notifications API** | `/me/notifications`, `/read-all`, and per-notification read endpoints |
| ⏰ **Performance Approval Reminder** | Daily reminder at 10:00 to first/second approvers based on pending approvals |
| 💰 **Financial Management** | Advances, budgets, and tracking |
| 👥 **Staff Management** | Profiles, contracts, and HR services |
| 📈 **Performance Tracking** | Task monitoring and reporting |
| 🔔 **Notifications** | Scheduled and event-driven alerts across Staff Portal and APM |
| 🛎️ **Helpdesk / ITSM** | Tickets with SLA, agent routing, knowledge base, public TV dashboard, ISO 27001/27014 audit logging |

</div>

---

## 🛠️ Technology Stack

<div align="center">

| Module | Backend | Frontend | Database |
|--------|---------|----------|----------|
| **Staff Portal** | CodeIgniter 3 | Bootstrap 5 | MySQL |
| **APM** | Laravel 10+ | Blade Templates | MySQL |
| **Finance** | Laravel 12 | React (Inertia) | MySQL |
| **Helpdesk** | Laravel 11 | Vue 3.5 + Pinia (Vite) | MySQL + Redis (Predis) |

</div>

---

## ⚙️ System requirements

### APM (PDF printouts with attachments)

Memo and activity PDFs (mPDF) can append uploaded attachments to the printout. **Scanned or image-only PDF attachments** need system tools beyond PHP; mPDF/FPDI alone cannot import them.

Install on the **application server** (production and any environment where memo PDFs are generated):

| Package | Commands / binaries | Purpose |
|---------|---------------------|---------|
| **Ghostscript** (recommended) | `gs` | Re-publish PDFs for import; rasterize scanned PDF pages to images for embedding |
| **Poppler** (recommended backup) | `pdftoppm` | Rasterize PDF pages when Ghostscript is unavailable |
| **LibreOffice** (recommended) | `libreoffice`, `soffice` | Convert Word attachments (`.doc`, `.docx`) to PDF for the annex |
| **PHP Imagick** (optional) | `imagick` extension | Alternative rasterization when ImageMagick is built with PDF support |

**Debian / Ubuntu:**

```bash
sudo apt update
sudo apt install ghostscript poppler-utils libreoffice-writer
# Optional: sudo apt install php-imagick && sudo phpenmod imagick
```

**RHEL / AlmaLinux / Rocky:**

```bash
sudo dnf install ghostscript poppler-utils libreoffice-writer
# Optional: sudo dnf install php-pecl-imagick
```

**macOS (Homebrew):**

```bash
brew install ghostscript poppler libreoffice
# Optional: pecl install imagick
```

Ensure `gs`, `pdftoppm`, and `libreoffice` / `soffice` are on the **same `PATH`** as the PHP process (web server, PHP-FPM, and queue workers if PDFs are generated from jobs).

Without Ghostscript/Poppler, vector PDFs may still embed; scanned PDF attachments may show an embed failure message in the annex. Without LibreOffice, Word (`.doc`/`.docx`) attachments are listed in the annex index but not rendered as pages.

See also [APM deployment](./apm/documentation/DEPLOYMENT.md) and [APM README](./apm/README.md#system-requirements).

---

## 📞 Support

For issues or questions:

1. 📖 Check the relevant module documentation
2. 🔍 Review [Main Documentation Hub](./documentation/README.md)
3. 📋 Check application logs
4. 🐛 Review troubleshooting guides

---

<div align="center">

**Version**: 1.0.0  
**Last Updated**: 2026

---

Made with ❤️ for Africa CDC

[⬆ Back to Top](#-africa-cdc-cbp)

</div>
