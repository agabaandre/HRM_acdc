# Self-registration & scoped clients Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let partners self-register (pending until admin approval), then manage only their own integration clients (created inactive) and see only those clients’ email logs, with an improved login/register UX.

**Architecture:** Extend `users` with approval + profile fields; gate login on `approval_status`; open `external-integrations` CRUD to approved non-admins via policy + pivot ownership; force `is_active=false` on account-created clients; keep admin activate/deactivate. Frontend: public `/register`, refreshed login with register CTA, scoped Integrations nav for non-admins, Users approval actions.

**Tech Stack:** Laravel 12, Sanctum, Vue 3 + Vuetify, PHPUnit feature tests (Docker `docker compose run --rm --no-deps --entrypoint bash app -lc '…'`).

## Global Constraints

- Self-register never sets `is_admin=true`
- Pending/rejected users never receive a Sanctum panel token
- Non-admin-created clients always start `is_active=false`
- Non-admins see only pivot-linked clients and those clients’ email logs
- Captcha + throttle on public register (same pattern as login)
- Tests run inside Docker app container; set `config(['services.captcha.enabled' => false])` in tests that hit captcha-gated endpoints unless seeding captcha
- Spec: `docs/superpowers/specs/2026-09-19-self-registration-scoped-clients-design.md`

## File map

| File | Responsibility |
|---|---|
| `backend/database/migrations/2026_09_19_200000_add_approval_fields_to_users_table.php` | Schema |
| `backend/app/Enums/UserApprovalStatus.php` | `pending` / `approved` / `rejected` |
| `backend/app/Models/User.php` | Fields, helpers, casts |
| `backend/app/Http/Requests/Api/V1/Admin/RegisterAccountRequest.php` | Public register validation |
| `backend/app/Http/Controllers/Api/V1/Admin/AuthController.php` | `register()`, login gate |
| `backend/app/Policies/ExternalIntegrationPolicy.php` | Admin vs owner |
| `backend/app/Http/Controllers/Api/V1/Admin/ExternalIntegrationController.php` | Scope list/store/update/destroy |
| `backend/app/Http/Controllers/Api/V1/Admin/UserController.php` | Approve/reject + list filter |
| `backend/app/Http/Controllers/Api/V1/Admin/DashboardController.php` | Scope stats for non-admins |
| `backend/routes/api.php` | Register route; move integrations (+ providers index) out of admin-only |
| `backend/tests/Feature/AccountRegistrationTest.php` | Register + login gate + approve |
| `backend/tests/Feature/ScopedExternalIntegrationTest.php` | Owner CRUD + inactive JWT |
| `frontend/src/views/auth/RegisterView.vue` + `RegisterForm.vue` | Registration UI |
| `frontend/src/components/auth/LoginForm.vue` + auth layout | Register CTA + polish |
| `frontend/src/router/index.ts`, `sidebar.ts` | Routes / nav |
| `frontend/src/views/users/*`, `IntegrationsView.vue`, `IntegrationFormView.vue` | Approval UI + scoped clients |

---

### Task 1: Migration + User approval model

**Files:**
- Create: `backend/database/migrations/2026_09_19_200000_add_approval_fields_to_users_table.php`
- Create: `backend/app/Enums/UserApprovalStatus.php`
- Modify: `backend/app/Models/User.php`
- Modify: `backend/database/factories/UserFactory.php`
- Test: `backend/tests/Feature/AccountRegistrationTest.php` (skeleton assertions on model helpers first, expand in Task 2)

**Interfaces:**
- Produces: `UserApprovalStatus` enum; `User::isApproved(): bool`, `User::isPendingApproval(): bool`; fillable/casts for new columns

- [ ] **Step 1: Add enum**

```php
<?php
namespace App\Enums;

enum UserApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 2: Migration**

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('approval_status', 20)->default('approved')->after('is_active');
    $table->string('phone')->nullable()->after('email');
    $table->string('organisation')->nullable()->after('phone');
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('rejected_at')->nullable();
    $table->string('rejection_reason')->nullable();
});
// Backfill: existing rows already default approved
```

- [ ] **Step 3: Update `User` fillable/casts + helpers**

Add to `#[Fillable]`: `approval_status`, `phone`, `organisation`, `approved_at`, `approved_by`, `rejected_at`, `rejection_reason`.

Cast `approval_status` to `UserApprovalStatus::class`, timestamps to `datetime`.

```php
public function isApproved(): bool
{
    return $this->approval_status === UserApprovalStatus::Approved;
}

public function isPendingApproval(): bool
{
    return $this->approval_status === UserApprovalStatus::Pending;
}
```

- [ ] **Step 4: Factory defaults `approval_status => approved`, `is_active => true`**

- [ ] **Step 5: Run migrate in Docker**

```bash
cd docker && docker compose exec -T app php artisan migrate --force
```

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_09_19_200000_add_approval_fields_to_users_table.php \
  backend/app/Enums/UserApprovalStatus.php backend/app/Models/User.php backend/database/factories/UserFactory.php
git commit -m "Add user approval status and registration profile fields."
```

---

### Task 2: Public register + login gate

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/Admin/RegisterAccountRequest.php`
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/AuthController.php`
- Modify: `backend/routes/api.php` (add `POST /auth/register` next to login)
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/AuthController.php` `me()` / login payload to include `approval_status`, `phone`, `organisation`
- Test: `backend/tests/Feature/AccountRegistrationTest.php`

**Interfaces:**
- Consumes: `UserApprovalStatus`, User helpers
- Produces: `AuthController::register(RegisterAccountRequest): JsonResponse`; login rejects pending/rejected

- [ ] **Step 1: Write failing tests**

```php
public function test_visitor_can_register_pending_account(): void
{
    config(['services.captcha.enabled' => false]);
    $this->postJson('/api/v1/admin/auth/register', [
        'name' => 'Partner User',
        'email' => 'partner@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'phone' => '+256700000000',
        'organisation' => 'Partner Org',
    ])->assertCreated()
        ->assertJsonMissingPath('token');

    $this->assertDatabaseHas('users', [
        'email' => 'partner@example.com',
        'approval_status' => 'pending',
        'is_admin' => 0,
        'is_active' => 0,
        'organisation' => 'Partner Org',
    ]);
}

public function test_pending_user_cannot_login(): void
{
    config(['services.captcha.enabled' => false]);
    User::factory()->create([
        'email' => 'pending@example.com',
        'password' => 'SecurePass123!',
        'approval_status' => UserApprovalStatus::Pending,
        'is_active' => false,
        'is_admin' => false,
    ]);
    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'pending@example.com',
        'password' => 'SecurePass123!',
    ])->assertStatus(403)
        ->assertJsonFragment(['message' => 'Your account is awaiting administrator approval.']);
}

public function test_rejected_user_cannot_login(): void { /* similar, status rejected, message about not approved */ }
```

- [ ] **Step 2: Run tests — expect FAIL (route missing)**

```bash
docker compose run --rm --no-deps --entrypoint bash app -lc 'php artisan test --filter=AccountRegistrationTest'
```

- [ ] **Step 3: Implement `RegisterAccountRequest`**

Rules: `name` required string; `email` required unique; `password` confirmed min 10; `phone` required string max 40; `organisation` required string max 255; captcha rules like `LoginRequest`.

- [ ] **Step 4: Implement `AuthController::register`**

```php
User::query()->create([
    'name' => $data['name'],
    'email' => $data['email'],
    'password' => $data['password'],
    'phone' => $data['phone'],
    'organisation' => $data['organisation'],
    'is_admin' => false,
    'is_active' => false,
    'approval_status' => UserApprovalStatus::Pending,
    'totp_required' => false, // set true on approve
]);
// AuditLogService action user_registered if easy
return response()->json(['message' => 'Registration received. An administrator must approve your account before you can sign in.'], 201);
```

- [ ] **Step 5: Gate `login()` before inactive/2FA checks**

```php
if ($user->approval_status === UserApprovalStatus::Pending) {
    return response()->json(['message' => 'Your account is awaiting administrator approval.'], 403);
}
if ($user->approval_status === UserApprovalStatus::Rejected) {
    return response()->json(['message' => 'Your account registration was not approved.'], 403);
}
```

- [ ] **Step 6: Route**

```php
Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');
```

- [ ] **Step 7: Tests pass → commit**

```bash
git commit -m "Add public account registration and approval login gate."
```

---

### Task 3: Admin approve / reject users

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/UserController.php`
- Modify: `backend/app/Http/Requests/Api/V1/Admin/StoreUserRequest.php` (ensure admin-created users stay `approved`)
- Modify: `backend/routes/api.php`
- Test: extend `AccountRegistrationTest.php`

**Interfaces:**
- Produces: `UserController::approve(User): JsonResponse`, `reject(Request, User): JsonResponse`

- [ ] **Step 1: Failing tests**

```php
public function test_admin_can_approve_pending_user(): void
{
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    $pending = User::factory()->create([
        'approval_status' => UserApprovalStatus::Pending,
        'is_active' => false,
        'is_admin' => false,
    ]);
    $token = $admin->createToken('admin-panel')->plainTextToken;
    $this->withToken($token)
        ->postJson("/api/v1/admin/users/{$pending->id}/approve")
        ->assertOk();
    $pending->refresh();
    $this->assertTrue($pending->isApproved());
    $this->assertTrue($pending->is_active);
    $this->assertTrue($pending->totp_required);
}

public function test_admin_can_reject_pending_user(): void { /* rejected_at set, is_active false */ }

public function test_approved_user_can_login(): void { /* after approve, login 200 or 2fa path */ }
```

- [ ] **Step 2: Implement approve/reject**

Approve:
```php
$user->update([
    'approval_status' => UserApprovalStatus::Approved,
    'is_active' => true,
    'totp_required' => true,
    'approved_at' => now(),
    'approved_by' => $request->user()->id,
    'rejected_at' => null,
    'rejection_reason' => null,
]);
```

Reject:
```php
$user->update([
    'approval_status' => UserApprovalStatus::Rejected,
    'is_active' => false,
    'rejected_at' => now(),
    'rejection_reason' => $request->input('reason'),
]);
$user->tokens()->delete();
```

- [ ] **Step 3: `index` accepts `?approval_status=`; `transform` includes new fields**

- [ ] **Step 4: Admin `store` sets `approval_status => Approved`**

- [ ] **Step 5: Routes inside admin middleware**

```php
Route::post('/users/{user}/approve', [UserController::class, 'approve']);
Route::post('/users/{user}/reject', [UserController::class, 'reject']);
```

- [ ] **Step 6: Tests pass → commit**

```bash
git commit -m "Allow admins to approve or reject registered accounts."
```

---

### Task 4: Scoped external integrations policy + controller

**Files:**
- Create: `backend/app/Policies/ExternalIntegrationPolicy.php`
- Register policy in `backend/app/Providers/AppServiceProvider.php` (or auto-discovery)
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/ExternalIntegrationController.php`
- Modify: `backend/app/Http/Requests/Api/V1/Admin/StoreExternalIntegrationRequest.php` + `UpdateExternalIntegrationRequest.php` (`authorize` → `$this->user() !== null` or policy)
- Modify: `backend/routes/api.php` — move `apiResource('external-integrations')` and `GET email-providers` (index + show only) to authenticated (non-admin) group; keep provider mutate admin-only
- Test: `backend/tests/Feature/ScopedExternalIntegrationTest.php`

**Interfaces:**
- Consumes: `User::allowedExternalIntegrationIds()`, `externalIntegrations()` pivot
- Produces: scoped index/store; non-admin store forces inactive + pivot attach

- [ ] **Step 1: Failing tests**

```php
public function test_approved_user_creates_inactive_owned_client(): void
{
    config(['services.captcha.enabled' => false]);
    $user = User::factory()->create([
        'is_admin' => false,
        'is_active' => true,
        'approval_status' => UserApprovalStatus::Approved,
        'totp_required' => false,
        'two_factor_totp_enabled' => true, // bypass setup middleware if needed
    ]);
    // If EnsureTotpSetupComplete blocks: set totp_required false and two_factor_totp_enabled true
    $token = $user->createToken('admin-panel')->plainTextToken;
    $res = $this->withToken($token)->postJson('/api/v1/admin/external-integrations', [
        'name' => 'My App',
        'generate_secret' => true,
    ])->assertCreated();
    $id = $res->json('data.id');
    $this->assertFalse((bool) $res->json('data.is_active'));
    $this->assertTrue($user->externalIntegrations()->whereKey($id)->exists());
}

public function test_user_cannot_see_others_clients(): void { /* create two users/clients; assert index count */ }

public function test_inactive_client_cannot_get_jwt(): void
{
    // create inactive integration with known secret; POST /integrations/auth/token → 401/403
}
```

- [ ] **Step 2: Policy**

```php
public function viewAny(User $user): bool { return $user->is_active && $user->isApproved(); }
public function view(User $user, ExternalIntegration $i): bool {
    return $user->is_admin || $user->canAccessExternalIntegration($i->id);
}
public function create(User $user): bool { return $user->isApproved(); }
public function update(User $user, ExternalIntegration $i): bool { return $this->view($user, $i); }
public function delete(User $user, ExternalIntegration $i): bool {
    return $user->is_admin || $user->canAccessExternalIntegration($i->id);
}
```

- [ ] **Step 3: Controller changes**

`index`: if `!$user->is_admin` then `whereIn('id', $user->allowedExternalIntegrationIds() ?: [0])`.

`store`: `$this->authorize('create', ExternalIntegration::class);`  
If non-admin: `$data['is_active'] = false;` after create `$request->user()->externalIntegrations()->syncWithoutDetaching([$integration->id]);`

`show`/`update`/`destroy`: `$this->authorize(...)`.

Non-admin must not flip `is_active` to true on update (ignore or 403 if they try).

- [ ] **Step 4: Routes**

Inside `auth:sanctum` group but **outside** `EnsureUserIsAdmin`:

```php
Route::get('/email-providers', [EmailProviderController::class, 'index']); // read-only for form
Route::apiResource('external-integrations', ExternalIntegrationController::class);
```

Remove duplicate from admin-only group. Keep `email-providers` write + drivers/test/set-default admin-only (drivers needed for forms — either expose `GET drivers` to all authenticated or hardcode smtp/exchange options only for non-admins; **expose `GET /email-providers/drivers` to authenticated users** as read-only).

- [ ] **Step 5: Tests pass → commit**

```bash
git commit -m "Scope integration clients to approved account owners."
```

---

### Task 5: Dashboard + email logs scoping verification

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/DashboardController.php`
- Verify: `EmailLogController` already scopes — add test if missing
- Test: `backend/tests/Feature/UserAppCredentialAccessTest.php` or new assertions in `ScopedExternalIntegrationTest`

- [ ] **Step 1: Test non-admin dashboard/logs only show own client traffic**

- [ ] **Step 2: Scope `DashboardController` queries with `allowedExternalIntegrationIds()` when not null

- [ ] **Step 3: Commit**

```bash
git commit -m "Scope dashboard stats to assigned integration clients."
```

---

### Task 6: Frontend register + improved login

**Files:**
- Create: `frontend/src/views/auth/RegisterView.vue` (or next to existing login views)
- Create: `frontend/src/components/auth/RegisterForm.vue`
- Modify: `frontend/src/components/auth/LoginForm.vue`
- Modify: login parent view / styles as needed for polish
- Modify: `frontend/src/router/index.ts` — public `register` route
- Modify: `frontend/src/stores/auth.ts` — optional `register()` helper
- Modify: `frontend/src/lib/api.ts` if needed

- [ ] **Step 1: Add route `{ path: 'register', name: 'register', component: RegisterView, meta: { guest: true } }`**

- [ ] **Step 2: RegisterForm fields + captcha + POST `/admin/auth/register` → success alert, link to login**

- [ ] **Step 3: LoginForm — add “Create account” button/link; improve spacing/title/subtitle; keep captcha/forgot password**

- [ ] **Step 4: Manual smoke in browser (or skip if no e2e) → commit**

```bash
git commit -m "Add register page and improve login with account CTA."
```

---

### Task 7: Frontend users approval UI + scoped integrations nav

**Files:**
- Modify: `frontend/src/config/sidebar.ts` — remove `adminOnly` from Integrations (title may stay “Integrations” or “My clients”)
- Modify: `frontend/src/router/index.ts` — integrations routes: allow non-admin (`requiresAdmin` false)
- Modify: `frontend/src/views/users/UsersListView.vue` — approval filter + Approve/Reject
- Modify: `frontend/src/views/users/UserFormView.vue` — show phone/org read-only if present
- Modify: `frontend/src/views/IntegrationsView.vue` — inactive badge; note for non-admins
- Modify: `frontend/src/views/IntegrationFormView.vue` — non-admin: no force-active; hide admin-only bits if any

- [ ] **Step 1: Sidebar — Integrations visible to all authenticated users**

- [ ] **Step 2: Users list — chips Pending/Approved/Rejected; actions calling approve/reject APIs**

- [ ] **Step 3: Integrations list — show `is_active`; for `!auth.isAdmin` show helper text “New clients stay inactive until an administrator activates them.”**

- [ ] **Step 4: Commit**

```bash
git commit -m "Expose scoped clients UI and admin approval actions."
```

---

### Task 8: End-to-end feature test pass + push

- [ ] **Step 1: Run full related suite**

```bash
docker compose run --rm --no-deps --entrypoint bash app -lc \
  'php artisan test --filter="AccountRegistrationTest|ScopedExternalIntegrationTest|UserAppCredentialAccessTest|MandatoryTotpEnrollmentTest"'
```

- [ ] **Step 2: Fix failures**

- [ ] **Step 3: Update design spec status to Implemented**

- [ ] **Step 4: Push branch/commits**

---

## Spec coverage checklist

| Spec requirement | Task |
|---|---|
| Public register + phone/org | 2, 6 |
| No login until approved | 2 |
| Admin approve/reject | 3, 7 |
| Own clients only + provider/IPs | 4, 7 |
| Clients start inactive; admin activates | 4 |
| Logs scoped | 5 |
| Improved login + register CTA | 6 |
| Feature tests | 2–5, 8 |

## Placeholder scan

None intentional. Nice-to-have admin/user emails deferred unless trivial in Task 2/3.

---

## Execution

Plan complete and saved to `docs/superpowers/plans/2026-09-19-self-registration-scoped-clients.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — implement tasks in this session with checkpoints  

Which approach?
