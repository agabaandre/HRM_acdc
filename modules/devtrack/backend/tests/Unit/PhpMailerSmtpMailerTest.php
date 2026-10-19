<?php

namespace Tests\Unit;

use App\Enums\EmailDriver;
use App\Models\EmailProvider;
use App\Services\PhpMailerSmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PhpMailerSmtpMailerTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_non_smtp_providers(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'Exchange',
            'slug' => 'exchange',
            'driver' => EmailDriver::Exchange,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PHPMailer SMTP transport requires an SMTP provider.');

        app(PhpMailerSmtpMailer::class)->send(
            provider: $provider,
            to: 'to@example.com',
            subject: 'Test',
            body: 'Hello',
            isHtml: false,
            fromAddress: 'from@example.com',
            fromName: 'From',
        );
    }

    public function test_requires_smtp_host(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'SMTP',
            'slug' => 'smtp',
            'driver' => EmailDriver::Smtp,
            'config' => ['port' => 587],
            'from_address' => 'from@example.com',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMTP host is not configured');

        app(PhpMailerSmtpMailer::class)->send(
            provider: $provider,
            to: 'to@example.com',
            subject: 'Test',
            body: 'Hello',
            isHtml: false,
            fromAddress: 'from@example.com',
            fromName: 'From',
        );
    }

    public function test_requires_from_address(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'SMTP',
            'slug' => 'smtp-from',
            'driver' => EmailDriver::Smtp,
            'config' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
            ],
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('From address is required');

        app(PhpMailerSmtpMailer::class)->send(
            provider: $provider,
            to: 'to@example.com',
            subject: 'Test',
            body: 'Hello',
            isHtml: false,
            fromAddress: '',
            fromName: 'From',
        );
    }

    public function test_requires_password_when_username_present(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'SMTP',
            'slug' => 'smtp-pass',
            'driver' => EmailDriver::Smtp,
            'config' => [
                'host' => 'mail.example.com',
                'port' => 465,
                'encryption' => 'ssl',
                'username' => 'notifications@example.com',
            ],
            'from_address' => 'notifications@example.com',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMTP password is missing');

        app(PhpMailerSmtpMailer::class)->resolveSettings($provider, 'notifications@example.com');
    }

    public function test_replaces_hostname_username_with_from_address(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'SMTP',
            'slug' => 'smtp-host-user',
            'driver' => EmailDriver::Smtp,
            'config' => [
                'host' => 'mail.africacdc.net',
                'port' => 465,
                'encryption' => 'ssl',
                'username' => 'mail.africacdc.net',
                'password' => 'secret',
            ],
            'from_address' => 'notifications@africacdc.net',
            'is_default' => true,
            'is_active' => true,
        ]);

        $settings = app(PhpMailerSmtpMailer::class)->resolveSettings(
            $provider,
            'notifications@africacdc.net'
        );

        $this->assertSame('notifications@africacdc.net', $settings['username']);
        $this->assertSame('ssl', $settings['encryption']);
        $this->assertSame(465, $settings['port']);
    }

    public function test_infers_ssl_encryption_from_port_465(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'SMTP',
            'slug' => 'smtp-port',
            'driver' => EmailDriver::Smtp,
            'config' => [
                'host' => 'mail.example.com',
                'port' => 465,
                'username' => 'user@example.com',
                'password' => 'secret',
            ],
            'is_default' => true,
            'is_active' => true,
        ]);

        $settings = app(PhpMailerSmtpMailer::class)->resolveSettings($provider, 'user@example.com');

        $this->assertSame('ssl', $settings['encryption']);
    }
}
