# Staff ecosystem file storage

Uploads for CodeIgniter staff, APM, Helpdesk, and staff-portal should live **outside the git repo** so deployments do not overwrite user files.

## Path layout (production)

```
/var/staffdata/{site-id}/
├── ci/            ← STAFF_PORTAL_UPLOADS_ROOT (staff photos, signatures, summernote, contracts)
├── apm/           ← STAFF_APM_FILES_ROOT (memo attachments, summernote images)
├── helpdesk/      ← STAFF_HELPDESK_FILES_ROOT (ticket attachments, rich-text images)
├── staff-portal/  ← STAFF_PORTAL_MODULE_FILES_ROOT
└── backups/files/ ← file backups (from Knowledge Hub Storage Management UI)
```

Site ID is derived from `BASE_URL` (e.g. `http://localhost/staff` → `localhost-staff`).

## Environment variables

```env
STAFF_SITE_ID=localhost-staff
STAFF_DATA_ROOT=/var/staffdata/localhost-staff
STAFF_USE_HOST_STORAGE=true

# Per-module overrides (optional)
STAFF_PORTAL_UPLOADS_ROOT=/var/staffdata/localhost-staff/ci
STAFF_APM_FILES_ROOT=/var/staffdata/localhost-staff/apm
STAFF_HELPDESK_FILES_ROOT=/var/staffdata/localhost-staff/helpdesk
STAFF_PORTAL_MODULE_FILES_ROOT=/var/staffdata/localhost-staff/staff-portal
```

When unset, apps use legacy in-repo paths (`uploads/`, `storage/app/public`) — safe for local dev.

## Migration

```bash
cd /path/to/staff
export STAFF_DATA_ROOT=/var/staffdata/localhost-staff
export BASE_URL=http://localhost/staff

./scripts/storage/fix-staff-storage-permissions.sh
DRY_RUN=true ./scripts/storage/migrate-all.sh   # preview
./scripts/storage/migrate-all.sh                # copy files (keeps legacy)

# Then set .env in each app and relink Laravel storage:
cd apm && php artisan storage:link
cd ../helpdesk/backend && php artisan storage:link
cd ../../staff-portal && php artisan storage:link
```

Per-module scripts live in `scripts/storage/`.

### macOS vs Ubuntu

| | macOS | Ubuntu / Linux |
|---|--------|----------------|
| Default data root | `/var/staffdata/{site-id}` | `/var/staffdata/{site-id}` |
| Web server group | `staff` (override: `STAFF_STORAGE_GROUP`) | `www-data` |
| rsync | Apple 2.6.x (no `--info=progress2`); optional `brew install rsync` for 3.x | Usually 3.x (`apt install rsync`) — uses `--info=progress2` |
| File size checks | `stat -f%z` | `stat -c%s` |

Scripts auto-detect rsync capabilities and file-size commands on both platforms.

### CodeIgniter cache / logs

If the dashboard shows `Permission denied` on `application/cache/*.json`, run:

```bash
./scripts/fix-ci-app-permissions.sh
```

Re-run after `sudo` storage migrations if cache files become root-owned.

## Git

`/uploads/` is gitignored. Untrack existing uploads once:

```bash
git rm -r --cached uploads/
```

## Knowledge Hub admin UI

When `STAFF_REPO_ROOT` points at this repo, **Settings → Storage Management → Staff ecosystem** shows module metrics, migration buttons, and file backups.

Knowledge Hub `.env`:

```env
HUB_STAFF_STORAGE_ENABLED=true
STAFF_REPO_ROOT=/opt/homebrew/var/www/staff
STAFF_BASE_URL=http://localhost/staff
```

## Shared code

`shared/StaffStorage.php` (`Staff\Shared\StaffStorage`) is used by CI3, APM, Helpdesk, and staff-portal for path resolution.
