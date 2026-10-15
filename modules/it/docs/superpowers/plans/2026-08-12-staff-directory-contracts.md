# Staff Directory + Contracts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Add contract type category, staff directory UX (filters/photo/counter/columns), create staff, and full contract management with one-current-contract enforcement.

**Architecture:** Extend `Modules/Staff` services and APIs; settings lookup for `contract_types.category`; Vue staff pages + column prefs in `localStorage`.

**Tech Stack:** Laravel, Vue 3, Vuetify, MySQL `staff` / `staff_contracts` / `contract_types`.

## Global Constraints

- Current statuses: `1,2,7`. On renew: previous ≠ `3` → `6` Renewed; previous `3` → leave Expired.
- Default list filter: `category=main_staff`.
- Leave module untouched.

---

### Task 1: Migration — contract_types.category

**Files:**
- Create: `staff-portal/backend/Modules/Lookup/database/migrations/2026_08_12_220000_add_category_to_contract_types.php`
- Modify: `staff-portal/backend/Modules/Settings/config/lookup-tables.php` (`contract_types` columns)
- Modify: Settings lookup Vue if it renders from config dynamically (verify `LookupSettings` / settings pages)

- [ ] **Step 1: Add migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_types')) {
            return;
        }
        if (! Schema::hasColumn('contract_types', 'category')) {
            Schema::table('contract_types', function (Blueprint $table): void {
                $table->string('category', 32)->default('main_staff')->after('contract_type');
            });
        }
        DB::table('contract_types')->whereNull('category')->orWhere('category', '')->update(['category' => 'main_staff']);
        DB::table('contract_types')->whereNotIn('category', ['main_staff', 'other_staff'])->update(['category' => 'main_staff']);
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_types') && Schema::hasColumn('contract_types', 'category')) {
            Schema::table('contract_types', function (Blueprint $table): void {
                $table->dropColumn('category');
            });
        }
    }
};
```

- [ ] **Step 2: Extend lookup-tables.php**

```php
'contract_types' => [
    'label' => 'Contract Types',
    'pk' => 'contract_type_id',
    'columns' => [
        'contract_type' => ['label' => 'Type', 'required' => true],
        'category' => [
            'label' => 'Category',
            'required' => true,
            'type' => 'select',
            'options' => [
                'main_staff' => 'Main staff',
                'other_staff' => 'Other staff',
            ],
        ],
    ],
    'order' => 'contract_type',
],
```

- [ ] **Step 3: Ensure settings CRUD supports select type** — read `SettingsApiController` / frontend lookup editor; if select unsupported, add `type=select` + options rendering.

- [ ] **Step 4: Run migration**

```bash
cd /opt/homebrew/var/www/staff/staff-portal/backend && php artisan migrate --path=Modules/Lookup/database/migrations/2026_08_12_220000_add_category_to_contract_types.php --force
```

Expected: `category` column exists; all rows `main_staff`.

---

### Task 2: Directory API — category filter + photo fields

**Files:**
- Modify: `Modules/Staff/app/Services/StaffDirectoryService.php`
- Modify: `Modules/Staff/app/Http/Controllers/Api/V1/StaffApiController.php`
- Modify: CSV export path in same controller/service
- Test: `Modules/Staff/tests/Feature/StaffDirectoryCategoryTest.php` (create if PHPUnit structure exists under module or `tests/Feature`)

**Interfaces:**
- Query param `category`: `main_staff` | `other_staff` | `all` (default `main_staff`)
- Row fields include `photo`, `contract_type`, `category`, existing directory fields

- [ ] **Step 1: Write failing feature test** asserting default excludes `other_staff` current contracts and includes `photo` key.

- [ ] **Step 2: Join `contract_types` on current contract in light/detail queries; filter by `ct.category` when not `all`.**

- [ ] **Step 3: Pass `category` through list + `export.csv`.**

- [ ] **Step 4: Manual tinker smoke** — `StaffDirectoryService::paginate` with category filters returns expected totals.

---

### Task 3: Staff directory Vue — counter, photo, columns, category filter

**Files:**
- Create: `frontend/src/lib/staffDirectoryColumns.ts` (column defs + localStorage load/save)
- Modify: `frontend/src/lib/staffApi.ts` — `category` param; types
- Modify: `frontend/src/pages/staff/StaffIndexPage.vue`
- Create (optional): `frontend/src/components/molecules/StaffColumnPicker.vue`

**Interfaces:**
- `localStorage` key: `staff-portal.staff-directory.columns.v1`
- Default visible: `photo`, `name`, `work_email`, `job`, `division`, `duty_station`, `contract_type`, `status`, `end_date`
- Always show `#` (computed `(page-1)*perPage + index + 1`) and Actions

- [ ] **Step 1: Add category chip group** — Main / Other / All (default Main).

- [ ] **Step 2: Add `#` and photo thumbnail (`<img>` or avatar; fallback initials).

- [ ] **Step 3: Column picker menu** — checkboxes for optional columns; persist prefs.

- [ ] **Step 4: Wire CSV export** with `category` + optional `columns` query if backend supports; else export current filter set.

- [ ] **Step 5: Add New Staff button** → `/staff/new` (page built in Task 5).

- [ ] **Step 6: `npm run build` in frontend.**

---

### Task 4: Contract uniqueness in StaffContractService

**Files:**
- Modify: `Modules/Staff/app/Services/StaffContractService.php`
- Test: `tests/Feature/StaffContractUniquenessTest.php` (or module tests)

**Interfaces:**
- `public const CURRENT_STATUSES = [1, 2, 7];`
- `assertNoConflictingCurrent(int $staffId, ?int $exceptContractId, int $incomingStatusId): void` throws `ValidationException` / domain exception
- `demotePreviousOnRenew(int $staffId, int $newContractId): void` — previous ≠ 3 → 6; leave 3; demote other stray current rows

- [ ] **Step 1: Failing test** — two Active inserts for same staff must fail (or second renew demotes first).

- [ ] **Step 2: Implement assert + demote in `create` / renew / `update` paths.**

- [ ] **Step 3: On renew create:** after insert, run demote logic on all other current rows; latest prior gets Renewed if not Expired.

- [ ] **Step 4: Run tests.**

---

### Task 5: Staff create API + Vue

**Files:**
- Create: `Modules/Staff/app/Services/StaffCreateService.php` (biodata + first contract transaction)
- Modify: `StaffApiController` — `store`, `lookups` for form
- Modify: `Modules/Staff/routes/api.php`
- Create: `frontend/src/pages/staff/StaffNewPage.vue`
- Modify: `frontend/src/router/index.ts` — `/staff/new`
- Modify: `frontend/src/lib/staffApi.ts` — `createStaff`, `fetchStaffFormLookups`

**Validation:** work_email unique; age ≥ 18; end > start; required CI3 fields; first contract `status_id=1`.

- [ ] **Step 1: API `POST /api/v1/staff` + `GET /api/v1/staff/form-lookups`.**

- [ ] **Step 2: Vue multi-section form (biodata + contract); on success navigate to `/staff/:id`.**

- [ ] **Step 3: Permission gate** — manage staff (71) required.

---

### Task 6: Contract CRUD API + staff show UI

**Files:**
- Modify: `StaffApiController` — store/update contract endpoints
- Modify: `Modules/Staff/routes/api.php`
- Modify: `frontend/src/pages/staff/StaffShowPage.vue` — Add/Renew + Edit dialogs
- Modify: `frontend/src/lib/staffApi.ts`

**Routes:**
- `POST /api/v1/staff/{id}/contracts`
- `PUT /api/v1/staff/{id}/contracts/{contractId}`
- `GET /api/v1/staff/{id}/contract-lookups` (optional; reuse form-lookups)

- [ ] **Step 1: Wire controller methods to `StaffContractService`.**

- [ ] **Step 2: Vue contract history + forms; surface 422 uniqueness errors.**

- [ ] **Step 3: Manual smoke:** create staff → renew → verify prior Renewed; try force second Active via API → 422.

---

### Task 7: Frontend build + smoke

- [ ] `cd staff-portal/frontend && npm run build`
- [ ] Hit `/staff`, `/staff/new`, staff show contracts with hard refresh
