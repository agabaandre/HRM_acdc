# Migration Backup & Restore Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin-only Backup/Restore UI that exports a portable JSON migration package (users, clients, providers, links, branding) and imports it with upsert rules on a new server.

**Architecture:** `MigrationExportService` builds the JSON (decrypting provider configs); `MigrationImportService` upserts by natural keys inside a DB transaction (re-encrypting provider configs with the destination `APP_KEY`). `MigrationController` exposes admin-only export/import endpoints; Vue page at `/backup` downloads and uploads the package.

**Tech Stack:** Laravel 12, Sanctum admin auth, Vue 3 + Vuetify 3, PHPUnit feature tests via `docker exec email-server-app php artisan test`.

## Global Constraints

- Schema version for packages: `1` only (reject others).
- Match keys: provider `slug`, user `email`, client `slug`.
- Provider `config` exported decrypted; imported via `EmailProvider::setConfigSafely()`.
- Client secrets: export/import `api_key_hash` + `api_key_prefix` only (never regenerate on import).
- Password: export raw hash; on import set hash without double-hashing (`Hash::isHashed` / raw update if needed).
- Excluded: email logs, audit logs, blocked lists, reset tokens, Sanctum tokens.
- Admin-only routes behind `EnsureUserIsAdmin`.
- Throttle: export `20,1` (per minute hour window as `20,60`), import `5,60`.
- Max upload: validate file size ≤ 20MB; JSON only.
- Audit: `migration_exported` / `migration_imported` without secret payloads.

## File map

| File | Responsibility |
|---|---|
| `backend/app/Services/MigrationExportService.php` | Build package array + filename |
| `backend/app/Services/MigrationImportService.php` | Validate + upsert; return summary |
| `backend/app/Http/Controllers/Api/V1/Admin/MigrationController.php` | export / import HTTP |
| `backend/routes/api.php` | Register routes |
| `backend/tests/Feature/MigrationBackupRestoreTest.php` | Feature coverage |
| `frontend/src/views/BackupRestoreView.vue` | UI |
| `frontend/src/router/index.ts` | `/backup` route |
| `frontend/src/config/sidebar.ts` | Nav item |

---

### Task 1: Export service + export endpoint (TDD)

**Files:**
- Create: `backend/app/Services/MigrationExportService.php`
- Create: `backend/app/Http/Controllers/Api/V1/Admin/MigrationController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/MigrationBackupRestoreTest.php`

**Interfaces:**
- Produces: `MigrationExportService::build(): array`, `MigrationExportService::filename(): string`
- Package keys: `meta`, `email_providers`, `users`, `external_integrations`, `user_client_links`, `branding`

- [ ] **Step 1: Write failing export test**

```php
<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Enums\UserApprovalStatus;
use App\Models\BrandingSetting;
use App\Models\EmailProvider;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrationBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        return $admin->createToken('admin-panel')->plainTextToken;
    }

    public function test_admin_can_export_migration_package(): void
    {
        Storage::fake('public');

        $provider = EmailProvider::query()->create([
            'name' => 'SMTP',
            'slug' => 'smtp-main',
            'driver' => EmailDriver::Smtp,
            'config' => ['host' => 'smtp.example.com', 'password' => 'secret-pass'],
            'from_address' => 'noreply@example.com',
            'is_default' => true,
            'is_active' => true,
        ]);

        $partner = User::factory()->create([
            'email' => 'partner@example.com',
            'is_admin' => false,
            'approval_status' => UserApprovalStatus::Approved,
        ]);

        $client = ExternalIntegration::query()->create([
            'name' => 'Partner App',
            'slug' => 'partner-app',
            'api_key_hash' => ExternalIntegration::hashClientSecret('client-secret-value-99'),
            'api_key_prefix' => 'cli…',
            'email_provider_id' => $provider->id,
            'is_active' => true,
        ]);
        $partner->externalIntegrations()->attach($client->id);

        BrandingSetting::current()->update([
            'app_name' => 'Migrated App',
            'primary_color' => '#112233',
        ]);

        $res = $this->withToken($this->adminToken())
            ->get('/api/v1/admin/migration/export')
            ->assertOk();

        $this->assertStringContainsString('attachment', (string) $res->headers->get('Content-Disposition'));
        $json = $res->json();
        $this->assertSame(1, $json['meta']['schema_version']);
        $this->assertSame('secret-pass', $json['email_providers'][0]['config']['password']);
        $this->assertSame('partner@example.com', collect($json['users'])->firstWhere('email', 'partner@example.com')['email']);
        $this->assertSame('partner-app', $json['external_integrations'][0]['slug']);
        $this->assertSame('smtp-main', $json['external_integrations'][0]['email_provider_slug']);
        $this->assertTrue(collect($json['user_client_links'])->contains(
            fn ($l) => $l['user_email'] === 'partner@example.com' && $l['client_slug'] === 'partner-app'
        ));
        $this->assertSame('Migrated App', $json['branding']['app_name']);
    }

    public function test_non_admin_cannot_export(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
        $this->withToken($user->createToken('admin-panel')->plainTextToken)
            ->getJson('/api/v1/admin/migration/export')
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test — expect fail (route missing)**

```bash
docker exec email-server-app php artisan test --filter=MigrationBackupRestoreTest
```

- [ ] **Step 3: Implement export service**

`MigrationExportService`:

```php
public function filename(): string
{
    return 'email-server-migration-'.now()->format('Ymd-His').'.json';
}

/** @return array<string, mixed> */
public function build(): array
{
    // meta.schema_version = 1
    // providers: map with safeConfig(), driver value, no id
    // users: include password via getRawOriginal('password');
    //        two_factor_totp_secret via attribute (decrypted by cast);
    //        approved_by_email / created_by_email resolved from User::find
    // clients: email_provider_slug from relation; api_key_hash + prefix
    // links: from pivot with user.email + integration.slug
    // branding: scalars + optional base64 assets from Storage::disk('public')
}
```

- [ ] **Step 4: Implement controller export + route**

```php
// MigrationController::export
$package = $exporter->build();
$audit->log('Migration package exported', [
    'event_type' => 'migration_exported',
    'user' => $request->user(),
    'new_values' => [
        'providers' => count($package['email_providers']),
        'users' => count($package['users']),
        'clients' => count($package['external_integrations']),
    ],
]);
return response()->streamDownload(
    fn () => print(json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
    $exporter->filename(),
    ['Content-Type' => 'application/json']
);
```

In `routes/api.php` inside `EnsureUserIsAdmin` group:

```php
Route::get('/migration/export', [MigrationController::class, 'export'])
    ->middleware('throttle:20,60');
```

- [ ] **Step 5: Run tests — expect pass**

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/MigrationExportService.php \
  backend/app/Http/Controllers/Api/V1/Admin/MigrationController.php \
  backend/routes/api.php \
  backend/tests/Feature/MigrationBackupRestoreTest.php
git commit -m "Add migration package export for users, clients, and providers."
```

---

### Task 2: Import service + import endpoint (TDD)

**Files:**
- Create: `backend/app/Services/MigrationImportService.php`
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/MigrationController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/tests/Feature/MigrationBackupRestoreTest.php`

**Interfaces:**
- Consumes: package array from Task 1
- Produces: `MigrationImportService::import(array $package): array` summary matching spec response `data`

- [ ] **Step 1: Write failing import tests**

```php
public function test_import_creates_missing_users_and_updates_existing_clients(): void
{
    // Destination already has provider slug smtp-main (different password)
    // and client partner-app (old hash)
    // Package has new user + updated client hash + new provider password
    // POST multipart file to /api/v1/admin/migration/import
    // Assert: user created; client hash updated; provider password decrypts to package value
    // Assert JWT still works with the package's known client secret
}

public function test_import_rejects_invalid_schema_version(): void
{
    // schema_version 99 -> 422
}
```

Use `Illuminate\Http\UploadedFile::fake()->createWithContent('migration.json', json_encode($package))`.

- [ ] **Step 2: Run tests — expect fail**

- [ ] **Step 3: Implement `MigrationImportService::import`**

Order inside `DB::transaction`:
1. Validate `meta.schema_version === 1` (throw `InvalidArgumentException` → 422).
2. Upsert providers by slug (`setConfigSafely`); track default (clear other defaults when setting one).
3. Upsert users by email (password via hashed cast / `Hash::isHashed`); store map email→id; resolve `created_by`/`approved_by` in a second pass.
4. Upsert clients by slug; resolve `email_provider_id` from slug (warn + skip provider link if missing).
5. Group `user_client_links` by user_email; for each user in that set, `sync` client ids (warn on missing).
6. Branding: update scalars; write base64 assets to `public` disk paths under `branding/`.
7. Return summary counts + warnings.

- [ ] **Step 4: Wire `MigrationController::import`**

```php
public function import(Request $request, MigrationImportService $importer, AuditLogService $audit): JsonResponse
{
    $request->validate([
        'file' => ['required', 'file', 'max:20480', 'mimetypes:application/json,text/plain'],
    ]);
    $raw = file_get_contents($request->file('file')->getRealPath());
    $package = json_decode($raw ?: '', true);
    if (! is_array($package)) {
        return response()->json(['message' => 'Invalid migration JSON.'], 422);
    }
    try {
        $summary = $importer->import($package);
    } catch (\InvalidArgumentException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
    $audit->log('Migration package imported', [
        'event_type' => 'migration_imported',
        'user' => $request->user(),
        'new_values' => collect($summary)->except('warnings')->all(),
    ]);
    return response()->json(['message' => 'Migration imported.', 'data' => $summary]);
}
```

Route:

```php
Route::post('/migration/import', [MigrationController::class, 'import'])
    ->middleware('throttle:5,60');
```

- [ ] **Step 5: Run full `MigrationBackupRestoreTest` — expect pass**

- [ ] **Step 6: Commit**

```bash
git commit -m "Add migration package import with upsert for users and clients."
```

---

### Task 3: Admin Backup / Restore UI

**Files:**
- Create: `frontend/src/views/BackupRestoreView.vue`
- Modify: `frontend/src/router/index.ts`
- Modify: `frontend/src/config/sidebar.ts`

**Interfaces:**
- Consumes: `GET /admin/migration/export` (blob download), `POST /admin/migration/import` (multipart)

- [ ] **Step 1: Add route + sidebar**

```ts
// router children
{ path: 'backup', name: 'backup', component: () => import('@/views/BackupRestoreView.vue'), meta: { requiresAdmin: true } }

// sidebar under Administration
{ title: 'Backup / Restore', icon: 'mdi-database-export-outline', to: { name: 'backup' }, adminOnly: true },
```

- [ ] **Step 2: Build `BackupRestoreView.vue`**

- PageHeader: “Backup / Restore”
- Alert: package contains secrets — treat like a password dump
- Button Download → `api.get('/admin/migration/export', { responseType: 'blob' })` then save via object URL with filename from `Content-Disposition` or default
- `v-file-input` accept `.json` + Restore button → confirm → `FormData` with `file` → show summary counts/warnings
- Use existing `ParentCard`, `api`, `apiErrorMessage` patterns

- [ ] **Step 3: Manual smoke** — open `/backup` as admin, export, import on same instance (counts show updates)

- [ ] **Step 4: Commit**

```bash
git commit -m "Add admin Backup/Restore page for migration packages."
```

---

### Task 4: Spec status + final verification

**Files:**
- Modify: `docs/superpowers/specs/2026-09-19-migration-backup-restore-design.md` (Status → Implemented)

- [ ] **Step 1: Run**

```bash
docker exec email-server-app php artisan test --filter=MigrationBackupRestoreTest
cd frontend && npm run build
```

- [ ] **Step 2: Mark spec Implemented; commit**

```bash
git commit -m "Mark migration backup/restore design as implemented."
```

---

## Spec coverage check

| Spec requirement | Task |
|---|---|
| Export JSON with decrypted providers | 1 |
| Users / clients / links / branding in package | 1 |
| Import upsert users (create missing) + update clients | 2 |
| Re-encrypt providers on import | 2 |
| Client hash preserved (JWT still works) | 2 |
| Admin UI download/upload | 3 |
| Throttle + audit + schema_version | 1–2 |
| Exclude logs | 1 (not exported) |
