<?php

namespace Tests\Unit;

use App\Models\EmailProvider;
use App\Services\ExchangeConfigurationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeConfigurationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_provider_config_is_used_not_env_config(): void
    {
        config([
            'exchange-email.tenant_id' => 'env-tenant',
            'exchange-email.client_id' => 'env-client',
            'exchange-email.client_secret' => 'env-secret',
            'exchange-email.from_email' => 'env@example.com',
            'exchange-email.from_name' => 'Env Mailer',
            'mail.from.address' => 'env-mail@example.com',
            'mail.from.name' => 'Env Mail From',
        ]);

        $provider = EmailProvider::factory()->create([
            'config' => [
                'tenant_id' => 'db-tenant',
                'client_id' => 'db-client',
                'client_secret' => 'db-secret',
            ],
            'from_address' => 'db@example.com',
            'from_name' => 'DB Mailer',
        ]);

        $resolver = app(ExchangeConfigurationResolver::class);
        $resolved = $resolver->resolve($provider);

        $this->assertSame('db-tenant', $resolved['tenant_id']);
        $this->assertSame('db-client', $resolved['client_id']);
        $this->assertSame('db-secret', $resolved['client_secret']);
        $this->assertSame('db@example.com', $resolved['from_email']);
        $this->assertSame('DB Mailer', $resolved['from_name']);
    }

    public function test_runtime_config_is_used_when_no_provider_passed(): void
    {
        config([
            'exchange-email.tenant_id' => 'runtime-tenant',
            'exchange-email.client_id' => 'runtime-client',
            'exchange-email.client_secret' => 'runtime-secret',
            'exchange-email.from_email' => 'runtime@example.com',
            'exchange-email.from_name' => 'Runtime Mailer',
        ]);

        $resolver = app(ExchangeConfigurationResolver::class);
        $resolved = $resolver->resolve(null);

        $this->assertSame('runtime-tenant', $resolved['tenant_id']);
        $this->assertSame('runtime@example.com', $resolved['from_email']);
        $this->assertTrue($resolver->isConfigured());
    }

    public function test_default_from_name_falls_back_to_africa_cdc_mailer(): void
    {
        config([
            'exchange-email.from_email' => null,
            'exchange-email.from_name' => null,
            'mail.from.address' => null,
            'mail.from.name' => null,
        ]);

        $provider = EmailProvider::factory()->create([
            'from_address' => 'mailer@example.com',
            'from_name' => null,
        ]);

        $resolver = app(ExchangeConfigurationResolver::class);

        $this->assertSame('mailer@example.com', $resolver->resolveFromAddress($provider));
        $this->assertSame('Africa CDC Mailer', $resolver->resolveFromName($provider));
    }
}
