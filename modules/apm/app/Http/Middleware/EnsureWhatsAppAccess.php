<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWhatsAppAccess
{
    public function handle(Request $request, Closure $next, string $area = 'config'): Response
    {
        $allowed = $area === 'module'
            ? whatsapp_module_can_access()
            : whatsapp_config_can_access();

        if (! $allowed) {
            abort(
                403,
                $area === 'module'
                    ? 'You do not have access to WhatsApp groups.'
                    : 'Unauthorized access to WhatsApp configuration.'
            );
        }

        return $next($request);
    }
}
