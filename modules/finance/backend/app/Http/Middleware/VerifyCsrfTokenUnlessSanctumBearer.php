<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;
use Illuminate\Http\Request;

class VerifyCsrfTokenUnlessSanctumBearer extends Middleware
{
    /**
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
        '*/api/*',
    ];

    /**
     * The Vue SPA authenticates with Sanctum bearer tokens. Same-origin session
     * cookies still trigger Sanctum's stateful CSRF pipeline, which 419s
     * multipart leave apply when the X-XSRF-TOKEN header is missing.
     */
    protected function inExceptArray($request): bool
    {
        if ($this->hasSanctumBearerToken($request)) {
            return true;
        }

        return parent::inExceptArray($request);
    }

    protected function hasSanctumBearerToken(Request $request): bool
    {
        $token = $request->bearerToken();

        return is_string($token) && $token !== '';
    }
}
