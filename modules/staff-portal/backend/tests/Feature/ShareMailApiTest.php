<?php

namespace Tests\Feature;

use App\Services\PortalMailer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Settings\Models\PortalEmailProvider;
use Tests\TestCase;

class ShareMailApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.url' => 'http://localhost',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.mail_config_key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'share.api_token' => 'test-token',
            'cache.default' => 'array',
            'mail.transport' => 'log',
            'mail.from.address' => 'fallback@example.org',
            'mail.from.name' => 'Fallback',
        ]);
        URL::forceRootUrl('http://localhost');
        DB::purge();
        DB::reconnect();
        Cache::flush();

        Schema::dropIfExists('portal_email_providers');
        Schema::create('portal_email_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver', 32);
            $table->json('config')->nullable();
            $table->string('from_address')->default('');
            $table->string('from_name')->default('');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_active_config_requires_auth(): void
    {
        $this->getJson('/share/mail/active-config')->assertUnauthorized();
    }

    public function test_active_config_returns_ciphertext(): void
    {
        PortalEmailProvider::query()->create([
            'uuid' => (string) Str::uuid(),
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

        $this->withToken('test-token')
            ->getJson('/share/mail/active-config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver', 'http')
            ->assertJsonMissingPath('data.config')
            ->assertJsonStructure([
                'data' => [
                    'driver',
                    'from_address',
                    'from_name',
                    'ciphertext',
                    'iv',
                    'tag',
                    'expires_at',
                    'provider_uuid',
                ],
            ]);
    }

    public function test_send_dispatches_via_portal_mailer(): void
    {
        $uuid = (string) Str::uuid();
        PortalEmailProvider::query()->create([
            'uuid' => $uuid,
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

        $this->mock(PortalMailer::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturnNull();
        });

        $this->withToken('test-token')
            ->postJson('/share/mail/send', [
                'to' => ['user@example.org'],
                'subject' => 'Hub test',
                'html' => '<p>ok</p>',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('driver', 'http')
            ->assertJsonPath('provider_uuid', $uuid);
    }

    public function test_send_returns_503_when_mailer_fails(): void
    {
        PortalEmailProvider::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HTTP',
            'slug' => 'http-primary',
            'driver' => 'http',
            'config' => [
                'client_id' => 'staff-portal',
                'client_secret' => 'secret',
            ],
            'from_address' => 'n@example.org',
            'from_name' => 'Notify',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->mock(PortalMailer::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('upstream down'));
        });

        $this->withToken('test-token')
            ->postJson('/share/mail/send', [
                'to' => 'user@example.org',
                'subject' => 'Hub test',
                'html' => '<p>ok</p>',
            ])
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }
}
