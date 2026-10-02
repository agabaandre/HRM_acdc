<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Settings\Models\PortalEmailProvider;
use Modules\Settings\Services\EmailProvidersService;
use Tests\TestCase;

class EmailProvidersResolveForSendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.transport' => 'exchange',
            'mail.from.address' => 'fallback@example.org',
            'mail.from.name' => 'Fallback',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);
        DB::purge();
        DB::reconnect();

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

    public function test_prefers_active_http_over_default_exchange(): void
    {
        PortalEmailProvider::query()->create([
            'uuid' => (string) Str::uuid(),
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

        $resolved = app(EmailProvidersService::class)->resolveForSend(null);

        $this->assertSame('http', $resolved['driver']);
        $this->assertSame('http-primary', $resolved['provider']->slug);
    }

    public function test_falls_back_to_default_when_http_unhealthy(): void
    {
        PortalEmailProvider::query()->create([
            'uuid' => (string) Str::uuid(),
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
            'uuid' => (string) Str::uuid(),
            'name' => 'HTTP empty',
            'slug' => 'http-empty',
            'driver' => 'http',
            'config' => ['client_id' => '', 'client_secret' => ''],
            'from_address' => 'n@example.org',
            'from_name' => 'Notify',
            'is_default' => false,
            'is_active' => true,
        ]);

        config([
            'mail.http.client_id' => '',
            'mail.http.client_secret' => '',
        ]);

        $resolved = app(EmailProvidersService::class)->resolveForSend(null);

        $this->assertSame('exchange', $resolved['driver']);
        $this->assertSame('exchange-default', $resolved['provider']->slug);
    }
}
