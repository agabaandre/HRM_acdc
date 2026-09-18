<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StaffPortalReferenceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BrandingController extends Controller
{
    public function __invoke(StaffPortalReferenceClient $client): JsonResponse
    {
        $defaults = [
            'company_name' => 'Africa CDC',
            'logo_url' => '',
            'system_logo' => '/assets/images/AU_CDC_Logo-800.png',
            'footer_copyright' => 'Copyright © Africa CDC {year}. All rights reserved.',
            'footer_copyright_rendered' => 'Copyright © Africa CDC '.date('Y').'. All rights reserved.',
            'print_footer' => '',
            'company_email' => 'registry@africacdc.org',
            'company_phone' => '',
            'company_website' => 'https://africacdc.org',
            'company_address' => '',
            'login_welcome_title' => 'Welcome Back',
            'login_welcome_text' => '',
            'login_background' => '/assets/images/bg_login.jpg',
            'login_background_url' => '',
        ];

        try {
            $payload = Cache::remember('helpdesk.staff_branding', 300, function () use ($client, $defaults) {
                if (! $client->isConfigured()) {
                    return $defaults;
                }

                return array_merge($defaults, $client->fetchBranding());
            });
        } catch (\Throwable $e) {
            Log::debug('Helpdesk branding fetch failed: '.$e->getMessage());
            $payload = $defaults;
        }

        return response()->json(['data' => $payload]);
    }
}
