# Performance Approver Nav Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Performance primary-nav dropdown (My forms, Pending reviews, Approval history, Analytics), pending-count badges on Performance and Pending reviews, and an Approval history tab of forms the logged-in staff approved or returned.

**Architecture:** Reuse the existing Performance hub (`?tab=`) and `PerformanceApprovalService::pendingActionsFor()`. Add `pending-count` and `approval-history` API endpoints. Extend `PortalNavItem` with children + badge support and a small Pinia store for the pending count.

**Tech Stack:** Laravel (Staff Portal backend), Vue 3 + Pinia + Vue Router, existing `PortalPrimaryNav` / `PortalPillSubnav` patterns.

**Spec:** `docs/superpowers/specs/2026-10-07-performance-approver-nav-design.md`

## Global Constraints

- Do not change who can approve or workflow rules.
- Trail actor column is `staff_id` on `ppa_approval_trail`, `ppa_approval_trail_midterm`, `ppa_approval_trail_end_term` (actor who acted; entry subject is `ppa_entries.staff_id`).
- Approval history includes only actions `Approved` and `Returned` (case-insensitive).
- Commit subjects: short, no Conventional Commit prefixes (`feat:`, etc.).
- Frontend path base: `modules/staff-portal/frontend/`. Backend path base: `modules/staff-portal/backend/`.

## File map

| File | Responsibility |
|------|----------------|
| `Modules/Performance/app/Services/PerformanceApprovalService.php` | `pendingCountFor()`, `approvalHistoryFor()` |
| `Modules/Performance/app/Http/Controllers/Api/V1/PerformanceHubApiController.php` | `pendingCount`, `approvalHistory` actions |
| `Modules/Performance/routes/api.php` | New GET routes |
| `tests/Feature/PerformanceApproverNavApiTest.php` | Feature tests for count + history |
| `frontend/src/lib/performanceApi.ts` | Client helpers + types |
| `frontend/src/stores/performancePending.ts` | Pending count for nav badges |
| `frontend/src/lib/portalNav.ts` | Performance `children` |
| `frontend/src/components/organisms/PortalPrimaryNav.vue` | Dropdown + badges |
| `frontend/src/styles/portal-shell.css` (or nav scoped CSS) | Badge styles if needed |
| `frontend/src/pages/performance/PerformancePage.vue` | Tab rename + approval-history UI |
| `frontend/src/pages/performance/PerformanceFormPage.vue` | Refresh pending count after approve/return |
| `frontend/src/components/templates/PortalAppShell.vue` | Load pending count when authenticated |

---

### Task 1: Pending count API (TDD)

**Files:**
- Modify: `Modules/Performance/app/Services/PerformanceApprovalService.php`
- Modify: `Modules/Performance/app/Http/Controllers/Api/V1/PerformanceHubApiController.php`
- Modify: `Modules/Performance/routes/api.php`
- Create: `tests/Feature/PerformanceApproverNavApiTest.php`

**Interfaces:**
- Produces: `PerformanceApprovalService::pendingCountFor(int $staffId): int`
- Produces: `GET /api/v1/performance/pending-count` → `{ data: { pending_count: int } }`

- [ ] **Step 1: Write failing feature test**

Create `tests/Feature/PerformanceApproverNavApiTest.php` modeled on `PerformanceFormApiTest` session/permissions. Assert:

```php
public function test_pending_count_matches_hub_pending_length(): void
{
    // Arrange: logged-in supervisor staff_id=50 with one midterm pending them
    // (reuse fixtures / insert ppa_entries + trail pattern from PerformanceFormApiTest)

    $countResponse = $this->getJson('/api/v1/performance/pending-count');
    $hubResponse = $this->getJson('/api/v1/performance/hub?tab=pending');

    $countResponse->assertOk();
    $hubResponse->assertOk();
    $this->assertSame(
        (int) $hubResponse->json('data.pending_count'),
        (int) $countResponse->json('data.pending_count')
    );
    $this->assertGreaterThan(0, (int) $countResponse->json('data.pending_count'));
}
```

Use the same auth approach as other Performance feature tests (`session()->put('user.permissions', [74])` and staff_id, or Sanctum actingAs — match existing tests in the file you copy from).

- [ ] **Step 2: Run test — expect FAIL**

```bash
cd modules/staff-portal/backend && php artisan test --filter=PerformanceApproverNavApiTest::test_pending_count_matches_hub_pending_length
```

Expected: FAIL (route missing or 404).

- [ ] **Step 3: Implement service + controller + route**

In `PerformanceApprovalService.php` add:

```php
public function pendingCountFor(int $staffId): int
{
    if ($staffId < 1) {
        return 0;
    }

    return $this->pendingActionsFor($staffId)->count();
}
```

In `PerformanceHubApiController.php` add:

```php
public function pendingCount(PerformanceApprovalService $approval): JsonResponse
{
    PortalPermission::authorize(74);

    $user = auth()->user();
    $session = $user instanceof PortalUser ? $user->toSessionArray() : (session('user') ?? []);
    $staffId = (int) ($session['staff_id'] ?? ($user instanceof PortalUser ? $user->auth_staff_id : 0));

    return response()->json([
        'data' => [
            'pending_count' => $approval->pendingCountFor($staffId),
        ],
    ]);
}
```

In `routes/api.php` inside the auth group:

```php
Route::get('performance/pending-count', [PerformanceHubApiController::class, 'pendingCount']);
```

- [ ] **Step 4: Run test — expect PASS**

```bash
cd modules/staff-portal/backend && php artisan test --filter=PerformanceApproverNavApiTest::test_pending_count_matches_hub_pending_length
```

- [ ] **Step 5: Commit**

```bash
git add modules/staff-portal/backend/Modules/Performance modules/staff-portal/backend/tests/Feature/PerformanceApproverNavApiTest.php
git commit -m "$(cat <<'EOF'
Add Performance pending-count API for nav badges.

EOF
)"
```

---

### Task 2: Approval history API (TDD)

**Files:**
- Modify: `Modules/Performance/app/Services/PerformanceApprovalService.php`
- Modify: `Modules/Performance/app/Http/Controllers/Api/V1/PerformanceHubApiController.php`
- Modify: `Modules/Performance/routes/api.php`
- Modify: `tests/Feature/PerformanceApproverNavApiTest.php`

**Interfaces:**
- Produces: `PerformanceApprovalService::approvalHistoryFor(int $actorStaffId, ?string $period, ?PerformancePhase $phase, int $page, int $perPage): array{data: list<array<string,mixed>>, meta: array<string,int>}`
- Produces: `GET /api/v1/performance/approval-history`

- [ ] **Step 1: Write failing tests**

```php
public function test_approval_history_returns_only_actor_approved_or_returned(): void
{
    // Insert trail rows: actor 50 Approved, actor 50 Returned, actor 51 Approved
    // GET /api/v1/performance/approval-history as staff 50
    // Assert: 2 rows, both staff_id actor filtered; actions only Approved/Returned
    // Assert each row has entry_id, staff_id (subject), staff_name, phase, phase_label,
    // performance_period, action, acted_at, form_url
}

public function test_approval_history_paginates(): void
{
    // Seed > per_page rows for actor; request per_page=2 page=1
    // Assert meta.total, meta.last_page, count(data) === 2
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
cd modules/staff-portal/backend && php artisan test --filter=PerformanceApproverNavApiTest::test_approval_history
```

- [ ] **Step 3: Implement `approvalHistoryFor`**

Query each trail table that exists (`Schema::hasTable`), select:

```php
// Per phase table (alias t):
// t.id, t.entry_id, t.action, t.comments, t.created_at as acted_at,
// p.staff_id, p.performance_period,
// subject name from staff join on p.staff_id
// WHERE t.staff_id = $actorStaffId
// AND LOWER(t.action) IN ('approved', 'returned')
// optional period / phase filters
```

Union the three phase result sets in PHP (or `unionAll` queries), sort by `acted_at` DESC / `id` DESC, then slice for pagination.

Map each row:

```php
[
    'entry_id' => (string) $row->entry_id,
    'staff_id' => (int) $row->staff_id,
    'staff_name' => (string) $row->staff_name,
    'phase' => $phase->value,
    'phase_label' => $phase->label(),
    'performance_period' => (string) $row->performance_period,
    'action' => (string) $row->action,
    'comments' => (string) ($row->comments ?? ''),
    'acted_at' => (string) $row->acted_at,
    'form_url' => '/performance/form/'.$phase->value.'/'.$row->entry_id.'/'.$row->staff_id,
]
```

Controller:

```php
public function approvalHistory(Request $request, PerformanceApprovalService $approval): JsonResponse
{
    PortalPermission::authorize(74);
    // resolve $staffId like hub()
    $page = max(1, (int) $request->query('page', 1));
    $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
    $period = $request->filled('period') ? (string) $request->query('period') : null;
    $phase = PerformancePhase::tryFrom((string) $request->query('phase', ''));

    $result = $approval->approvalHistoryFor($staffId, $period, $phase, $page, $perPage);

    return response()->json(['data' => $result['data'], 'meta' => $result['meta']]);
}
```

Route:

```php
Route::get('performance/approval-history', [PerformanceHubApiController::class, 'approvalHistory']);
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
cd modules/staff-portal/backend && php artisan test --filter=PerformanceApproverNavApiTest
```

- [ ] **Step 5: Commit**

```bash
git add modules/staff-portal/backend/Modules/Performance modules/staff-portal/backend/tests/Feature/PerformanceApproverNavApiTest.php
git commit -m "$(cat <<'EOF'
Add Performance approval-history API for approvers.

EOF
)"
```

---

### Task 3: Frontend API + pending-count store

**Files:**
- Modify: `frontend/src/lib/performanceApi.ts`
- Create: `frontend/src/stores/performancePending.ts`
- Modify: `frontend/src/components/templates/PortalAppShell.vue`
- Modify: `frontend/src/pages/performance/PerformanceFormPage.vue` (refresh after approve/return/consent)

**Interfaces:**
- Produces: `fetchPerformancePendingCount(): Promise<number>`
- Produces: `fetchPerformanceApprovalHistory(params): Promise<{ data: PerformanceApprovalHistoryItem[]; meta: … }>`
- Produces: `usePerformancePendingStore()` with `count`, `refresh()`, `setCount(n)`

- [ ] **Step 1: Extend `performanceApi.ts`**

```ts
export type PerformanceTab = 'dashboard' | 'my' | 'pending' | 'approval-history'

export interface PerformanceApprovalHistoryItem {
  entry_id: string
  staff_id: number
  staff_name: string
  phase: PerformancePhase
  phase_label: string
  performance_period: string
  action: string
  comments: string
  acted_at: string
  form_url: string
}

export async function fetchPerformancePendingCount(): Promise<number> {
  const { data } = await api.get<{ data: { pending_count: number } }>(
    '/api/v1/performance/pending-count',
  )
  return Number(data.data.pending_count || 0)
}

export async function fetchPerformanceApprovalHistory(params: {
  page?: number
  per_page?: number
  period?: string
  phase?: PerformancePhase
}): Promise<{
  data: PerformanceApprovalHistoryItem[]
  meta: { current_page: number; per_page: number; total: number; last_page: number }
}> {
  const { data } = await api.get<{
    data: PerformanceApprovalHistoryItem[]
    meta: { current_page: number; per_page: number; total: number; last_page: number }
  }>('/api/v1/performance/approval-history', { params })
  return { data: data.data, meta: data.meta }
}
```

- [ ] **Step 2: Create Pinia store**

```ts
// stores/performancePending.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchPerformancePendingCount } from '@/lib/performanceApi'

export const usePerformancePendingStore = defineStore('performancePending', () => {
  const count = ref(0)
  const loaded = ref(false)

  async function refresh(): Promise<void> {
    try {
      count.value = await fetchPerformancePendingCount()
      loaded.value = true
    } catch {
      // Keep last known count; nav badge is best-effort.
    }
  }

  function setCount(n: number): void {
    count.value = Math.max(0, Number(n) || 0)
    loaded.value = true
  }

  return { count, loaded, refresh, setCount }
})
```

- [ ] **Step 3: Load count from shell when authenticated**

In `PortalAppShell.vue`, watch `auth.isAuthenticated` (and performance module enabled): call `usePerformancePendingStore().refresh()`.

- [ ] **Step 4: Refresh after approve/return/consent**

In `PerformanceFormPage.vue`, after successful approve/return/consent, call `usePerformancePendingStore().refresh()`.

- [ ] **Step 5: Commit**

```bash
git add modules/staff-portal/frontend/src/lib/performanceApi.ts \
  modules/staff-portal/frontend/src/stores/performancePending.ts \
  modules/staff-portal/frontend/src/components/templates/PortalAppShell.vue \
  modules/staff-portal/frontend/src/pages/performance/PerformanceFormPage.vue
git commit -m "$(cat <<'EOF'
Wire Performance pending-count client store.

EOF
)"
```

---

### Task 4: Performance nav dropdown + badges

**Files:**
- Modify: `frontend/src/lib/portalNav.ts`
- Modify: `frontend/src/components/organisms/PortalPrimaryNav.vue`
- Modify: `frontend/src/styles/portal-shell.css` (badge styles)

**Interfaces:**
- Consumes: `usePerformancePendingStore().count`
- Produces: Performance item with `children` linking to hub tabs

- [ ] **Step 1: Extend nav model**

```ts
export interface PortalNavItem {
  // ...existing fields
  children?: PortalNavItem[]
  /** When set, show badge from performance pending store */
  badgeFrom?: 'performancePending'
}

// Replace Performance entry:
{
  label: 'Performance',
  i18nKey: 'performance',
  to: '/performance',
  permission: 74,
  match: ['/performance'],
  group: 'primary',
  icon: 'fa-solid fa-chart-line',
  module: 'performance',
  badgeFrom: 'performancePending',
  children: [
    { label: 'My forms', i18nKey: 'perf_my_forms', to: '/performance?tab=dashboard', icon: 'fa-solid fa-file-lines' },
    { label: 'Pending reviews', i18nKey: 'perf_pending', to: '/performance?tab=pending', icon: 'fa-solid fa-clipboard-check', badgeFrom: 'performancePending' },
    { label: 'Approval history', i18nKey: 'perf_approval_history', to: '/performance?tab=approval-history', icon: 'fa-solid fa-stamp' },
    { label: 'Analytics', i18nKey: 'perf_analytics', to: '/performance?tab=analytics', icon: 'fa-solid fa-chart-line' },
  ],
}
```

Update `isNavItemActive` so a parent with children is active when any child path/query matches `/performance` (path prefix is enough).

- [ ] **Step 2: Render dropdown in `PortalPrimaryNav.vue`**

For each primary item with `children?.length`:

- Render a dropdown block like **More** (`cbp-nav-item-dropdown`).
- Toggle open state per item key (or single `perfOpen` ref).
- Parent button shows label + caret + badge when `badgeFrom === 'performancePending' && store.count > 0`.
- Menu links: `RouterLink` to each child `to`; Pending child shows same badge.
- Do **not** also render Performance as a plain `RouterLink` in the flat list.
- Close on route change / outside click (extend existing `closeAll`).

Badge markup example:

```vue
<span v-if="badgeCount(item) > 0" class="cbp-nav-badge">{{ badgeCount(item) }}</span>
```

```ts
function badgeCount(item: PortalNavItem): number {
  if (item.badgeFrom === 'performancePending') {
    return performancePending.count
  }
  return 0
}
```

- [ ] **Step 3: CSS**

Add compact pill badge next to Performance / Pending labels (reuse colors similar to `PortalPillSubnav` badge — primary/error tonal).

- [ ] **Step 4: Manual check**

Log in as a supervisor with pending items: Performance shows badge; open dropdown; Pending reviews shows badge; links land on correct tabs.

- [ ] **Step 5: Commit**

```bash
git add modules/staff-portal/frontend/src/lib/portalNav.ts \
  modules/staff-portal/frontend/src/components/organisms/PortalPrimaryNav.vue \
  modules/staff-portal/frontend/src/styles/portal-shell.css
git commit -m "$(cat <<'EOF'
Add Performance nav dropdown with pending badges.

EOF
)"
```

---

### Task 5: Hub Approval history tab + rename History

**Files:**
- Modify: `frontend/src/pages/performance/PerformancePage.vue`
- Modify: `frontend/src/lib/performanceApi.ts` (HubTab types if needed)

- [ ] **Step 1: Tab model**

```ts
type HubTab = PerformanceTab | 'analytics' // PerformanceTab already includes approval-history
```

Update `hubTabItems` order:

1. My forms (`dashboard`)
2. Pending reviews (`pending`) — badge from `data.pending_count` **or** store count (prefer hub data when loaded, else store)
3. Approval history (`approval-history`)
4. My submissions (`my`) — rename label from History; i18n key `subnav.perf_my_submissions`
5. Analytics (`analytics`)

Ensure `setTab` / route query watcher accept `approval-history`.

- [ ] **Step 2: Load approval history when tab active**

```ts
const historyRows = ref<PerformanceApprovalHistoryItem[]>([])
const historyMeta = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1 })
const historyPage = ref(1)

async function loadApprovalHistory() {
  const res = await fetchPerformanceApprovalHistory({
    page: historyPage.value,
    per_page: 25,
    period: period.value || undefined,
  })
  historyRows.value = res.data
  historyMeta.value = res.meta
}
```

Call from `load()` when `tab === 'approval-history'`. Also `performancePending.setCount(data.pending_count)` when hub loads so nav stays in sync.

- [ ] **Step 3: Table UI**

Mirror Pending table columns: Staff, Phase (`phase_label`), Period, Action, Date (`acted_at`), Open (`RouterLink` / button to `form_url`). Client or server pagination via `historyMeta`.

Empty state: “No approval actions yet.”

- [ ] **Step 4: Sync pending store from hub**

When hub returns, `performancePending.setCount(data.pending_count)`.

- [ ] **Step 5: Commit**

```bash
git add modules/staff-portal/frontend/src/pages/performance/PerformancePage.vue \
  modules/staff-portal/frontend/src/lib/performanceApi.ts
git commit -m "$(cat <<'EOF'
Add Approval history tab on Performance hub.

EOF
)"
```

---

### Task 6: Build SPA + smoke verify

**Files:**
- `frontend/` build output (`dist-user` + publish script as used in this environment)

- [ ] **Step 1: Build and publish**

```bash
cd modules/staff-portal/frontend && npx vite build --outDir dist-user && ../scripts/publish-spa.sh "$(pwd)/dist-user"
```

- [ ] **Step 2: Smoke checklist**

- [ ] Performance dropdown lists four items
- [ ] Badges on Performance + Pending when count > 0
- [ ] Pending reviews lists actionable PPA/midterm/endterm
- [ ] Approval history lists only my Approved/Returned rows
- [ ] My submissions still shows own forms
- [ ] Approve one item → badges decrease after refresh

- [ ] **Step 3: Commit any leftover fixes; push if asked**

```bash
git status
```

---

## Spec coverage self-review

| Spec requirement | Task |
|------------------|------|
| Performance nav dropdown (4 items) | Task 4 |
| Badge on Performance parent | Task 4 (+ store Task 3) |
| Badge on Pending reviews (nav + hub) | Tasks 4–5 |
| Pending list unchanged semantics | Task 1 reuses `pendingActionsFor` |
| Approval history Approved/Returned only | Task 2 |
| Rename History → My submissions | Task 5 |
| Lightweight pending-count endpoint | Task 1 |
| Refresh after approve/return | Task 3 |
| Tests | Tasks 1–2 |

## Placeholder scan

No TBD/TODO steps; commands and interfaces specified.

## Type consistency

- Tab key: `approval-history` everywhere (nav query, hub pills, `PerformanceTab`).
- Trail actor filter: `t.staff_id` (actor), subject: `ppa_entries.staff_id`.
- Badge source key: `performancePending`.
