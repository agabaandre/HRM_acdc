# Business Unit Mailbox Intake Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Poll each Business Unit’s Exchange mailbox every minute via Microsoft Graph, create email tickets, AI-categorize/assign (admin least-load fallback), mark read + move to Processed; edit BUs in a settings modal.

**Architecture:** App-only Graph client lists unread Inbox mail for BUs with `email_intake_enabled` + `support_mailbox`. `BusinessUnitMailboxIntakeService` creates tickets (`source=email`), records `helpdesk_email_messages` for idempotency, dispatches existing `CategorizeTicketWithAi`, then marks read and moves to folder `Processed`. Scheduler runs `PollBusinessUnitMailboxesJob` every minute with `withoutOverlapping`.

**Tech Stack:** Laravel (jobs/schedule), Microsoft Graph REST, existing `EXCHANGE_*` OAuth, Vue/Vuetify settings modal, PHPUnit feature tests with Graph HTTP faked.

**Spec:** `docs/superpowers/specs/2026-07-22-bu-mailbox-intake-design.md`

## Global Constraints

- Mailbox address and intake toggle live on **Business Unit**, not General settings.
- IT & MIS seed: `support_mailbox=helpdesk@africacdc.org`, `email_intake_enabled=true`.
- Poll cadence: **every 1 minute**.
- After successful ticket create: **mark read + move to Processed**.
- Reuse `CategorizeTicketWithAi` / `TicketAssignmentService::assignAdminRoundRobin` for uncategorized.
- Credentials: existing `EXCHANGE_*` env (client credentials); Azure must grant Mail.ReadWrite (or Read + move-capable permission) on mailboxes.
- Max **25** unread messages per BU per poll run.
- Do not commit unless the user asks (workspace git rule).

## File map

| Path | Responsibility |
|------|----------------|
| `backend/database/migrations/2026_07_22_160000_bu_mailbox_intake.php` | BU columns + `helpdesk_email_messages` |
| `backend/app/Models/HelpdeskBusinessUnit.php` | fillable/casts |
| `backend/app/Models/HelpdeskEmailMessage.php` | idempotency rows |
| `backend/app/Services/ExchangeGraphMailReader.php` | Graph list/mark/move/ensure folder |
| `backend/app/Services/BusinessUnitMailboxIntakeService.php` | orchestrate create + categorize dispatch + Graph post-process |
| `backend/app/Jobs/PollBusinessUnitMailboxesJob.php` | scheduled entry |
| `backend/bootstrap/app.php` | `everyMinute()->withoutOverlapping()` |
| `backend/app/Http/Controllers/Api/V1/Admin/AdminHelpdeskBusinessUnitController.php` | validate new fields |
| `backend/app/Services/StaffDirectoryLookupService.php` | `resolveByWorkEmail(string): ?array` (+ staff id) |
| `frontend/.../CategoriesManagementPanel.vue` | lean table + Add/Edit modal |
| `backend/tests/Feature/BusinessUnitMailboxIntakeTest.php` | intake + idempotency |
| `documentation/ADMIN_GUIDE.md`, `INTEGRATION.md` | Azure + ops notes |

---

### Task 1: Migration, models, BU admin API fields

**Files:**
- Create: `helpdesk/backend/database/migrations/2026_07_22_160000_bu_mailbox_intake.php`
- Modify: `helpdesk/backend/app/Models/HelpdeskBusinessUnit.php`
- Create: `helpdesk/backend/app/Models/HelpdeskEmailMessage.php`
- Modify: `helpdesk/backend/app/Http/Controllers/Api/V1/Admin/AdminHelpdeskBusinessUnitController.php`
- Modify: `helpdesk/backend/app/Http/Controllers/Api/V1/BusinessUnitController.php` (expose mailbox fields only to admins if public index should stay lean — **expose on admin index only**; public BU list may omit mailbox)

**Interfaces:**
- Produces: `HelpdeskBusinessUnit.support_mailbox: ?string`, `email_intake_enabled: bool`; `HelpdeskEmailMessage` with unique `graph_message_id`

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
        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_business_units', 'support_mailbox')) {
                $table->string('support_mailbox', 191)->nullable()->after('allows_asset_link_on_resolve');
            }
            if (! Schema::hasColumn('helpdesk_business_units', 'email_intake_enabled')) {
                $table->boolean('email_intake_enabled')->default(false)->after('support_mailbox');
            }
        });

        DB::table('helpdesk_business_units')
            ->where('slug', 'it-mis')
            ->update([
                'support_mailbox' => 'helpdesk@africacdc.org',
                'email_intake_enabled' => true,
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('helpdesk_email_messages')) {
            Schema::create('helpdesk_email_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_unit_id')->constrained('helpdesk_business_units')->cascadeOnDelete();
                $table->string('graph_message_id', 191)->unique();
                $table->string('internet_message_id', 512)->nullable()->index();
                $table->foreignId('ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
                $table->string('from_email', 191)->nullable();
                $table->string('subject', 500)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->json('raw_meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_email_messages');
        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_business_units', 'email_intake_enabled')) {
                $table->dropColumn('email_intake_enabled');
            }
            if (Schema::hasColumn('helpdesk_business_units', 'support_mailbox')) {
                $table->dropColumn('support_mailbox');
            }
        });
    }
};
```

- [ ] **Step 2: Update `HelpdeskBusinessUnit` fillable/casts** — add `support_mailbox`, `email_intake_enabled` (boolean).

- [ ] **Step 3: Create `HelpdeskEmailMessage` model** — table `helpdesk_email_messages`, fillable matching columns, casts `raw_meta` => array, `processed_at` => datetime; relations `businessUnit()`, `ticket()`.

- [ ] **Step 4: Admin controller validation** — on store/update accept:
  - `support_mailbox` => nullable email max 191
  - `email_intake_enabled` => sometimes boolean  
  Persist both on create/update.

- [ ] **Step 5: Run migration**

```bash
cd helpdesk/backend && php artisan migrate --force --no-interaction
```

Expected: migration `2026_07_22_160000_bu_mailbox_intake` DONE; IT & MIS row has mailbox set.

---

### Task 2: Staff directory resolve by email

**Files:**
- Modify: `helpdesk/backend/app/Services/StaffDirectoryLookupService.php`
- Test: `helpdesk/backend/tests/Unit/StaffDirectoryLookupByEmailTest.php` (or Feature if cache hard to unit)

**Interfaces:**
- Produces: `resolveByWorkEmail(string $email): ?array{staff_id:int,name:string,work_email:string,division_id:?int,directorate_id:?int,duty_station_name:?string}`

- [ ] **Step 1: Implement `resolveByWorkEmail`** — normalize email to lowercase trim; scan cached staff rows; match `work_email` case-insensitive; return same shape as `resolveByStaffId` **plus** `staff_id`.

- [ ] **Step 2: Manual sanity** — if no cache in tests, return null; intake must still create ticket with From header only.

---

### Task 3: Exchange Graph mail reader

**Files:**
- Create: `helpdesk/backend/app/Services/ExchangeGraphMailReader.php`

**Interfaces:**
- Consumes: `EXCHANGE_*` via same token acquisition as send path (prefer HTTP client + client_credentials token request, or reuse `ExchangeOAuth` if it exposes access token for arbitrary Graph calls)
- Produces:
  - `listUnreadInbox(string $mailboxUpn, int $top = 25): array<GraphMessage>`
  - `ensureProcessedFolderId(string $mailboxUpn): string`
  - `markReadAndMoveToProcessed(string $mailboxUpn, string $messageId): void`
- `GraphMessage` shape (array): `id`, `internetMessageId`, `subject`, `bodyPreview`, `body` (`contentType`, `content`), `from` (`emailAddress` name/address), `receivedDateTime`

- [ ] **Step 1: Implement token + GET messages**

`GET https://graph.microsoft.com/v1.0/users/{mailbox}/mailFolders/inbox/messages?$filter=isRead eq false&$top={top}&$select=id,internetMessageId,subject,bodyPreview,body,from,receivedDateTime&$orderby=receivedDateTime asc`

- [ ] **Step 2: Ensure Processed folder**

List `mailFolders` (or childFolders of Inbox); if name equals `Processed` (case-insensitive) use id; else `POST .../mailFolders` with `displayName: Processed`.

- [ ] **Step 3: Mark read + move**

`PATCH .../messages/{id}` with `{"isRead":true}` then `POST .../messages/{id}/move` with `{"destinationId":"<processedFolderId>"}`.

- [ ] **Step 4: Error surface** — throw RuntimeException with Graph error body snippet; set readable `lastError` property for logging.

---

### Task 4: Intake service + poll job + schedule

**Files:**
- Create: `helpdesk/backend/app/Services/BusinessUnitMailboxIntakeService.php`
- Create: `helpdesk/backend/app/Jobs/PollBusinessUnitMailboxesJob.php`
- Modify: `helpdesk/backend/bootstrap/app.php`
- Create: `helpdesk/backend/tests/Feature/BusinessUnitMailboxIntakeTest.php`

**Interfaces:**
- Consumes: `ExchangeGraphMailReader`, `StaffDirectoryLookupService`, `TicketNumberGenerator`, `HtmlSanitizer` (or strip tags), `CategorizeTicketWithAi`
- Produces: `BusinessUnitMailboxIntakeService::pollUnit(HelpdeskBusinessUnit $unit): array{created:int,skipped:int,errors:int}`
- Job `handle()` loops enabled BUs and calls `pollUnit`

- [ ] **Step 1: Write failing feature test** with Graph reader mocked via binding:

```php
public function test_unread_email_creates_ticket_and_is_idempotent(): void
{
    Bus::fake([CategorizeTicketWithAi::class]);
    $this->seed(HelpdeskCategorySeeder::class);
    $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
    $unit->update(['email_intake_enabled' => true, 'support_mailbox' => 'helpdesk@africacdc.org']);

    $fake = new class extends \App\Services\ExchangeGraphMailReader {
        public int $moveCalls = 0;
        public function listUnreadInbox(string $mailboxUpn, int $top = 25): array {
            return [[
                'id' => 'msg-1',
                'internetMessageId' => '<a@b>',
                'subject' => 'VPN down',
                'bodyPreview' => 'Cannot connect',
                'body' => ['contentType' => 'Text', 'content' => 'Cannot connect to VPN'],
                'from' => ['emailAddress' => ['name' => 'Ada', 'address' => 'ada@example.org']],
                'receivedDateTime' => now()->toIso8601String(),
            ]];
        }
        public function ensureProcessedFolderId(string $mailboxUpn): string { return 'folder-processed'; }
        public function markReadAndMoveToProcessed(string $mailboxUpn, string $messageId): void { $this->moveCalls++; }
    };
    // Prefer interface or partial mock — bind concrete fake if reader is not final.
    $this->app->instance(ExchangeGraphMailReader::class, $fake);

    $svc = app(BusinessUnitMailboxIntakeService::class);
    $r1 = $svc->pollUnit($unit);
    $r2 = $svc->pollUnit($unit);

    $this->assertSame(1, $r1['created']);
    $this->assertSame(0, $r2['created']);
    $this->assertSame(1, HelpdeskTicket::query()->where('source', 'email')->count());
    Bus::assertDispatched(CategorizeTicketWithAi::class);
    $this->assertSame(1, $fake->moveCalls);
}
```

(Adjust fake if `ExchangeGraphMailReader` cannot be extended — use Mockery `mock()` instead.)

- [ ] **Step 2: Run test — expect FAIL** (class missing).

```bash
cd helpdesk/backend && php artisan test --filter=BusinessUnitMailboxIntakeTest
```

- [ ] **Step 3: Implement `BusinessUnitMailboxIntakeService::pollUnit`**

For each message:
1. Skip if `HelpdeskEmailMessage` exists for `graph_message_id`.
2. `Cache::lock('email-intake:'.$id, 30)->block(5)`.
3. Resolve requester via `resolveByWorkEmail`.
4. Create ticket: `source=email`, `business_unit_id`, `category_id=null`, subject from email (max 255), description HTML-escaped/sanitized body, requester fields, `status=open`, generate ticket_number via existing generator.
5. Insert `HelpdeskEmailMessage` with `ticket_id`, `processed_at=now()`.
6. `CategorizeTicketWithAi::dispatch($ticket->id, $dutyStation)`.
7. `markReadAndMoveToProcessed` — on failure log warning (idempotency protects duplicates).

Skip BUs without mailbox or intake disabled.

- [ ] **Step 4: Implement job + schedule**

```php
// PollBusinessUnitMailboxesJob — ShouldQueue, queue default or 'helpdesk'
public function handle(BusinessUnitMailboxIntakeService $intake): void
{
    $units = HelpdeskBusinessUnit::query()
        ->where('email_intake_enabled', true)
        ->whereNotNull('support_mailbox')
        ->where('support_mailbox', '!=', '')
        ->where('is_active', true)
        ->get();
    foreach ($units as $unit) {
        try {
            $intake->pollUnit($unit);
        } catch (\Throwable $e) {
            Log::error('helpdesk.email_intake.bu_failed', [
                'business_unit_id' => $unit->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
```

In `bootstrap/app.php` schedule:

```php
$schedule->job(new \App\Jobs\PollBusinessUnitMailboxesJob)
    ->everyMinute()
    ->withoutOverlapping(5);
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
cd helpdesk/backend && php artisan test --filter=BusinessUnitMailboxIntakeTest
```

---

### Task 5: Settings UI — lean table + BU modal

**Files:**
- Modify: `helpdesk/frontend/src/components/settings/CategoriesManagementPanel.vue`
- Mirror to `helpdesk/client/...` if that tree is kept in sync
- Rebuild: `helpdesk/frontend` `npm run build`

**Interfaces:**
- Consumes admin BU API fields `support_mailbox`, `email_intake_enabled`
- Produces modal create/update payloads including those fields plus existing anonymous / allow asset flags

- [ ] **Step 1: Slim `buHeaders`** to: Name, Categories, Active, Actions (remove inline Description/Slug/Order/Anonymous/Allow Asset inputs).

- [ ] **Step 2: Add modal state** — `buModalOpen`, `buEditing: BusinessUnitOption | null`; fields in reactive form include mailbox + `email_intake_enabled`.

- [ ] **Step 3: Table actions** — Edit opens modal with row values; Add opens empty modal; Save calls POST or PUT then reload.

- [ ] **Step 4: Modal fields** — Name*, Slug, Description, Sort order, Active, Allow anonymous, Allow Asset, Support mailbox (email input, placeholder `helpdesk@africacdc.org`), Email intake enabled (checkbox).

- [ ] **Step 5: Build frontend**

```bash
cd helpdesk/frontend && npm run build
```

Expected: success; CategoriesManagementPanel chunk updated.

---

### Task 6: Docs

**Files:**
- Modify: `helpdesk/documentation/ADMIN_GUIDE.md`
- Modify: `helpdesk/documentation/INTEGRATION.md`
- Modify: `helpdesk/documentation/SYSTEMD.md` (mention every-minute schedule + Graph Mail.ReadWrite)

- [ ] **Step 1: Document** Azure application permission for mailbox read/move, BU modal settings, Processed folder behavior, scheduler every minute, IT & MIS default mailbox.

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| BU `support_mailbox` + `email_intake_enabled` | 1 |
| IT & MIS seed mailbox | 1 |
| Graph list unread / mark / move Processed | 3 |
| Create ticket + categorize dispatch | 4 |
| Idempotency table | 1 + 4 |
| Admin least-load via existing job | 4 (reuse CategorizeTicketWithAi) |
| Schedule every 1 minute | 4 |
| Modal BU editor | 5 |
| Docs | 6 |

## Self-review notes

- No webhook/IMAP tasks (out of scope).
- Attachments deferred per spec.
- `resolveByWorkEmail` staff_id included for ticket `requester_staff_id`.
- Commit steps omitted from agent execution unless user requests commits.
