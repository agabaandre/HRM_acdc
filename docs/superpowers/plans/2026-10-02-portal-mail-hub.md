# Portal Mail Hub Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Staff Portal owns email providers; CBP modules dispatch via Share by default and may fetch encrypted config for local send fallback, preferring healthy HTTP (notifications.africacdc.org) then UI default then Exchange/SMTP.

**Architecture:** Extend `EmailProvidersService::resolveForSend` with the priority order. Add Share endpoints `POST /share/mail/send` and `GET /share/mail/active-config` (AES-256-GCM). Add `shared/StaffPortalMailClient.php` used by modules with `STAFF_MAIL_DISPATCH=auto|portal|local`. Seed/UI default driver becomes `http`.

**Tech Stack:** Laravel 11 (staff-portal + modules), PHPUnit Feature/Unit tests, existing Share `AuthenticateShareApi`, existing `PortalMailer` / `HttpNotificationsMailClient`.

**Spec:** `docs/superpowers/specs/2026-10-02-portal-mail-hub-design.md`

## Global Constraints

- Do not remove Exchange/SMTP or module-local mailers.
- Never return plaintext secrets from Share JSON; encrypt config with `STAFF_MAIL_CONFIG_KEY` (fallback `APP_KEY`).
- UI-edited provider rows must not be overwritten by env seed.
- HTTP healthy → UI Default → Exchange → SMTP (spec § Resolution order).
- Auth for Share mail routes: existing `AuthenticateShareApi` only.
- Prefer small diffs; reuse `PortalMailer` for dispatch.
- Commit after each task; do not commit `.env` or oauth tokens.

## File map

| Path | Responsibility |
|------|----------------|
| `modules/staff-portal/backend/Modules/Settings/app/Services/EmailProvidersService.php` | Resolution order + HTTP health |
| `modules/staff-portal/backend/app/Services/MailConfigCrypto.php` | Encrypt/decrypt active config |
| `modules/staff-portal/backend/Modules/Share/app/Http/Controllers/ShareMailController.php` | `send` + `activeConfig` |
| `modules/staff-portal/backend/Modules/Share/routes/share.php` | Wire routes |
| `modules/staff-portal/backend/tests/Feature/ShareMailApiTest.php` | Feature tests |
| `modules/staff-portal/backend/tests/Unit/EmailProvidersResolveForSendTest.php` | Resolution unit tests |
| `modules/staff-portal/backend/tests/Unit/MailConfigCryptoTest.php` | Crypto unit tests |
| `modules/staff-portal/frontend/src/pages/settings/EmailServersPage.vue` | Default create driver `http` |
| `shared/StaffPortalMailClient.php` | Module hybrid client |
| `shared/load-staff-root-env.php` | Expose `STAFF_MAIL_CONFIG_KEY`, `STAFF_MAIL_DISPATCH` |
| `modules/helpdesk/backend/app/Services/StaffPortalMailClient.php` | Thin wrapper or require shared |
| Helpdesk / APM / Finance send call sites | Switch to hybrid client (phased) |
| `docs/SETUP.md` | Document mail hub + HTTP primary |

---

### Task 1: Resolution order (HTTP → default → Exchange → SMTP)

**Files:**
- Modify: `modules/staff-portal/backend/Modules/Settings/app/Services/EmailProvidersService.php` (`resolveForSend`, add helpers)
- Create: `modules/staff-portal/backend/tests/Unit/EmailProvidersResolveForSendTest.php`

**Interfaces:**
- Produces: `resolveForSend(?PortalEmailProvider $provider = null): array{provider, driver, config, from_address, from_name}` with new priority when `$provider === null`
- Produces: `httpProviderHealthy(PortalEmailProvider $p): bool`

- [ ] **Step 1: Write the failing unit test**

```php
<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Settings\Models\PortalEmailProvider;
use Modules\Settings\Services\EmailProvidersService;
use Tests\TestCase;

class EmailProvidersResolveForSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_active_http_over_default_exchange(): void
    {
        PortalEmailProvider::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Exchange Default',
            'slug' => 'exchange-default',
            'driver' => 'exchange',
            'config' => ['tenant_id' => 't'],
            'from_address' => 'ex@example.org',
            'from_name' => 'Ex',
            'is_default' => true,
            'is_active' => true,
        ]);
        PortalEmailProvider::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'HTTP',
            'slug' => 'http-primary',
            'driver' => 'http',
            'config' => [
                'base_url' => 'https://notifications.africacdc.org/api/v1',
                'client_id' => 'staff-portal',
                'client_secret' => 'secret',
            ],
            'from_address' => 'n@example.org',
            'from_name' => 'Notify',
            'is_default' => false,
            'is_active' => true,
        ]);

        $resolved = app(EmailProvidersService::class)->resolveForSend(null);

        $this->assertSame('http', $resolved['driver']);
        $this->assertSame('http-primary', $resolved['provider']->slug);
    }
}
```

Adjust create() columns to match the real migration (`portal_email_providers`) if names differ — read the migration first.

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd modules/staff-portal/backend && php artisan test --filter=EmailProvidersResolveForSendTest
```

Expected: FAIL (still prefers env/default exchange).

- [ ] **Step 3: Implement resolution**

Replace the `$provider === null` branch in `resolveForSend` with:

1. Active HTTP where `httpProviderHealthy` (non-empty `client_id`+`client_secret` after `withEnvFallbacks`).
2. Else `defaultProvider()` if active.
3. Else first active by driver order: `exchange`, `smtp`, `zoho`, then any other active.
4. Else env virtual provider (keep existing env-transport behavior).

```php
public function httpProviderHealthy(PortalEmailProvider $provider): bool
{
    if ($this->normalizeTransport($provider->driver) !== 'http' || ! $provider->is_active) {
        return false;
    }
    $config = $this->withEnvFallbacks('http', $provider->config ?? []);
    return trim((string) ($config['client_id'] ?? '')) !== ''
        && trim((string) ($config['client_secret'] ?? '')) !== '';
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd modules/staff-portal/backend && php artisan test --filter=EmailProvidersResolveForSendTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add modules/staff-portal/backend/Modules/Settings/app/Services/EmailProvidersService.php \
  modules/staff-portal/backend/tests/Unit/EmailProvidersResolveForSendTest.php
git commit -m "Prefer healthy HTTP email provider over UI default."
```

---

### Task 2: MailConfigCrypto

**Files:**
- Create: `modules/staff-portal/backend/app/Services/MailConfigCrypto.php`
- Create: `modules/staff-portal/backend/tests/Unit/MailConfigCryptoTest.php`
- Modify: `shared/load-staff-root-env.php` (ensure `STAFF_MAIL_CONFIG_KEY` is loaded if present)
- Modify: `.env.example` and `scripts/setup/templates/root.env.example` — document `STAFF_MAIL_CONFIG_KEY=`

**Interfaces:**
- Produces: `MailConfigCrypto::encrypt(array $payload): array{ciphertext, iv, tag, expires_at}`
- Produces: `MailConfigCrypto::decrypt(string $ciphertext, string $iv, string $tag): array`
- Key: `config('app.mail_config_key')` or `env('STAFF_MAIL_CONFIG_KEY')` or `config('app.key')`

- [ ] **Step 1: Write failing test**

```php
public function test_round_trip(): void
{
    config(['app.mail_config_key' => 'base64:'.base64_encode(random_bytes(32))]);
    $crypto = app(\App\Services\MailConfigCrypto::class);
    $payload = ['driver' => 'http', 'config' => ['client_secret' => 's'], 'from_address' => 'a@b.c', 'from_name' => 'N'];
    $enc = $crypto->encrypt($payload);
    $this->assertArrayHasKey('ciphertext', $enc);
    $dec = $crypto->decrypt($enc['ciphertext'], $enc['iv'], $enc['tag']);
    $this->assertSame($payload, $dec);
}
```

- [ ] **Step 2: Run — expect FAIL (class missing)**

```bash
cd modules/staff-portal/backend && php artisan test --filter=MailConfigCryptoTest
```

- [ ] **Step 3: Implement AES-256-GCM**

```php
<?php

namespace App\Services;

use RuntimeException;

final class MailConfigCrypto
{
    public function encrypt(array $payload, int $ttlSeconds = 3600): array
    {
        $key = $this->keyBytes();
        $iv = random_bytes(12);
        $tag = '';
        $plain = json_encode($payload, JSON_THROW_ON_ERROR);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Mail config encrypt failed');
        }

        return [
            'ciphertext' => base64_encode($cipher),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String(),
        ];
    }

    public function decrypt(string $ciphertext, string $iv, string $tag): array
    {
        $plain = openssl_decrypt(
            base64_decode($ciphertext, true) ?: '',
            'aes-256-gcm',
            $this->keyBytes(),
            OPENSSL_RAW_DATA,
            base64_decode($iv, true) ?: '',
            base64_decode($tag, true) ?: ''
        );
        if ($plain === false) {
            throw new RuntimeException('Mail config decrypt failed');
        }

        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    private function keyBytes(): string
    {
        $raw = (string) (config('app.mail_config_key') ?: env('STAFF_MAIL_CONFIG_KEY') ?: config('app.key'));
        if (str_starts_with($raw, 'base64:')) {
            $raw = base64_decode(substr($raw, 7), true) ?: '';
        }

        return hash('sha256', $raw, true);
    }
}
```

Add to `modules/staff-portal/backend/config/app.php` (or existing config):

```php
'mail_config_key' => env('STAFF_MAIL_CONFIG_KEY', env('APP_KEY')),
```

- [ ] **Step 4: Run tests — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "Add AES-GCM crypto for shared mail config payloads."
```

---

### Task 3: Share mail send + active-config endpoints

**Files:**
- Create: `modules/staff-portal/backend/Modules/Share/app/Http/Controllers/ShareMailController.php`
- Modify: `modules/staff-portal/backend/Modules/Share/routes/share.php`
- Create: `modules/staff-portal/backend/tests/Feature/ShareMailApiTest.php`

**Interfaces:**
- Consumes: `PortalMailer::send`, `EmailProvidersService::resolveForSend`, `MailConfigCrypto::encrypt`
- Produces: `POST /share/mail/send`, `GET /share/mail/active-config`

- [ ] **Step 1: Write Feature test (auth + send mocked)**

Use `Http::fake()` or mock `PortalMailer`. Authenticate with `config('share.api_token')` Bearer.

```php
public function test_active_config_requires_auth(): void
{
    $this->getJson('/share/mail/active-config')->assertUnauthorized();
}

public function test_active_config_returns_ciphertext(): void
{
    // seed http provider…
    config(['share.api_token' => 'test-token', 'app.mail_config_key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    $this->withToken('test-token')
        ->getJson('/share/mail/active-config')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['driver', 'ciphertext', 'iv', 'tag', 'expires_at']]);
}
```

(Confirm how Share static token is passed — header `Authorization: Bearer test-token` per `AuthenticateShareApi`.)

- [ ] **Step 2: Run — expect FAIL (404)**

```bash
cd modules/staff-portal/backend && php artisan test --filter=ShareMailApiTest
```

- [ ] **Step 3: Implement controller + routes**

Inside `AuthenticateShareApi` group in `share.php`:

```php
Route::post('mail/send', [ShareMailController::class, 'send']);
Route::get('mail/active-config', [ShareMailController::class, 'activeConfig']);
```

`send`: validate `to` (array|string), `subject`, `html`, optional `cc`/`bcc`/`attachments`/`provider_uuid`/`idempotency_key`; call `PortalMailer`; return `{ success, driver, provider_uuid }`.

`activeConfig`: `resolveForSend(null)`, encrypt `{ driver, config, from_address, from_name }`, return metadata without plaintext `config`.

- [ ] **Step 4: Tests PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "Expose Share mail send and encrypted active-config APIs."
```

---

### Task 4: Shared hybrid mail client

**Files:**
- Create: `shared/StaffPortalMailClient.php`
- Create: `modules/helpdesk/backend/app/Services/CbpMailDispatcher.php` (or require shared via composer path / `require_once`)
- Modify: helpdesk bootstrap or a service provider to load shared file if not autoloaded
- Document: `STAFF_MAIL_DISPATCH=auto|portal|local` in `.env.example`

**Interfaces:**
- Produces: `StaffPortalMailClient::send(string|array $to, string $subject, string $html, array $options = []): void`
- Options: `cc`, `bcc`, `attachments`, `provider_uuid`
- Behavior: `auto` → portal POST then fallback decrypt+local; `portal` → portal only; `local` → decrypt/config or env local only

- [ ] **Step 1: Unit-test client with Http::fake** (in staff-portal or helpdesk tests)

Fake:

1. `POST …/share/mail/send` → 200  
2. Then a case where send returns 503 and `GET …/share/mail/active-config` returns encrypted payload; decrypt with known key; assert local send invoked (mock).

Keep the first test to “portal success path only” if local mailer hard to mock; second test decrypt round-trip via crypto.

- [ ] **Step 2: Implement `shared/StaffPortalMailClient.php`**

Use `STAFF_API_INTERNAL_BASE_URL` / `config('services.staff_api…')` patterns already in helpdesk. Auth: Bearer `STAFF_API_TOKEN` or Basic from existing env.

Decrypt with same key algorithm as `MailConfigCrypto` (duplicate minimal decrypt in shared PHP **or** publish crypto to `shared/MailConfigCrypto.php` and use from portal via require — prefer **one** `shared/MailConfigCrypto.php` used by portal + modules to avoid drift).

If Task 2 put crypto only in portal, **move** crypto to `shared/MailConfigCrypto.php` in this task and thin-wrap in portal.

- [ ] **Step 3: Wire helpdesk one send path** (e.g. ticket notification) through dispatcher when `STAFF_MAIL_DISPATCH != local`

- [ ] **Step 4: Manual smoke**

```bash
curl -sS -X POST "http://localhost/staff/backend/share/mail/send" \
  -H "Authorization: Bearer $STAFF_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"to":["you@example.org"],"subject":"hub test","html":"<p>ok</p>"}'
```

Expected: `{"success":true,"driver":"http",…}` when HTTP configured.

- [ ] **Step 5: Commit**

```bash
git commit -m "Add shared StaffPortalMailClient with portal-first hybrid send."
```

---

### Task 5: UI default + env seed (HTTP primary)

**Files:**
- Modify: `modules/staff-portal/frontend/src/pages/settings/EmailServersPage.vue` — `form.driver = 'http'`, `openCreate` uses http
- Modify: `modules/staff-portal/backend/Modules/Settings/app/Services/EmailProvidersService.php` — `seedHttpFromEnvIfMissing(): void`
- Call seed from `EmailProvidersController::index` or a one-shot artisan `email-providers:seed-http` run by setup
- Modify: `docs/SETUP.md` — HTTP primary, Share mail hub brief

- [ ] **Step 1: Change UI default driver to `http`**

- [ ] **Step 2: Seed method** — if no active/inactive `http` row exists, create from `MAIL_HTTP_*` / from address; set `is_active=true`; set `is_default=true` only if no other default. **Never update** existing HTTP row fields that are non-empty in DB.

- [ ] **Step 3: Test seed idempotency** (unit)

- [ ] **Step 4: Commit**

```bash
git commit -m "Default email UI and seed to Africa CDC HTTP provider."
```

---

### Task 6: Roll remaining modules (APM, Finance, Risk Register)

**Files:**
- APM: `app/Helpers/MailingHelper.php` and/or `SendNotificationEmailJob` — optional facade to shared client for new sends (feature flag)
- Finance: payslip / notification jobs similarly
- Risk Register: if mailer exists, same pattern
- Each module `.env.example`: `STAFF_MAIL_DISPATCH=auto`, `STAFF_MAIL_CONFIG_KEY=` (inherited)

- [ ] **Step 1: APM — wrap primary notification send with hybrid client behind `STAFF_MAIL_DISPATCH`**
- [ ] **Step 2: Finance — same for one job**
- [ ] **Step 3: Risk Register — same if applicable**
- [ ] **Step 4: Commit**

```bash
git commit -m "Route CBP module mail through portal hub client by default."
```

---

### Task 7: Docs + setup wizard copy

**Files:**
- `docs/SETUP.md`
- `setup.sh` mail prompt: list HTTP (notifications) as **1 / default**, Exchange as 2, SMTP as 3 (align with product priority; keep zoho if present)

- [ ] **Step 1: Update SETUP.md table for mail hub + Share endpoints**
- [ ] **Step 2: Reorder setup.sh transport choices (HTTP default)**
- [ ] **Step 3: Commit + push**

```bash
git commit -m "Document portal mail hub and default HTTP transport in setup."
git push
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Portal dispatch default | 3, 4 |
| Encrypted config fetch | 2, 3, 4 |
| HTTP → default → Exchange → SMTP | 1 |
| UI edits win / env seed | 5 |
| Hybrid auto/portal/local | 4 |
| Module rollout | 6 |
| Docs | 7 |
| notifications API unchanged | (consume only via existing Http client) |

## Placeholder / consistency scan

- Crypto lives in `shared/` after Task 4 (portal wraps or uses shared).  
- Share paths are `/share/mail/send` and `/share/mail/active-config` under staff-portal `APP_URL` (`…/backend/share/…`).  
- No TBD steps remaining.

---

## Execution

Plan complete and saved to `docs/superpowers/plans/2026-10-02-portal-mail-hub.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — run tasks in this session with executing-plans checkpoints  

Which approach?
