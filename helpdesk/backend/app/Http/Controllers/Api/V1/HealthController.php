<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSetting;
use App\Services\StaffPortalReferenceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    /**
     * Public health + branding hints (aligns with APM system-settings palette; admin overrides in helpdesk_settings).
     */
    public function __invoke(StaffPortalReferenceClient $staffPortalReferenceClient): JsonResponse
    {
        $primary = '#119a48';
        $secondary = '#c9a227';
        if (Schema::hasTable('helpdesk_settings')) {
            $primary = HelpdeskSetting::getValue(HelpdeskSetting::KEY_BRANDING_PRIMARY, $primary) ?? $primary;
            $secondary = HelpdeskSetting::getValue(HelpdeskSetting::KEY_BRANDING_SECONDARY, $secondary) ?? $secondary;
        }

        return response()->json([
            'status' => 'ok',
            'service' => 'africa-cdc-helpdesk-api',
            'version' => '0.1.0',
            'laravel' => app()->version(),
            'branding' => [
                'primary' => $primary,
                'primary_dark' => '#0d7a3a',
                'accent' => '#9f2240',
                'secondary' => $secondary,
            ],
            'integrations' => [
                'staff_portal_base' => rtrim(config('helpdesk.staff_portal_url', 'http://localhost/staff'), '/'),
                'apm_settings' => rtrim(config('helpdesk.apm_base_url', 'http://localhost/staff/apm'), '/').'/system-settings',
            ],
            'staff_share_api' => [
                'configured' => $staffPortalReferenceClient->isConfigured(),
            ],
        ]);
    }
}
