# APM Multi-Division Switcher Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let APM staff with multiple divisions (contract associations and/or focal/head/director/AA/OIC roles) switch an active division on `/apm/home` for session-scoped matrix/memo create and view, without breaking staff sync or finance-officer approvals.

**Architecture:** Session overlay (`active_division_id`) on top of primary `staff.division_id`. `StaffDivisionContext` builds the switchable union. `user_session('division_id'|'division_name')` returns the active context when valid so existing controllers pick it up. Associated divisions stay JSON on `staff.associated_divisions` via existing share sync.

**Tech Stack:** Laravel (APM + Staff Portal), Pest/PHPUnit in `modules/apm`, Blade + `public/js/home-dashboard-app.js` (Vue 3 + Vuetify 3).

**Spec:** `docs/superpowers/specs/2026-10-03-apm-multi-division-switcher-design.md`

## Global Constraints

- Switchable set = primary + `associated_divisions` + focal/head/head-OIC/director/director-OIC/admin_assistant (union, deduped).
- Do **not** add divisions solely because user is `finance_officer` / finance OIC.
- Finance officers **may** switch if the union has ≥ 2 divisions.
- Active division is **session-only**; clear on SSO/login; reset to primary.
- Active division drives all APM create/view/list paths that use `user_session('division_id')`.
- Do not break Staff Portal → share → APM/finance/risk/helpdesk staff sync.
- Prefer small diffs; reuse existing `Division` OIC helpers.
- Commit after each task; no Conventional Commit prefixes (`feat:`, etc.); no AI attribution trailers.

## File map

| Path | Responsibility |
|------|----------------|
| `modules/apm/app/Support/StaffDivisionContext.php` | Switchable list + set/get/clear active division |
| `modules/apm/app/Helpers/CustomHelper.php` | `user_session` overlays `division_id` / `division_name` |
| `modules/apm/app/Http/Controllers/DivisionContextController.php` | POST switch endpoint |
| `modules/apm/routes/web.php` | Register route |
| `modules/apm/app/Http/Controllers/HomeController.php` | Pass switcher config in `pageConfig` |
| `modules/apm/public/js/home-dashboard-app.js` | Division select UI on home |
| `modules/apm/resources/views/partials/apm-vuetify-runtime-scripts.blade.php` | Bump `?v=` cache bust |
| `modules/apm/app/Http/Controllers/AuthController.php` | Clear active division on SSO open |
| `modules/apm/tests/Unit/StaffDivisionContextTest.php` | Unit tests for switchable set + session |
| `modules/apm/tests/Feature/DivisionContextSwitchTest.php` | HTTP switch + session overlay |
| `modules/staff-portal/backend/tests/Feature/ShareStaffAssociatedDivisionsTest.php` | Share payload regression (create if needed) |
| `modules/apm/tests/Unit/SyncStaffAssociatedDivisionsTest.php` | Normalize associated JSON |

---

### Task 1: `StaffDivisionContext` switchable union

**Files:**
- Create: `modules/apm/app/Support/StaffDivisionContext.php`
- Create: `modules/apm/tests/Unit/StaffDivisionContextTest.php`
- Modify: `modules/apm/app/Models/Division.php` only if a small `queryForStaffHeadOrHeadOic` helper is cleaner than inlining (prefer reuse `queryForWeeklyBriefDivisionAuthority` pieces / existing OIC date logic)

**Interfaces:**
- Produces: `StaffDivisionContext::switchableDivisions(?int $staffId = null): list<array{id:int,name:string,is_primary:bool,sources:list<string>}>`
- Produces: `StaffDivisionContext::switchableIds(?int $staffId = null): list<int>`
- Produces: `StaffDivisionContext::primaryDivisionId(?int $staffId = null): ?int`
- Consumes: `Staff`, `Division`, `resolved_session_staff_id()`

- [ ] **Step 1: Write the failing unit test**

Create `modules/apm/tests/Unit/StaffDivisionContextTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Staff;
use App\Support\StaffDivisionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('switchable includes primary and associated but not finance-officer-only division', function () {
    $staff = Staff::factory()->create([
        'staff_id' => 501,
        'division_id' => 10,
        'associated_divisions' => [20],
        'active' => 1,
    ]);

    Division::query()->insert([
        ['division_id' => 10, 'division_name' => 'Primary Div', 'focal_person' => null, 'division_head' => null, 'admin_assistant' => null, 'finance_officer' => null, 'director_id' => null],
        ['division_id' => 20, 'division_name' => 'Associated Div', 'focal_person' => null, 'division_head' => null, 'admin_assistant' => null, 'finance_officer' => null, 'director_id' => null],
        ['division_id' => 30, 'division_name' => 'Finance Only', 'focal_person' => null, 'division_head' => null, 'admin_assistant' => null, 'finance_officer' => 501, 'director_id' => null],
    ]);

    $ids = StaffDivisionContext::switchableIds(501);

    expect($ids)->toContain(10)->toContain(20)->not->toContain(30);
});

test('switchable includes division where staff is focal person', function () {
    Staff::factory()->create([
        'staff_id' => 502,
        'division_id' => 10,
        'associated_divisions' => [],
        'active' => 1,
    ]);
    Division::query()->insert([
        ['division_id' => 10, 'division_name' => 'Home', 'focal_person' => null, 'division_head' => null, 'admin_assistant' => null, 'finance_officer' => null, 'director_id' => null],
        ['division_id' => 40, 'division_name' => 'Focal Div', 'focal_person' => 502, 'division_head' => null, 'admin_assistant' => null, 'finance_officer' => null, 'director_id' => null],
    ]);

    expect(StaffDivisionContext::switchableIds(502))->toContain(10)->toContain(40);
});
```

If `Staff::factory()` or `Division` insert columns differ, adapt to existing factories/migrations in APM tests (inspect `modules/apm/database/factories` and division table columns). Prefer creating models the same way other APM unit tests do.

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd modules/apm && php artisan test --filter=StaffDivisionContextTest
```

Expected: FAIL (class `StaffDivisionContext` not found).

- [ ] **Step 3: Implement `StaffDivisionContext`**

Create `modules/apm/app/Support/StaffDivisionContext.php`:

```php
<?php

namespace App\Support;

use App\Models\Division;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class StaffDivisionContext
{
    public const SESSION_ACTIVE_ID = 'active_division_id';
    public const SESSION_ACTIVE_NAME = 'active_division_name';

    public static function primaryDivisionId(?int $staffId = null): ?int
    {
        $staffId = $staffId ?? resolved_session_staff_id();
        if ($staffId === null || $staffId <= 0) {
            return null;
        }
        $staff = Staff::query()->where('staff_id', $staffId)->first();
        $id = (int) ($staff->division_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /** @return list<int> */
    public static function switchableIds(?int $staffId = null): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['id'],
            self::switchableDivisions($staffId)
        ));
    }

    /**
     * @return list<array{id:int,name:string,is_primary:bool,sources:list<string>}>
     */
    public static function switchableDivisions(?int $staffId = null): array
    {
        $staffId = $staffId ?? resolved_session_staff_id();
        if ($staffId === null || $staffId <= 0) {
            return [];
        }

        $staff = Staff::query()->where('staff_id', $staffId)->first();
        if (! $staff) {
            return [];
        }

        /** @var array<int, list<string>> $sourcesById */
        $sourcesById = [];
        $add = static function (int $id, string $source) use (&$sourcesById): void {
            if ($id <= 0) {
                return;
            }
            $sourcesById[$id] ??= [];
            if (! in_array($source, $sourcesById[$id], true)) {
                $sourcesById[$id][] = $source;
            }
        };

        $primary = (int) ($staff->division_id ?? 0);
        $add($primary, 'primary');

        foreach ((array) ($staff->associated_divisions ?? []) as $raw) {
            $add((int) $raw, 'associated');
        }

        if (Schema::hasTable('divisions')) {
            foreach (Division::query()->where('focal_person', $staffId)->pluck('division_id') as $id) {
                $add((int) $id, 'focal');
            }
            foreach (Division::query()->where('division_head', $staffId)->pluck('division_id') as $id) {
                $add((int) $id, 'head');
            }
            foreach (Division::query()->where('admin_assistant', $staffId)->pluck('division_id') as $id) {
                $add((int) $id, 'admin_assistant');
            }
            foreach (Division::queryForStaffActingAsDirector($staffId)->pluck('division_id') as $id) {
                $add((int) $id, 'director');
            }
            $today = Carbon::now()->toDateString();
            $headOic = Division::query()
                ->where('head_oic_id', $staffId)
                ->where(function ($q) use ($today) {
                    $q->whereNull('head_oic_start_date')->orWhereDate('head_oic_start_date', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('head_oic_end_date')->orWhereDate('head_oic_end_date', '>=', $today);
                })
                ->pluck('division_id');
            foreach ($headOic as $id) {
                $add((int) $id, 'head_oic');
            }
            // Intentionally omit finance_officer / finance_officer_oic_id
        }

        if ($sourcesById === []) {
            return [];
        }

        $names = Division::query()
            ->whereIn('division_id', array_keys($sourcesById))
            ->pluck('division_name', 'division_id');

        $out = [];
        foreach ($sourcesById as $id => $sources) {
            $out[] = [
                'id' => (int) $id,
                'name' => (string) ($names[$id] ?? ('Division '.$id)),
                'is_primary' => (int) $id === $primary,
                'sources' => $sources,
            ];
        }
        usort($out, static function (array $a, array $b): int {
            if ($a['is_primary'] !== $b['is_primary']) {
                return $a['is_primary'] ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }

    public static function clearActive(): void
    {
        session()->forget([self::SESSION_ACTIVE_ID, self::SESSION_ACTIVE_NAME]);
    }

    public static function setActive(int $divisionId, ?int $staffId = null): bool
    {
        $ids = self::switchableIds($staffId);
        if (! in_array($divisionId, $ids, true)) {
            return false;
        }
        $name = (string) (Division::query()->where('division_id', $divisionId)->value('division_name') ?? '');
        session([
            self::SESSION_ACTIVE_ID => $divisionId,
            self::SESSION_ACTIVE_NAME => $name,
        ]);

        return true;
    }

    public static function activeDivisionId(?int $staffId = null): ?int
    {
        $active = (int) session(self::SESSION_ACTIVE_ID, 0);
        if ($active <= 0) {
            return self::primaryDivisionId($staffId);
        }
        if (! in_array($active, self::switchableIds($staffId), true)) {
            self::clearActive();

            return self::primaryDivisionId($staffId);
        }

        return $active;
    }

    public static function activeDivisionName(?int $staffId = null): ?string
    {
        $id = self::activeDivisionId($staffId);
        if ($id === null) {
            return null;
        }
        $cachedId = (int) session(self::SESSION_ACTIVE_ID, 0);
        $cachedName = session(self::SESSION_ACTIVE_NAME);
        if ($cachedId === $id && is_string($cachedName) && $cachedName !== '') {
            return $cachedName;
        }

        return (string) (Division::query()->where('division_id', $id)->value('division_name') ?? '');
    }
}
```

Adjust column names if APM `divisions` PK is `id` instead of `division_id` — check `Division` model `$primaryKey` / fillable before implementing.

- [ ] **Step 4: Run tests to verify they pass**

```bash
cd modules/apm && php artisan test --filter=StaffDivisionContextTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add modules/apm/app/Support/StaffDivisionContext.php modules/apm/tests/Unit/StaffDivisionContextTest.php
git commit -m "$(cat <<'EOF'
Add StaffDivisionContext for multi-division switch lists.

EOF
)"
```

---

### Task 2: Overlay `user_session` division + clear on login

**Files:**
- Modify: `modules/apm/app/Helpers/CustomHelper.php` (`user_session`)
- Modify: `modules/apm/app/Http/Controllers/AuthController.php` (`openSessionFromStaffToken`)
- Modify: `modules/apm/tests/Unit/StaffDivisionContextTest.php` (add session overlay cases) or create `modules/apm/tests/Unit/UserSessionDivisionOverlayTest.php`

**Interfaces:**
- Consumes: `StaffDivisionContext::activeDivisionId()`, `activeDivisionName()`
- Produces: `user_session('division_id')` / `user_session('division_name')` return active context when set

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Models\Division;
use App\Models\Staff;
use App\Support\StaffDivisionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user_session division_id prefers active_division_id when switchable', function () {
    Staff::factory()->create([
        'staff_id' => 601,
        'division_id' => 10,
        'associated_divisions' => [20],
        'active' => 1,
    ]);
    // insert divisions 10, 20 as in Task 1
    session(['user' => ['staff_id' => 601, 'division_id' => 10, 'division_name' => 'Primary Div']]);
    expect((int) user_session('division_id'))->toBe(10);

    expect(StaffDivisionContext::setActive(20, 601))->toBeTrue();
    expect((int) user_session('division_id'))->toBe(20);
});
```

- [ ] **Step 2: Run test — expect FAIL** (overlay not implemented)

```bash
cd modules/apm && php artisan test --filter=user_session.division_id.prefers
```

- [ ] **Step 3: Implement overlay in `user_session`**

Inside both API and web branches of `user_session`, after resolving `$value` for a key, add:

```php
if ($key === 'division_id' && class_exists(\App\Support\StaffDivisionContext::class)) {
    $active = \App\Support\StaffDivisionContext::activeDivisionId();
    if ($active !== null && $active > 0) {
        return $active;
    }
}
if ($key === 'division_name' && class_exists(\App\Support\StaffDivisionContext::class)) {
    $name = \App\Support\StaffDivisionContext::activeDivisionName();
    if (is_string($name) && $name !== '') {
        return $name;
    }
}
```

Also when `$key === null` (full user array), optionally overlay:

```php
$activeId = \App\Support\StaffDivisionContext::activeDivisionId();
if ($activeId) {
    $user['division_id'] = $activeId;
    $user['division_name'] = \App\Support\StaffDivisionContext::activeDivisionName();
}
```

In `AuthController::openSessionFromStaffToken`, after building `$json` and before `session([...])`:

```php
if (class_exists(\App\Support\StaffDivisionContext::class)) {
    \App\Support\StaffDivisionContext::clearActive();
}
```

Call `clearActive()` at the start of session open so a prior session cannot leak (session regenerate usually clears, but be explicit).

- [ ] **Step 4: Run tests — expect PASS**

```bash
cd modules/apm && php artisan test --filter=UserSessionDivisionOverlayTest
# or StaffDivisionContextTest if tests live there
```

- [ ] **Step 5: Commit**

```bash
git add modules/apm/app/Helpers/CustomHelper.php modules/apm/app/Http/Controllers/AuthController.php modules/apm/tests/Unit/
git commit -m "$(cat <<'EOF'
Overlay active division on user_session for APM context.

EOF
)"
```

---

### Task 3: POST division-context endpoint

**Files:**
- Create: `modules/apm/app/Http/Controllers/DivisionContextController.php`
- Modify: `modules/apm/routes/web.php`
- Create: `modules/apm/tests/Feature/DivisionContextSwitchTest.php`

**Interfaces:**
- Produces: `POST /division-context` named `division-context.update`
- Consumes: `StaffDivisionContext::setActive`

- [ ] **Step 1: Write failing feature test**

```php
<?php

use App\Models\Division;
use App\Models\Staff;
use App\Support\StaffDivisionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('post division context switches active division', function () {
    Staff::factory()->create([
        'staff_id' => 701,
        'division_id' => 10,
        'associated_divisions' => [20],
        'active' => 1,
    ]);
    // seed divisions 10, 20
    $this->withSession(['user' => ['staff_id' => 701, 'division_id' => 10, 'division_name' => 'Primary']])
        ->post(route('division-context.update'), ['division_id' => 20])
        ->assertRedirect();

    expect((int) session(StaffDivisionContext::SESSION_ACTIVE_ID))->toBe(20);
});

test('post division context rejects division outside switchable set', function () {
    Staff::factory()->create([
        'staff_id' => 702,
        'division_id' => 10,
        'associated_divisions' => [],
        'active' => 1,
    ]);
    // seed division 10 only; 99 does not exist or not switchable
    $this->withSession(['user' => ['staff_id' => 702, 'division_id' => 10]])
        ->from(route('home'))
        ->post(route('division-context.update'), ['division_id' => 99])
        ->assertRedirect(route('home'));

    expect(session(StaffDivisionContext::SESSION_ACTIVE_ID))->toBeNull();
});
```

Wire middleware the same way as other authenticated web routes (`CheckSessionMiddleware`). If route middleware blocks unauthenticated differently, follow `HomeController` pattern.

- [ ] **Step 2: Run test — expect FAIL** (route missing)

```bash
cd modules/apm && php artisan test --filter=DivisionContextSwitchTest
```

- [ ] **Step 3: Implement controller + route**

`DivisionContextController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Support\StaffDivisionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DivisionContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'division_id' => ['required', 'integer', 'min:1'],
        ]);
        $ok = StaffDivisionContext::setActive((int) $validated['division_id']);
        if (! $ok) {
            return redirect()
                ->back()
                ->with(['msg' => 'You cannot switch to that division.', 'type' => 'error']);
        }

        $name = StaffDivisionContext::activeDivisionName() ?? 'selected division';

        return redirect()
            ->back()
            ->with(['msg' => 'Now acting as '.$name.'.', 'type' => 'success']);
    }
}
```

In `routes/web.php` near the home route:

```php
Route::post('/division-context', [App\Http\Controllers\DivisionContextController::class, 'update'])
    ->name('division-context.update')
    ->middleware(CheckSessionMiddleware::class);
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
cd modules/apm && php artisan test --filter=DivisionContextSwitchTest
```

- [ ] **Step 5: Commit**

```bash
git add modules/apm/app/Http/Controllers/DivisionContextController.php modules/apm/routes/web.php modules/apm/tests/Feature/DivisionContextSwitchTest.php
git commit -m "$(cat <<'EOF'
Add APM division context switch endpoint.

EOF
)"
```

---

### Task 4: Home dashboard switcher UI

**Files:**
- Modify: `modules/apm/app/Http/Controllers/HomeController.php`
- Modify: `modules/apm/public/js/home-dashboard-app.js`
- Modify: `modules/apm/resources/views/partials/apm-vuetify-runtime-scripts.blade.php` (bump `home-dashboard-app.js?v=`)

**Interfaces:**
- Consumes: `StaffDivisionContext::switchableDivisions()`, `activeDivisionId()`, route `division-context.update`
- Produces: `pageConfig.divisionContext` for the Vue app

- [ ] **Step 1: Extend `HomeController::index` pageConfig**

```php
use App\Support\StaffDivisionContext;

$switchable = StaffDivisionContext::switchableDivisions();
$divisionContext = [
    'enabled' => count($switchable) >= 2,
    'activeId' => StaffDivisionContext::activeDivisionId(),
    'divisions' => $switchable,
    'updateUrl' => route('division-context.update'),
    'csrfToken' => csrf_token(),
];

return view('home', [
    'pageConfig' => [
        // existing keys...
        'divisionContext' => $divisionContext,
    ],
]);
```

- [ ] **Step 2: Add UI to `home-dashboard-app.js`**

In `setup()`:

```javascript
const divisionContext = computed(() => cfg.divisionContext || { enabled: false, divisions: [] });
const activeDivisionId = Vue.ref(Number((cfg.divisionContext && cfg.divisionContext.activeId) || 0));
function switchDivision(id) {
    const ctx = cfg.divisionContext || {};
    if (!ctx.updateUrl || !id || Number(id) === Number(ctx.activeId)) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = ctx.updateUrl;
    form.style.display = 'none';
    [['division_id', String(id)], ['_token', ctx.csrfToken || '']].forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}
return { /* existing */, divisionContext, activeDivisionId, switchDivision };
```

In the header template (next to welcome / pending chips), add:

```html
<div v-if="divisionContext.enabled" class="mt-3" style="max-width: 420px;">
  <div class="text-caption text-medium-emphasis mb-1">Acting division</div>
  <v-select
    v-model="activeDivisionId"
    :items="divisionContext.divisions"
    item-title="name"
    item-value="id"
    density="comfortable"
    hide-details
    variant="outlined"
    prepend-inner-icon="mdi-office-building-outline"
    @update:model-value="switchDivision"
  >
    <template #item="{ props, item }">
      <v-list-item v-bind="props" :subtitle="item.raw.is_primary ? 'Primary (contract)' : null"></v-list-item>
    </template>
  </v-select>
</div>
```

- [ ] **Step 3: Bump asset version**

In `apm-vuetify-runtime-scripts.blade.php` change `home-dashboard-app.js?v=6` to `?v=7` (or next integer).

- [ ] **Step 4: Manual smoke (local)**

1. Log into APM as a staff with ≥ 2 switchable divisions.
2. Open `/apm/home` — switcher visible.
3. Switch to secondary — flash success; create special memo / matrix — `division_id` is secondary.
4. Log out and back in — active resets to primary; switcher still lists both.

- [ ] **Step 5: Commit**

```bash
git add modules/apm/app/Http/Controllers/HomeController.php modules/apm/public/js/home-dashboard-app.js modules/apm/resources/views/partials/apm-vuetify-runtime-scripts.blade.php
git commit -m "$(cat <<'EOF'
Show division switcher on APM home dashboard.

EOF
)"
```

---

### Task 5: Sync / share API regression (non-breaking)

**Files:**
- Create: `modules/apm/tests/Unit/SyncStaffAssociatedDivisionsNormalizeTest.php` (test normalize via reflection or extract method to `StaffDivisionContext` / small helper)
- Create or extend: `modules/staff-portal/backend/tests/Feature/ShareStaffAssociatedDivisionsTest.php`
- Optional read-only check: finance/risk sync code paths ignore unknown keys (document in commit if already safe)

**Interfaces:**
- Confirms share returns `associated_divisions` array
- Confirms APM normalize turns JSON string / array / null into `list<int>`

- [ ] **Step 1: APM normalize test**

Prefer extracting `normalizeAssociatedDivisions` from `SyncStaffCommand` to `App\Support\AssociatedDivisions` with:

```php
public static function normalize(mixed $value): array
```

Then both sync and tests call it. Failing test first against the new class, then move the private method body.

- [ ] **Step 2: Share API test**

In staff-portal backend tests, assert that when a contract has `other_associated_divisions = [2]`, the share staff listing includes `associated_divisions` containing `2` (follow patterns in `StaffCreateApiTest` / `ShareReferenceDataService`).

- [ ] **Step 3: Run tests**

```bash
cd modules/apm && php artisan test --filter=AssociatedDivisions
cd modules/staff-portal/backend && php artisan test --filter=ShareStaffAssociatedDivisions
```

- [ ] **Step 4: Quick grep safety**

```bash
rg -n "associated_divisions" modules/finance/backend modules/risk-register/backend modules/helpdesk/backend --glob '*.php' | head -40
```

If a sync maps columns strictly and would error on unknown keys, add a one-line ignore/`Arr::only` — only if a test/reproduce shows breakage. Do not invent columns in finance/risk/helpdesk.

- [ ] **Step 5: Commit**

```bash
git add modules/apm/app/Support/AssociatedDivisions.php modules/apm/app/Console/Commands/SyncStaffCommand.php modules/apm/tests/Unit/SyncStaffAssociatedDivisionsNormalizeTest.php modules/staff-portal/backend/tests/Feature/ShareStaffAssociatedDivisionsTest.php
git commit -m "$(cat <<'EOF'
Harden associated_divisions sync and share coverage.

EOF
)"
```

---

### Task 6: Hot-path audit for raw session division reads

**Files:**
- Grep-driven fixes under `modules/apm` for create/list paths that bypass `user_session('division_id')`

- [ ] **Step 1: Grep**

```bash
cd /opt/homebrew/var/www/staff
rg -n "session\('user\.division_id'\)|session\('user'\)\['division_id'\]|\\\$user\['division_id'\]" modules/apm/app modules/apm/resources/views --glob '*.{php,blade.php}'
```

- [ ] **Step 2: Fix create/list hot paths** that set memo/matrix `division_id` from raw session to use `user_session('division_id')` or `StaffDivisionContext::activeDivisionId()`.

Skip pure display of “home division” labels that intentionally mean primary, if any.

- [ ] **Step 3: Run APM unit + feature tests from this plan**

```bash
cd modules/apm && php artisan test --filter='StaffDivisionContext|DivisionContext|AssociatedDivisions|UserSessionDivision'
```

- [ ] **Step 4: Commit if any code changes**

```bash
git add -A modules/apm
git commit -m "$(cat <<'EOF'
Use session division overlay on APM create and list paths.

EOF
)"
```

---

### Task 7: Push and verify

- [ ] **Step 1: Ensure all plan tests green**

```bash
cd modules/apm && php artisan test --filter='StaffDivisionContext|DivisionContext|AssociatedDivisions|UserSessionDivision'
cd modules/staff-portal/backend && php artisan test --filter=ShareStaffAssociatedDivisions
```

- [ ] **Step 2: Push**

```bash
git push origin HEAD
```

- [ ] **Step 3: Production smoke checklist** (after deploy)

1. Staff with Other associated divisions: switcher on home; create matrix in secondary division.
2. Focal person for another division (not on contract other list): division appears; can create matrix.
3. Finance officer only on division F (no other associations/roles): no switcher for F alone.
4. Finance officer who also has associated divisions: switcher works; approvals still route via finance_officer fields.
5. Logout/login: active division resets to primary.
6. Run APM `staff:sync` (or existing command name) — no errors; `associated_divisions` populated.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Union switchable set | Task 1 |
| Exclude finance-officer-only | Task 1 |
| Session overlay active division | Task 2 |
| Clear on login | Task 2 |
| Home switcher ≥ 2 | Task 4 |
| POST switch | Task 3 |
| Drives create/view via user_session | Tasks 2, 6 |
| JSON associated_divisions + sync | Task 5 |
| Share API non-breaking | Task 5 |
| FO can switch if multi-division staff | Tasks 1–4 |
| Session-only persistence | Tasks 2–3 |

## Placeholder scan

No TBD/TODO steps; commands and code included.
