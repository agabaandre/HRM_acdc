# Staff ecosystem file storage

Uploads and generated files for **CodeIgniter staff**, **APM**, **Helpdesk**, and **staff-portal** should live **outside the git repository** so `git pull` and deployments do not overwrite user content.

This document covers host-side storage, migration scripts, permissions, and the Knowledge Hub admin UI.

---

## Table of contents

1. [Architecture](#architecture)
2. [Local development vs production](#local-development-vs-production)
3. [Environment variables](#environment-variables)
4. [Migration (macOS and Ubuntu)](#migration-macos-and-ubuntu)
5. [After migration checklist](#after-migration-checklist)
6. [CodeIgniter cache and logs](#codeigniter-cache-and-logs)
7. [Git](#git)
8. [Knowledge Hub admin UI](#knowledge-hub-admin-ui)
9. [Shared PHP module](#shared-php-module)
10. [Troubleshooting](#troubleshooting)

---

## Architecture

```
/var/staffdata/{site-id}/          ← outside repo (mount in Docker / persist on VPS)
├── ci/                            ← STAFF_PORTAL_UPLOADS_ROOT
│   ├── staff/                     photos, signatures, passport scans, contracts
│   ├── summernote/
│   └── leave/
├── apm/                           ← STAFF_APM_FILES_ROOT (Laravel public disk)
│   └── uploads/                   memo attachments, activities, summernote, …
├── helpdesk/                      ← STAFF_HELPDESK_FILES_ROOT
│   └── helpdesk/                  ticket attachments, rich-text images
├── staff-portal/                  ← STAFF_PORTAL_MODULE_FILES_ROOT
└── backups/files/                 ← staff file backups (Knowledge Hub UI)

Legacy in-repo paths (default for local dev):
  uploads/                         CI3 FCPATH uploads
  apm/storage/app/public/          APM attachments
  helpdesk/backend/storage/app/public/
  staff-portal/storage/app/public/
```

**Site ID** is derived from `BASE_URL` unless `STAFF_SITE_ID` is set:

| `BASE_URL` | Site ID |
|------------|---------|
| `http://localhost/staff` | `localhost-staff` |
| `https://staff.africacdc.org` | `staff-africacdc-org` |

---

## Local development vs production

| Mode | Behaviour |
|------|-----------|
| **Local dev (default)** | No `STAFF_DATA_ROOT` → apps use in-repo `uploads/` and `storage/app/public`. No migration required. |
| **Production** | Set `STAFF_DATA_ROOT` or `STAFF_USE_HOST_STORAGE=true` → apps read/write under `/var/staffdata/{site-id}/`. Run migration once. |

---

## Environment variables

Set in the **root staff `.env`** (CI3), and mirror in **APM**, **Helpdesk**, and **staff-portal** `.env` files as needed.

```env
# Site identity
STAFF_SITE_ID=localhost-staff          # optional; derived from BASE_URL if unset
BASE_URL=http://localhost/staff/

# Host storage (production)
STAFF_DATA_ROOT=/var/staffdata/localhost-staff
STAFF_USE_HOST_STORAGE=true            # alternative to STAFF_DATA_ROOT alone

# Per-module overrides (optional — default to {STAFF_DATA_ROOT}/{module})
STAFF_PORTAL_UPLOADS_ROOT=/var/staffdata/localhost-staff/ci
STAFF_APM_FILES_ROOT=/var/staffdata/localhost-staff/apm
STAFF_HELPDESK_FILES_ROOT=/var/staffdata/localhost-staff/helpdesk
STAFF_PORTAL_MODULE_FILES_ROOT=/var/staffdata/localhost-staff/staff-portal

# Knowledge Hub integration (on the Knowledge Hub server)
HUB_STAFF_STORAGE_ENABLED=true
STAFF_REPO_ROOT=/opt/homebrew/var/www/staff
STAFF_BASE_URL=http://localhost/staff
STAFF_FILES_BACKUP_RETENTION_DAYS=30
```

### Which app uses which variable

| App | Config key / usage |
|-----|-------------------|
| **CI3** | `staff_uploads_root()` / `staff_uploads_path()` → `STAFF_PORTAL_UPLOADS_ROOT` or `{STAFF_DATA_ROOT}/ci` |
| **APM** | `config('staff_portal.uploads_root')` + `filesystems` public disk → `STAFF_APM_FILES_ROOT` |
| **Helpdesk** | `config('helpdesk.staff_uploads_root')` + public disk → `STAFF_HELPDESK_FILES_ROOT` |
| **staff-portal** | `config('staff-portal.uploads_root')` + public disk → shared `ci/` for photos/contracts |

---

## Migration (macOS and Ubuntu)

Scripts live in **`scripts/storage/`**. They **copy** files (legacy originals are kept until you verify the host copy).

### One-time setup

```bash
cd /path/to/staff

export STAFF_DATA_ROOT=/var/staffdata/localhost-staff
export BASE_URL=http://localhost/staff

# Create host directories and permissions
./scripts/storage/fix-staff-storage-permissions.sh

# Preview (no writes)
DRY_RUN=true ./scripts/storage/migrate-all.sh

# Copy all modules
./scripts/storage/migrate-all.sh
```

### Per-module scripts

| Script | Source | Destination |
|--------|--------|-------------|
| `migrate-ci-uploads.sh` | `uploads/` | `{STAFF_DATA_ROOT}/ci/` |
| `migrate-apm-uploads.sh` | `apm/storage/app/public/` | `{STAFF_DATA_ROOT}/apm/` |
| `migrate-helpdesk-uploads.sh` | `helpdesk/backend/storage/app/public/` | `{STAFF_DATA_ROOT}/helpdesk/` |
| `migrate-staff-portal-uploads.sh` | `staff-portal/storage/app/public/` | `{STAFF_DATA_ROOT}/staff-portal/` |
| `migrate-all.sh` | Runs all four in order | |

### Platform notes

| | macOS | Ubuntu / Linux |
|---|--------|----------------|
| Data root | `/var/staffdata/{site-id}` | Same |
| Web group | `staff` (`STAFF_STORAGE_GROUP`) | `www-data` |
| rsync | Apple 2.6.x — scripts avoid `--info=progress2`; optional `brew install rsync` | Usually 3.x — uses `--info=progress2` |
| File verify | `stat -f%z` | `stat -c%s` |

`VERIFY=false ./scripts/storage/migrate-ci-uploads.sh` skips byte-size verification.

---

## After migration checklist

1. **Set `.env`** in root staff, `apm/`, `helpdesk/backend/`, `staff-portal/` with `STAFF_DATA_ROOT` and `STAFF_USE_HOST_STORAGE=true`.

2. **Relink Laravel public storage:**
   ```bash
   cd apm && php artisan storage:link
   cd ../helpdesk/backend && php artisan storage:link
   cd ../../staff-portal && php artisan storage:link
   ```

3. **Fix CI cache permissions** (if dashboard shows permission errors after `sudo` migrations):
   ```bash
   ./scripts/fix-ci-app-permissions.sh
   ```

4. **Optional symlink** for CI3 without changing code further:
   ```bash
   mv uploads uploads.legacy
   ln -sfn /var/staffdata/localhost-staff/ci uploads
   ```

5. **Untrack uploads from git** (one-time):
   ```bash
   git rm -r --cached uploads/
   ```

6. **Verify** staff photos, memo attachments, and helpdesk ticket files load correctly.

---

## CodeIgniter cache and logs

CI3 writes JSON cache files under `application/cache/` (dashboard lookups, SSO codes, job schedules). The web server must be able to write here.

**Symptom:** `file_put_contents(... application/cache/duty_stations.json): Permission denied`

**Fix:**
```bash
./scripts/fix-ci-app-permissions.sh
```

This script:
- Creates `application/cache`, `cbp_sso`, `temp`, `login_attempts`, and `application/logs`
- Sets ownership to your user + `staff` (macOS) or `www-data` (Ubuntu)
- Applies `chmod ug+rwX,o+rwx` so Apache (`_www` / `www-data`) can write

`cache_helper.php` skips cache writes gracefully when the directory is not writable (data still loads from the database).

Re-run after any `sudo` storage migration that leaves cache files root-owned.

---

## Git

`/uploads/` is in `.gitignore`. If uploads were previously committed:

```bash
git rm -r --cached uploads/
git commit -m "Stop tracking uploads; use STAFF_DATA_ROOT in production"
```

`application/cache/*` and `application/logs/*` remain gitignored (except `index.html` / `.htaccess`).

---

## Knowledge Hub admin UI

When the Knowledge Hub runs on the same host as the staff repo, use **Settings → Storage Management → Staff ecosystem** (`/admin/storage-management#storage-staff`).

Features:
- Per-module file counts and sizes (legacy vs host path)
- **Migrate** / **Migrate all** (runs shell scripts in `STAFF_REPO_ROOT`)
- **Run file backup** with configurable retention
- Path and env reference

Knowledge Hub `.env`:

```env
HUB_STAFF_STORAGE_ENABLED=true
STAFF_REPO_ROOT=/opt/homebrew/var/www/staff
STAFF_BASE_URL=http://localhost/staff
STAFF_HOST_DATA_ROOT=/var/staffdata
```

See also [Knowledge Hub STORAGE.md](../../knowledge_hub/docs/deployment/STORAGE.md) (sibling repo).

---

## Shared PHP module

`shared/StaffStorage.php` (`Staff\Shared\StaffStorage`) resolves paths for all apps.

```php
\Staff\Shared\StaffStorage::ciUploadsRoot();
\Staff\Shared\StaffStorage::apmPublicRoot(base_path());
\Staff\Shared\StaffStorage::recommendedPaths();
```

Autoloaded via Composer in root CI3, APM, Helpdesk, and staff-portal.

After pulling changes that touch `shared/` or `composer.json`:

```bash
composer dump-autoload -o          # repo root (CI3)
cd apm && composer dump-autoload -o
cd ../helpdesk/backend && composer dump-autoload -o
cd ../../staff-portal && composer dump-autoload -o
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| `rsync: --info=progress2: unknown option` (macOS) | Fixed in scripts — update `_common.sh`. Optional: `brew install rsync`. |
| Dashboard cache permission denied | `./scripts/fix-ci-app-permissions.sh` |
| Uploads missing after `git pull` | Migrate to `STAFF_DATA_ROOT`; `git rm -r --cached uploads/` |
| APM attachments 404 | `php artisan storage:link` in `apm/`; check `STAFF_APM_FILES_ROOT` |
| Helpdesk avatars broken | Set `STAFF_PORTAL_UPLOADS_ROOT` to `{STAFF_DATA_ROOT}/ci` in Helpdesk `.env` |
| Staff-portal photos 404 | Set `STAFF_PORTAL_UPLOADS_ROOT`; use `staff.media.photo` route (not direct `/uploads/staff/`) |
| Migration created root-owned files | Re-run `fix-staff-storage-permissions.sh` and `fix-ci-app-permissions.sh` |

---

## Related scripts

| Script | Purpose |
|--------|---------|
| `scripts/storage/fix-staff-storage-permissions.sh` | Host data dirs under `/var/staffdata` |
| `scripts/storage/migrate-all.sh` | Copy all module uploads to host |
| `scripts/fix-ci-app-permissions.sh` | CI3 `application/cache` and `application/logs` |
| `apm/fix-storage-permissions.sh` | APM Laravel `storage/` and `bootstrap/cache` |

---

**See also:** [Main README](../README.md) · [Documentation index](../documentation/README.md) · [Environment variables](../assets/ENVIRONMENT_VARIABLES.md)
