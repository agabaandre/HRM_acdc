# Activity Reports + Reporting & Finance Registry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship one shared Activity Report per in-scope memo, a permission-gated Reporting & Finance registry on `/reports`, HOD approval, reminders, related-activity tags, AI synthesis via portal AI providers, table preview, and `/api/apm/v1` endpoints.

**Architecture:** Mirror ARF: `ActivityReport` + `HasApprovalWorkflow`, dedicated `reporting_registry_statuses`, staff permission `apm_reporting_finance_registry`, daily reminder command, Vuetify form/registry UI, thin OpenAI-compatible client reading shared `portal_ai_providers`.

**Tech Stack:** Laravel (APM module), Blade, Vue 3 + Vuetify 3 (CDN), PHPUnit/Pest, staff-portal permissions + `portal_ai_providers`

**Spec:** `modules/apm/documentation/superpowers/specs/2026-10-12-activity-reports-design.md`

## Global Constraints

- One report per `(source_id, source_type)`; multi-contributor shared draft; last-save wins
- Scope: Activity (incl. single memo), SpecialMemo, NonTravelMemo with `pra_activity_id`
- Editors: `responsible_person_id` + `staff_id` + `internal_participants` staff IDs
- Related activities: context tags only; AI may include when checkbox on
- Registry Start/Process: permission name `apm_reporting_finance_registry` only
- Reminders: current quarter; **all types = end date + 7d** (NT: `date_to` else `memo_date`); not gated on ARF/SR or registry processed; draft does not stop; stop after first submit; `not_executed` stops
- Scope includes extramural/external funded (`fund_type_id` 2/3) activities/single/special/NT (NT also via `pra_activity_id`)
- AI keys: never store in APM; use `portal_ai_providers`
- No Conventional Commit prefixes in commit subjects (match repo style)

## File map

| File | Responsibility |
|------|----------------|
| `modules/apm/database/migrations/2026_10_12_100000_create_activity_reports_tables.php` | `activity_reports`, `activity_report_related`, `reporting_registry_statuses` |
| `modules/apm/database/migrations/2026_10_12_100100_seed_apm_reporting_finance_registry_permission.php` | Insert permission row |
| `modules/apm/database/migrations/2026_10_12_100200_seed_activity_report_workflow.php` | Workflow + HOD definition (if none reusable) |
| `modules/apm/app/Models/ActivityReport.php` | Eloquent model |
| `modules/apm/app/Models/ReportingRegistryStatus.php` | Registry process row |
| `modules/apm/app/Support/ActivityReportPermission.php` | Resolve permission id by name; `userCanProcessRegistry()` |
| `modules/apm/app/Services/ActivityReport/ActivityReportAccessService.php` | Who can view/edit/submit |
| `modules/apm/app/Services/ActivityReport/ActivityReportAutofillService.php` | Prefill from source memo/PRA |
| `modules/apm/app/Services/ActivityReport/ActivityReportRegistryService.php` | Quarter list, start, process |
| `modules/apm/app/Services/ActivityReport/ActivityReportReminderService.php` | Eligibility + notify |
| `modules/apm/app/Services/ActivityReport/ActivityReportAiService.php` | Context build + chat via providers |
| `modules/apm/app/Services/ActivityReport/PortalAiChatClient.php` | HTTP chat completions against portal providers |
| `modules/apm/app/Http/Controllers/ActivityReportController.php` | Web CRUD, submit, approve, return, preview |
| `modules/apm/app/Http/Controllers/ReportingRegistryController.php` | Registry index + JSON actions |
| `modules/apm/app/Http/Controllers/Api/ApmActivityReportController.php` | JWT API |
| `modules/apm/app/Console/Commands/SendActivityReportRemindersCommand.php` | `activity-reports:remind` |
| `modules/apm/app/Providers/ScheduleServiceProvider.php` | Schedule daily reminder |
| `modules/apm/app/Services/PendingApprovalsService.php` | Include ActivityReport |
| `modules/apm/routes/web.php` | Web routes |
| `modules/apm/routes/api.php` | API routes |
| `modules/apm/resources/views/pages/reports-index-content.blade.php` | Registry card link |
| `modules/apm/resources/views/activity-reports/*` | Index/form/show/print blades |
| `modules/apm/public/js/reporting-registry-app.js` | Registry table + preview |
| `modules/apm/public/js/activity-report-form-app.js` | Form, related tags, AI panel |
| `modules/apm/tests/Unit/ActivityReport/*` | Unit tests |
| `modules/apm/tests/Feature/ActivityReport/*` | Feature tests |

---

### Task 1: Migrations — tables + permission

**Files:**
- Create: `modules/apm/database/migrations/2026_10_12_100000_create_activity_reports_tables.php`
- Create: `modules/apm/database/migrations/2026_10_12_100100_seed_apm_reporting_finance_registry_permission.php`
- Test: `modules/apm/tests/Unit/ActivityReport/ActivityReportSchemaTest.php`

**Interfaces:**
- Produces tables: `activity_reports`, `activity_report_related`, `reporting_registry_statuses`
- Produces permission name: `apm_reporting_finance_registry`

- [ ] **Step 1: Write failing schema smoke test**

```php
<?php

namespace Tests\Unit\ActivityReport;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityReportSchemaTest extends TestCase
{
    public function test_activity_reports_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('activity_reports'));
        $this->assertTrue(Schema::hasTable('activity_report_related'));
        $this->assertTrue(Schema::hasTable('reporting_registry_statuses'));
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `cd modules/apm && php artisan test --filter=ActivityReportSchemaTest`

Expected: FAIL (tables missing)

- [ ] **Step 3: Create tables migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('source_type');
            $table->string('mission_type')->nullable();
            $table->string('document_number')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedBigInteger('responsible_person_id')->nullable();
            $table->unsignedBigInteger('forward_workflow_id')->nullable();
            $table->unsignedBigInteger('reverse_workflow_id')->nullable();
            $table->string('approval_level')->nullable();
            $table->string('next_approval_level')->nullable();
            $table->string('overall_status')->default('draft');
            $table->json('approval_order_map')->nullable();
            $table->boolean('not_executed')->default(false);
            $table->boolean('has_been_submitted')->default(false);
            // Autofill snapshots
            $table->text('tier2')->nullable();
            $table->text('tier4')->nullable();
            $table->string('activity_name_code')->nullable();
            $table->text('broad_activity_amert')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->longText('objectives')->nullable();
            // Editable core
            $table->string('location_venue')->nullable();
            $table->unsignedBigInteger('lead_organiser_staff_id')->nullable();
            $table->json('other_organiser_staff_ids')->nullable();
            $table->unsignedInteger('participants_count')->nullable();
            $table->longText('member_states_covered')->nullable();
            $table->longText('key_strategic_needs')->nullable();
            $table->longText('narrative_achievements')->nullable();
            $table->longText('challenges_recommendations')->nullable();
            $table->longText('means_of_verification')->nullable();
            $table->string('budget_code')->nullable();
            $table->decimal('total_cost_usd', 15, 2)->nullable();
            $table->longText('logistics_planned')->nullable();
            $table->longText('logistics_actuals')->nullable();
            $table->json('mission_extras')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'source_type'], 'activity_reports_source_unique');
            $table->index(['overall_status', 'division_id']);
        });

        Schema::create('activity_report_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_report_id')->constrained('activity_reports')->cascadeOnDelete();
            $table->unsignedBigInteger('related_source_id');
            $table->string('related_source_type');
            $table->timestamps();
            $table->unique(
                ['activity_report_id', 'related_source_id', 'related_source_type'],
                'activity_report_related_unique'
            );
        });

        Schema::create('reporting_registry_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('source_type');
            $table->timestamp('started_at')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'source_type'], 'reporting_registry_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_report_related');
        Schema::dropIfExists('activity_reports');
        Schema::dropIfExists('reporting_registry_statuses');
    }
};
```

- [ ] **Step 4: Seed permission migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $name = 'apm_reporting_finance_registry';
        if (DB::table('permissions')->where('name', $name)->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => $name,
            'definition' => 'APM Reporting & Finance Registry',
            'module' => 'apm',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }
        DB::table('permissions')->where('name', 'apm_reporting_finance_registry')->delete();
    }
};
```

Match `PermissionsService::createPermission` columns: `name`, `definition`, `module`.

- [ ] **Step 5: Migrate and re-run test**

Run: `cd modules/apm && php artisan migrate --force && php artisan test --filter=ActivityReportSchemaTest`

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add modules/apm/database/migrations/2026_10_12_100000_create_activity_reports_tables.php \
  modules/apm/database/migrations/2026_10_12_100100_seed_apm_reporting_finance_registry_permission.php \
  modules/apm/tests/Unit/ActivityReport/ActivityReportSchemaTest.php
git commit -m "$(cat <<'EOF'
Add Activity Report tables and registry permission.

EOF
)"
```

---

### Task 2: Models + permission helper + access service

**Files:**
- Create: `modules/apm/app/Models/ActivityReport.php`
- Create: `modules/apm/app/Models/ReportingRegistryStatus.php`
- Create: `modules/apm/app/Support/ActivityReportPermission.php`
- Create: `modules/apm/app/Services/ActivityReport/ActivityReportAccessService.php`
- Test: `modules/apm/tests/Unit/ActivityReport/ActivityReportAccessServiceTest.php`

**Interfaces:**
- Produces:
  - `ActivityReportPermission::NAME = 'apm_reporting_finance_registry'`
  - `ActivityReportPermission::id(): ?int`
  - `ActivityReportPermission::userCanProcess(?array $permissionIds = null): bool`
  - `ActivityReportAccessService::canEdit(ActivityReport|object $source, int $staffId): bool`
  - `ActivityReportAccessService::canView(...): bool`
  - `ActivityReportAccessService::participantStaffIds(object $source): array`

- [ ] **Step 1: Write failing access tests**

```php
<?php

namespace Tests\Unit\ActivityReport;

use App\Services\ActivityReport\ActivityReportAccessService;
use PHPUnit\Framework\TestCase;

class ActivityReportAccessServiceTest extends TestCase
{
    public function test_responsible_and_staff_and_participants_can_edit(): void
    {
        $svc = new ActivityReportAccessService();
        $source = (object) [
            'staff_id' => 10,
            'responsible_person_id' => 20,
            'internal_participants' => ['30' => ['days' => 1], '40' => ['days' => 2]],
        ];

        $this->assertTrue($svc->canEdit($source, 10));
        $this->assertTrue($svc->canEdit($source, 20));
        $this->assertTrue($svc->canEdit($source, 30));
        $this->assertFalse($svc->canEdit($source, 99));
    }
}
```

- [ ] **Step 2: Run — expect FAIL** (class missing)

Run: `cd modules/apm && php artisan test --filter=ActivityReportAccessServiceTest`

- [ ] **Step 3: Implement models + permission + access**

`ActivityReport` uses `HasApprovalWorkflow`, `HasDocumentNumber`, `Auditable`; morph `source()`; `related()` belongsToMany via pivot; casts for JSON/dates/bools; fillable matching migration.

`ReportingRegistryStatus` fillable: source_id, source_type, started_at, started_by, processed_at, processed_by.

`ActivityReportPermission`:

```php
public static function id(): ?int
{
    if (! \Schema::hasTable('permissions')) {
        return null;
    }
    $id = \DB::table('permissions')->where('name', self::NAME)->value('id');

    return $id !== null ? (int) $id : null;
}

public static function userCanProcess(?array $permissionIds = null): bool
{
    $permId = self::id();
    if ($permId === null) {
        return false;
    }
    $ids = $permissionIds ?? (user_session('permissions', []) ?? []);

    return in_array($permId, array_map('intval', (array) $ids), true);
}
```

`ActivityReportAccessService::participantStaffIds` flattens `staff_id`, `responsible_person_id`, and `internal_participants` (reuse Activity’s flattening approach). `canEdit` true if `$staffId` in that set and report not locked (`overall_status` not in submitted/approved unless returned). Treat `overall_status` in `['draft','returned']` as editable; `submitted`/`pending`/`approved` not editable by participants (HOD uses approve/return).

- [ ] **Step 4: Run tests — PASS**

- [ ] **Step 5: Commit**

```bash
git add modules/apm/app/Models/ActivityReport.php \
  modules/apm/app/Models/ReportingRegistryStatus.php \
  modules/apm/app/Support/ActivityReportPermission.php \
  modules/apm/app/Services/ActivityReport/ActivityReportAccessService.php \
  modules/apm/tests/Unit/ActivityReport/ActivityReportAccessServiceTest.php
git commit -m "$(cat <<'EOF'
Add Activity Report models and access checks.

EOF
)"
```

---

### Task 3: Autofill + create-or-get report service

**Files:**
- Create: `modules/apm/app/Services/ActivityReport/ActivityReportAutofillService.php`
- Create: `modules/apm/app/Services/ActivityReport/ActivityReportService.php`
- Test: `modules/apm/tests/Unit/ActivityReport/ActivityReportServiceTest.php`

**Interfaces:**
- Produces:
  - `ActivityReportAutofillService::fromSource(Model $source): array` (attribute bag)
  - `ActivityReportService::findOrCreateForSource(Model $source, int $actorStaffId): ActivityReport`
  - `ActivityReportService::MISSION_TYPES` constant list matching PDF

- [ ] **Step 1: Failing test — unique findOrCreate**

```php
public function test_find_or_create_returns_same_report_twice(): void
{
    // Arrange a NonTravelMemo or Activity factory/fixture with pra_activity_id
    $svc = app(\App\Services\ActivityReport\ActivityReportService::class);
    $first = $svc->findOrCreateForSource($source, $actorStaffId);
    $second = $svc->findOrCreateForSource($source, $actorStaffId);
    $this->assertSame($first->id, $second->id);
}
```

Use existing APM test factories/fixtures if present; otherwise create minimal DB rows in the test.

- [ ] **Step 2: Implement autofill**

Map from source:
- dates → start_date, end_date, duration_days
- activity_title / codes → activity_name_code
- division_id, staff_id, responsible_person_id
- objectives from memo when mission type is not `in_office` / `others`
- logistics_planned from available budget/memo fields when present
- PRA/AWP tier fields when columns/relations exist; else leave null

- [ ] **Step 3: Implement findOrCreateForSource**

```php
public function findOrCreateForSource(Model $source, int $actorStaffId): ActivityReport
{
    $existing = ActivityReport::query()
        ->where('source_id', $source->getKey())
        ->where('source_type', $source::class)
        ->first();
    if ($existing) {
        return $existing;
    }

    $attrs = $this->autofill->fromSource($source);
    $attrs['source_id'] = $source->getKey();
    $attrs['source_type'] = $source::class;
    $attrs['overall_status'] = 'draft';
    $attrs['created_by'] = $actorStaffId;
    $attrs['updated_by'] = $actorStaffId;
    // Assign forward_workflow_id from seeded Activity Report workflow

    return ActivityReport::query()->create($attrs);
}
```

Handle unique constraint race with try/catch + re-fetch.

- [ ] **Step 4: Tests PASS + commit**

```bash
git commit -m "$(cat <<'EOF'
Add Activity Report autofill and create-or-get.

EOF
)"
```

---

### Task 4: Registry service + web controller + card

**Files:**
- Create: `modules/apm/app/Services/ActivityReport/ActivityReportRegistryService.php`
- Create: `modules/apm/app/Http/Controllers/ReportingRegistryController.php`
- Modify: `modules/apm/routes/web.php`
- Modify: `modules/apm/resources/views/pages/reports-index-content.blade.php`
- Create: `modules/apm/resources/views/reporting-registry/index.blade.php`
- Create: `modules/apm/public/js/reporting-registry-app.js`
- Test: `modules/apm/tests/Unit/ActivityReport/ActivityReportRegistryServiceTest.php`

**Interfaces:**
- Produces:
  - `listCurrentQuarter(?int $divisionId = null): array`
  - `markStarted(string $sourceType, int $sourceId, int $staffId): ReportingRegistryStatus`
  - `markProcessed(string $sourceType, int $sourceId, int $staffId): ReportingRegistryStatus`
  - Routes: `reporting-registry.index`, `.data`, `.start`, `.process`, `.preview`

- [ ] **Step 1: Failing tests**

```php
public function test_mark_processed_requires_permission_checked_by_caller(): void
{
    $svc = app(ActivityReportRegistryService::class);
    $row = $svc->markStarted(NonTravelMemo::class, $id, 1);
    $this->assertNotNull($row->started_at);
    $row = $svc->markProcessed(NonTravelMemo::class, $id, 1);
    $this->assertNotNull($row->processed_at);
}

public function test_list_current_quarter_includes_nt_with_pra_only(): void
{
    // NT without pra_activity_id excluded; with pra included
}
```

- [ ] **Step 2: Implement registry service**

`listCurrentQuarter`:
- Compute quarter start/end from `now()`
- Query Activities (matrix + single) whose date range overlaps quarter
- Special memos overlapping quarter
- NonTravelMemo where `pra_activity_id` not null and dates overlap
- Left join `reporting_registry_statuses` and `activity_reports` for status columns
- Return rows: id, type, document_number, title, division, start/end, process_status, report_status, urls

`markStarted` / `markProcessed`: upsert registry row; process may auto-set started if null.

- [ ] **Step 3: Controller**

- `index`: blade + pageConfig (canProcess from `ActivityReportPermission::userCanProcess()`, data URL)
- `data`: JSON list
- `start`/`process`: abort 403 unless permission; then service
- `preview`: load report by source; return JSON payload for modal (404 if none)

- [ ] **Step 4: UI**

Add card on reports index linking to `route('reporting-registry.index')`.

`reporting-registry-app.js`: Vuetify data table; Start/Process buttons if `canProcess`; Open report; Preview dialog; Not executed action calling report update endpoint (Task 5).

- [ ] **Step 5: Register routes in session auth group in `web.php`**

- [ ] **Step 6: Tests PASS + commit**

```bash
git commit -m "$(cat <<'EOF'
Add Reporting and Finance registry list and process actions.

EOF
)"
```

---

### Task 5: Report form UI + web save/submit + related tags

**Files:**
- Create: `modules/apm/app/Http/Controllers/ActivityReportController.php`
- Create: `modules/apm/resources/views/activity-reports/form.blade.php`
- Create: `modules/apm/resources/views/activity-reports/show.blade.php`
- Create: `modules/apm/public/js/activity-report-form-app.js`
- Modify: `modules/apm/routes/web.php`
- Modify: `modules/apm/resources/views/partials/apm-vuetify-runtime-scripts.blade.php` (load script)
- Test: `modules/apm/tests/Feature/ActivityReport/ActivityReportUpdateTest.php`

**Interfaces:**
- Routes: `activity-reports.create-or-open`, `show`, `update`, `submit`, `related.search`, `related.sync`, `not-executed`
- Produces update validation matching fillable editable fields + `mission_type` + `mission_extras`

- [ ] **Step 1: Feature test — participant can update draft; stranger 403**

- [ ] **Step 2: Controller methods**

`createOrOpen(sourceType, sourceId)`: resolve model, access check, findOrCreate, redirect to form.

`update`: access + editable status; set `updated_by`; save fields.

`submit`: validate required core fields; set `overall_status` pending; `has_been_submitted=true`; `submitted_at=now()`; kick workflow like ARF `submitForApproval`.

`syncRelated`: replace pivot rows; related are context only.

`searchRelated`: search memos by title/document_number excluding self; return id/type/label for Vuetify autocomplete checkboxes.

`setNotExecuted`: participants only; sets flag; optional clear reminders obligation.

- [ ] **Step 3: Form app**

Top: related activities `v-autocomplete` multiple chips + checkboxes pattern (same UX spirit as PRA multi-select in `matrices-index-app.js`).

Mission type select; show/hide extras for official_engagement / events / training.

Save draft; Submit; AI panel stub section (wired in Task 8).

- [ ] **Step 4: Tests PASS + commit**

```bash
git commit -m "$(cat <<'EOF'
Add Activity Report form with related activity tags.

EOF
)"
```

---

### Task 6: Workflow seed + HOD approve/return + PendingApprovalsService

**Files:**
- Create: `modules/apm/database/migrations/2026_10_12_100200_seed_activity_report_workflow.php`
- Modify: `modules/apm/app/Http/Controllers/ActivityReportController.php` (approve/return)
- Modify: `modules/apm/app/Services/PendingApprovalsService.php`
- Modify: home/nav counts helpers if they hardcode types
- Test: `modules/apm/tests/Feature/ActivityReport/ActivityReportApprovalTest.php`

**Interfaces:**
- Produces workflow name `Activity Report` with division-specific HOD step (mirror ARF/special memo definition pattern)
- `getPendingActivityReports(...)` merged in `getPendingApprovals()`

- [ ] **Step 1: Seed workflow**

Insert into `workflows` / `workflow_definitions` / `approvers` following an existing HOD-only workflow in DB (inspect live seeds for ARF). Store workflow id on new reports in `findOrCreateForSource`.

- [ ] **Step 2: approve / return actions**

Copy patterns from `RequestARFController::approve` / return paths; on return set `overall_status=returned` so collaborators can edit.

- [ ] **Step 3: PendingApprovalsService**

Add `getPendingActivityReports` analogous to `getPendingARFRequests` (lines ~666–733), merge in `getPendingApprovals`, include in summary counts.

- [ ] **Step 4: Feature tests for approve/return + pending inclusion**

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
Wire Activity Report HOD workflow and pending approvals.

EOF
)"
```

---

### Task 7: Reminders command + schedule

**Files:**
- Create: `modules/apm/app/Services/ActivityReport/ActivityReportReminderService.php`
- Create: `modules/apm/app/Console/Commands/SendActivityReportRemindersCommand.php`
- Modify: `modules/apm/app/Providers/ScheduleServiceProvider.php`
- Test: `modules/apm/tests/Unit/ActivityReport/ActivityReportReminderServiceTest.php`

**Interfaces:**
- Produces:
  - `ActivityReportReminderService::eligibleSources(\Carbon\Carbon $today): Collection`
  - `notifyEligible(): int` (count notified)
  - Command: `activity-reports:remind`
  - Schedule: dailyAt `09:30` (offset from other reminders)

- [ ] **Step 1: Unit tests for eligibility matrix**

Cases:
1. NT processed 8 days ago, no report → eligible
2. NT processed 3 days ago → not eligible
3. Activity end date 8 days ago, not processed → eligible
4. Report draft only → still eligible
5. Report `has_been_submitted=true` → not eligible
6. `not_executed=true` → not eligible
7. Outside current quarter → not eligible

- [ ] **Step 2: Implement service**

For each in-scope source in current quarter:
- Resolve due date: NT uses registry `processed_at`; others use `end_date`
- Eligible if due date + 7 days ≤ today
- Skip if report missing? **No — missing report is eligible** (same as draft)
- Skip if report `not_executed` or `has_been_submitted`
- Collect staff_id + responsible + participants; send via existing notification/email job pattern (`SendNotificationEmailJob` or memo reminder style)

- [ ] **Step 3: Command + schedule**

```php
$schedule->command('activity-reports:remind')
    ->dailyAt('09:30')
    ->withoutOverlapping()
    ->runInBackground();
```

- [ ] **Step 4: Tests PASS + commit**

```bash
git commit -m "$(cat <<'EOF'
Add Activity Report overdue reminder schedule.

EOF
)"
```

---

### Task 8: AI chat client + form panel

**Files:**
- Create: `modules/apm/app/Services/ActivityReport/PortalAiChatClient.php`
- Create: `modules/apm/app/Services/ActivityReport/ActivityReportAiService.php`
- Modify: `modules/apm/app/Http/Controllers/ActivityReportController.php` (`aiChat`)
- Modify: `modules/apm/public/js/activity-report-form-app.js`
- Test: `modules/apm/tests/Unit/ActivityReport/ActivityReportAiServiceTest.php`

**Interfaces:**
- Produces:
  - `PortalAiChatClient::chat(string $system, string $user, ?string $providerUuid = null): array{ok:bool,reply:?string,error:?string}`
  - `ActivityReportAiService::buildContext(ActivityReport $report, bool $includeRelated): string`
  - `ActivityReportAiService::chat(ActivityReport $report, string $prompt, bool $includeRelated, ?string $providerUuid): array`

- [ ] **Step 1: Unit test context includes related titles only when flag true**

- [ ] **Step 2: PortalAiChatClient**

Read `portal_ai_providers` (shared DB). Decrypt key the same way staff-portal does if encryption key is shared; if decrypt fails, return clear error asking admin to configure Settings → AI providers.

POST `{endpoint}/chat/completions` OpenAI-compatible body (copy field rules from `PortalAiCompatibleClient::completionLimitFields`).

- [ ] **Step 3: ActivityReportAiService**

Build system prompt: synthesize Africa CDC activity report fields; user prompt + serialized draft; if `$includeRelated`, append tagged related activity titles/objectives/dates (not full form merge).

Return assistant text; UI offers “Apply to narrative_achievements” (or field picker) with confirm — never auto-overwrite without confirm.

- [ ] **Step 4: Form AI panel**

Checkbox **Include related activities**; provider select (list active providers via small JSON endpoint); send; show reply; Apply button.

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
Add Activity Report AI synthesis via portal providers.

EOF
)"
```

---

### Task 9: Preview + print

**Files:**
- Modify: `modules/apm/app/Http/Controllers/ActivityReportController.php` (`preview`, `print`)
- Create: `modules/apm/resources/views/activity-reports/print.blade.php`
- Modify: `modules/apm/public/js/reporting-registry-app.js` (preview drawer)
- Test: `modules/apm/tests/Feature/ActivityReport/ActivityReportPreviewTest.php`

**Interfaces:**
- `GET activity-reports/{id}/preview` → JSON sections
- `GET activity-reports/{id}/print` → printable blade

- [ ] **Step 1: Feature test preview JSON keys include mission_type and related labels**

- [ ] **Step 2: Implement preview presenter on ActivityReportService::previewPayload(ActivityReport $r): array`

- [ ] **Step 3: Registry Preview opens modal bound to payload; print link opens print view**

- [ ] **Step 4: Commit**

```bash
git commit -m "$(cat <<'EOF'
Add Activity Report preview and print views.

EOF
)"
```

---

### Task 10: JWT API `/api/apm/v1`

**Files:**
- Create: `modules/apm/app/Http/Controllers/Api/ApmActivityReportController.php`
- Modify: `modules/apm/routes/api.php`
- Modify: `modules/apm/app/Http/Controllers/Api/ApmDocumentController.php` (optional type `activity_report` if list patterns need it)
- Test: `modules/apm/tests/Feature/ActivityReport/ApmActivityReportApiTest.php`

**Interfaces:**
- `GET activity-reports/registry`
- `POST activity-reports/registry/{type}/{id}/start|process`
- `GET|POST activity-reports/by-source/{type}/{id}` (create-or-get)
- `PUT activity-reports/{id}`
- `POST activity-reports/{id}/submit`
- `POST activity-reports/{id}/approve|return`
- `GET activity-reports/{id}/preview`
- `POST activity-reports/{id}/ai/chat` body: `{prompt, include_related, provider_uuid?}`
- `GET activity-reports/related/search?q=`
- `PUT activity-reports/{id}/related`

Reuse `ActivityReport*` services; auth via `auth:api` + `apm.api.context`; map staff_id from API user.

- [ ] **Step 1: Feature tests with actingAs API user — process 403 without permission; edit 403 for non-participant**

- [ ] **Step 2: Implement controller + routes inside `auth:api` group**

- [ ] **Step 3: Commit**

```bash
git commit -m "$(cat <<'EOF'
Expose Activity Report endpoints on APM API v1.

EOF
)"
```

---

### Task 11: Smoke polish + docs touch

**Files:**
- Modify: `modules/apm/documentation/superpowers/specs/2026-10-12-activity-reports-design.md` (Status → Implemented) only if all tasks done
- Manual smoke checklist in commit message body optional

- [ ] **Step 1: Manual smoke**

1. Assign `apm_reporting_finance_registry` at `/staff/permissions`
2. Open `/reports` → registry → Start/Process on a NT with PRA
3. Open report as participant → tag related → save → AI chat with include related → submit
4. HOD sees pending → return → edit → resubmit → approve
5. Confirm reminder dry-run: `php artisan activity-reports:remind` after seeding due rows
6. Preview from table; print view

- [ ] **Step 2: Final commit if any polish**

```bash
git commit -m "$(cat <<'EOF'
Polish Activity Report registry and form edge cases.

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| One shared report / unique source | 1, 3 |
| Collaborative editors | 2, 5 |
| Related tags context-only | 5, 8 |
| Registry + permission | 1, 4 |
| Reminders rules | 7 |
| HOD + pending approvals | 6 |
| AI via portal providers | 8 |
| Table preview | 4, 9 |
| API v1 | 10 |
| PDF field mapping / mission types | 3, 5 |
| Not executed | 5, 7 |

## Placeholder / consistency self-review

- Permission name fixed: `apm_reporting_finance_registry`
- Reminder stop flag: `has_been_submitted` (not merely current status)
- Registry table: `reporting_registry_statuses` (not memo columns)
- AI: `PortalAiChatClient` + `include_related` flag naming consistent across web/API
- Mission types: official_engagement, training, events, procurement, in_office, recruitment_hr, others
