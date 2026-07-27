<?php

namespace App\Http\Controllers\Api\V1\Tools;

use Illuminate\Http\Request;

trait AuthorizesHelpdeskTools
{
    protected function ensureItAssetManager(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless($p && $p->canManageItAssets(), 403, 'You need IT Assets management permission.');
    }

    protected function ensureLicenseManager(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless($p && $p->canManageLicenses(), 403, 'You need License management permission.');
    }

    protected function ensureInformationSystemsManager(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless($p && $p->canManageInformationSystems(), 403, 'You need Information Systems management permission.');
    }

    protected function ensureSoftwareRequestSubmit(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless($p && $p->canSubmitSoftwareRequests(), 403, 'You cannot submit software requests.');
    }

    protected function ensureSoftwareRequestManage(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless(
            $p && ($p->canManageSoftwareRequests() || $p->canApproveSoftwareRequests()),
            403,
            'You need software request approval or management permission.'
        );
    }

    protected function ensureHostingProcess(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless($p && $p->canProcessHostingRequests(), 403, 'You need hosting request processing permission.');
    }

    protected function ensureInnovationProcess(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless($p && $p->canProcessInnovationRequests(), 403, 'You need innovation request processing permission.');
    }
}
