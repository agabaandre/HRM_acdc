<?php

namespace Tests\Unit;

use Illuminate\Http\Response;
use Staff\Shared\SessionCookieClearer;
use Tests\TestCase;

class SessionCookieClearerTest extends TestCase
{
    public function test_forget_session_cookies_tolerates_null_secure_and_same_site(): void
    {
        config([
            'session.cookie' => 'apm_session',
            'session.path' => '/',
            'session.domain' => null,
            'session.secure' => null,
            'session.same_site' => null,
        ]);

        $response = new Response('ok');

        SessionCookieClearer::forgetSessionCookies($response, ['/apm', '/staff/apm']);

        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);

        foreach ($cookies as $cookie) {
            $this->assertSame('apm_session', $cookie->getName());
            $this->assertTrue($cookie->getExpiresTime() < time());
        }
    }

    public function test_clear_accepts_explicit_bool_secure_and_string_same_site(): void
    {
        $response = new Response('ok');

        SessionCookieClearer::clear(
            $response,
            'apm_session',
            '/staff/apm',
            null,
            true,
            'lax',
        );

        $cookie = $response->headers->getCookies()[0] ?? null;
        $this->assertNotNull($cookie);
        $this->assertSame('apm_session', $cookie->getName());
        $this->assertSame('/staff/apm', $cookie->getPath());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
    }
}
