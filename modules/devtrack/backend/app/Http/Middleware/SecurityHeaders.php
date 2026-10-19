<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Defense in depth when Nginx is absent (artisan serve / tests).
        // Skip headers already set by the reverse proxy to avoid duplicates.
        $this->setMissing($response, 'X-Content-Type-Options', 'nosniff');
        $this->setMissing($response, 'X-Frame-Options', 'SAMEORIGIN');
        $this->setMissing($response, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setMissing($response, 'X-XSS-Protection', '0');
        $this->setMissing(
            $response,
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
        );
        $this->setMissing($response, 'Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $this->setMissing($response, 'Cross-Origin-Resource-Policy', 'same-site');
        $response->headers->remove('X-Powered-By');

        $csp = $this->contentSecurityPolicy($request);
        $this->setMissing($response, 'Content-Security-Policy', $csp);

        // Authenticated admin API — never let shared caches store responses.
        if ($this->shouldDisableCaching($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if ($request->secure()) {
            $this->setMissing($response, 'Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $base = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none';";
        // Fonts are self-hosted (Inter via @fontsource, MDI via npm) — no Google Fonts CDN.
        $fonts = "font-src 'self' data:;";
        $images = "img-src 'self' data: blob:;";

        if ($this->isApiDocsRequest($request)) {
            // Swagger UI injects <style> tags; keep element inline styles for docs only.
            return "{$base} script-src 'self'; style-src-elem 'self' 'unsafe-inline'; style-src-attr 'unsafe-inline'; {$fonts} {$images} connect-src 'self';";
        }

        // JSON API — no third-party style/font CDNs; no blanket style-src unsafe-inline.
        return "{$base} script-src 'self'; style-src 'self'; {$fonts} {$images} connect-src 'self';";
    }

    private function isApiDocsRequest(Request $request): bool
    {
        return in_array($request->path(), ['api/documentation', 'api/docs.json', 'docs'], true);
    }

    private function shouldDisableCaching(Request $request): bool
    {
        if ($request->is('docs-assets/*') || $request->is('storage/*')) {
            return false;
        }

        return $request->is('api/*') || $request->is('up') || $request->is('docs');
    }

    private function setMissing(Response $response, string $name, string $value): void
    {
        if (! $response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }
}
