# Audit Logs CI3 Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Match CI3 `auth/logs` filters, columns, details modal, and revert on the SPA audit page.

**Architecture:** Expand `AuthAdminApiController::auditLogs` + add revert endpoint using existing Audit revert whitelist; rebuild `AuditLogsPage.vue`.

**Tech Stack:** Laravel, Vue 3, `user_logs` table, CI3 reference `application/modules/auth/views/users/user_logs.php`.

## Global Constraints

- Permission **17** for all audit admin endpoints
- Revert whitelist remains config-driven (today: `user` table only) — do not broaden without explicit request
- Default page size **50**

---

### Task 1: Expand audit list API

**Files:**
- Modify: `Modules/Auth/app/Http/Controllers/Api/V1/AuthAdminApiController.php`
- Modify: `Modules/Auth/routes/api.php` if needed
- Reference: `application/modules/auth/models/Auth_mdl.php` (`get_logs`, `count_logs`)

**Query params:** `search` (alias keep `q`), `name`, `email`, `http_method`, `event_type`, `date_from`, `date_to`, `page`, `per_page` (default 50).

**Select:** join `user` + `staff` for email; return all `user_logs` columns used by UI.

- [ ] **Step 1: Join staff for work_email; apply each filter when filled.**

```php
$q = DB::table('user_logs as l')
    ->leftJoin('user as u', 'u.user_id', '=', 'l.user_id')
    ->leftJoin('staff as s', 's.staff_id', '=', 'u.auth_staff_id')
    ->select('l.*', 'u.name as user_name', 's.work_email as user_email')
    ->orderByDesc('l.id');
```

- [ ] **Step 2: Meta** — `total`, `current_page`, `last_page`, `per_page`, `extended` (true if any row has http_method/event_type populated or config flag).

- [ ] **Step 3: Smoke via authenticated HTTP or tinker-constructed request.**

---

### Task 2: Revert API

**Files:**
- Modify: `AuthAdminApiController` — `revertAuditLog(int $id)`
- Modify: `Modules/Auth/routes/api.php` — `POST auth/audit-logs/{id}/revert`
- Reuse: `Modules/Audit` services / CI3 `audit_revert.php` logic if already ported; else port whitelist restore of `old_values` JSON onto `target_table`/`target_id`

- [ ] **Step 1: Implement revert** — require `old_values`, not already reverted, `target_table` whitelisted; set `reverted_at`, `reverted_by_user_id`.

- [ ] **Step 2: Return 422 with message when not revertible.**

---

### Task 3: AuditLogsPage Vue parity

**Files:**
- Modify: `frontend/src/lib/authAdminApi.ts`
- Modify: `frontend/src/pages/auth/AuditLogsPage.vue`

- [ ] **Step 1: Filter form** — search, name, email, method, event, date_from, date_to; Apply / Reset.

- [ ] **Step 2: Summary cards** — matching rows, rows on page, short note about integrity/retention.

- [ ] **Step 3: Table** — `#`, ID, User (name+email), When, Method badge, Event badge, URI, Target (`table#id`), Actions.

- [ ] **Step 4: Details dialog** — IP, UA, action, old/new JSON, reverted banner.

- [ ] **Step 5: Revert button** when eligible; confirm; refresh list.

- [ ] **Step 6: `npm run build`.**

---

### Task 4: Manual check

- [ ] Compare filters/columns against CI3 `auth/logs` locally or production screenshots
- [ ] Revert a safe whitelisted `user` change in local DB only
