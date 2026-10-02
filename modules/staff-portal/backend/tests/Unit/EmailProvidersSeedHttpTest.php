<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Settings\Models\PortalEmailProvider;
use Modules\Settings\Services\EmailProvidersService;
use Tests\TestCase;

class EmailProvidersSeedHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'mail.http.base_url' => 'https://notifications.africacdc.org/api/v1',
            'mail.http.client_id' => 'seed-client',
            'mail.http.client_secret' => 'seed-secret',
            'mail.from.address' => 'notifications@africacdc.org',
            'mail.from.name' => 'Africa CDC',
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

    public function test_seeds_http_provider_once_from_env(): void
    {
        $service = app(EmailProvidersService::class);

        $first = $service->seedHttpFromEnvIfMissing();
        $this->assertNotNull($first);
        $this->assertSame('http', $first->driver);
        $this->assertTrue($first->is_default);
        $this->assertSame('seed-client', $first->config['client_id'] ?? null);

        $countAfterFirst = PortalEmailProvider::query()->where('driver', 'http')->count();
        $this->assertSame(1, $countAfterFirst);

        // Mutate UI fields — seed must not overwrite
        $first->update([
            'name' => 'UI Edited HTTP',
            'config' => [
                'base_url' => 'https://notifications.africacdc.org/api/v1',
                'client_id' => 'ui-client',
                'client_secret' => 'ui-secret',
            ],
        ]);

        $second = $service->seedHttpFromEnvIfMissing();
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PortalEmailProvider::query()->where('driver', 'http')->count());
        $this->assertSame('UI Edited HTTP', $second->fresh()->name);
        $this->assertSame('ui-client', $second->fresh()->config['client_id'] ?? null);
    }

    public function test_skips_seed_when_http_creds_empty(): void
    {
        config([
            'mail.http.client_id' => '',
            'mail.http.client_secret' => '',
        ]);

        $this->assertNull(app(EmailProvidersService::class)->seedHttpFromEnvIfMissing());
        $this->assertSame(0, PortalEmailProvider::query()->count());
    }
}
