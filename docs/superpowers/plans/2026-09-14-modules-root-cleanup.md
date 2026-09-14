# Modules root cleanup Implementation Plan

> **For agentic workers:** Execute task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Move CBP apps under `modules/`, replace `backend` symlink with rewrites, remove unused root clutter, keep public URLs unchanged.

**Architecture:** Disk layout only; Apache `.htaccess` maps `/staff/{apm,finance,helpdesk,backend}` into `modules/…`.

**Tech Stack:** Apache mod_rewrite, git mv, bash path updates.

## Global Constraints

- Public URLs stay `/staff/`, `/staff/backend`, `/staff/apm`, `/staff/finance`, `/staff/helpdesk`.
- No root `backend` symlink.
- One focused commit preferred for the move + routing.

---

### Task 1: Move apps into `modules/`

- [ ] `mkdir -p modules`
- [ ] `git mv staff-portal apm finance helpdesk modules/`
- [ ] Remove root `backend` symlink (`git rm backend` or `rm backend`)

### Task 2: Update root routing

- [ ] Rewrite `.htaccess`: sibling apps → `modules/$1`; SPA/assets → `modules/staff-portal/…`; `backend` → `modules/staff-portal/backend`
- [ ] Update `index.php` require path
- [ ] Smoke: `/staff/`, `/staff/backend/up`, `/staff/apm`, `/staff/finance`, `/staff/helpdesk`

### Task 3: Update path references + delete clutter

- [ ] Update `scripts/fix-laravel-storage-permissions.sh` and other script path defaults
- [ ] Delete: `resources/`, `utils/`, spreadsheet, `000-default.conf`, `nginx-http.conf`
- [ ] Commit

---
