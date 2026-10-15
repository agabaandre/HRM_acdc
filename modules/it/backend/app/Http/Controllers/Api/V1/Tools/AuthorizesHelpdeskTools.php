<?php

namespace App\Http\Controllers\Api\V1\Tools;

use Illuminate\Http\Request;

trait AuthorizesHelpdeskTools
{
    protected function ensureItAssetManager(Request $request): void
    {
        abort_unless((int) $request->attributes->get('risk_staff_id') > 0, 401);
    }

    protected function ensureLicenseManager(Request $request): void
    {
        abort_unless((int) $request->attributes->get('risk_staff_id') > 0, 401);
    }

    protected function ensureInformationSystemsManager(Request $request): void
    {
        abort_unless((int) $request->attributes->get('risk_staff_id') > 0, 401);
    }

    protected function ensureServerManager(Request $request): void
    {
        abort_unless((int) $request->attributes->get('risk_staff_id') > 0, 401);
    }
}
