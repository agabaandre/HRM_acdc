<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PortalMailer;
use App\Support\RiskPermissions;
use App\Support\RrSettingsBag;
use Staff\Shared\StaffApiCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Staff\Shared\ModuleEmailSettings;
use Throwable;

class EmailSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $bag = new RrSettingsBag;
        $share = StaffApiCredentials::resolve($bag)['base_url'];

        return response()->json([
            'data' => ModuleEmailSettings::snapshot(
                moduleLabel: 'Risk Register',
                defaultFromName: 'Africa CDC Risk Register',
                defaultSubjectPrefix: 'Risk Register',
                moduleEnvPath: base_path('.env'),
                shareBaseUrl: $share,
                bag: $bag,
            ),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'staff_mail_dispatch' => ['nullable', 'string', 'in:auto,portal,local'],
            'mail_transport' => ['nullable', 'string', 'in:http,exchange,smtp,zoho,log'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_subject_prefix' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $result = ModuleEmailSettings::persist(
                $validated,
                'Africa CDC Risk Register',
                'Risk Register',
                base_path('.env'),
                bag: new RrSettingsBag,
            );

            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (Throwable $e) {
            Log::error('Risk Register email settings update failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function test(Request $request, PortalMailer $mailer): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'to' => ['required', 'email', 'max:255'],
        ]);

        $result = ModuleEmailSettings::sendTest(
            $validated['to'],
            'Risk Register',
            'Risk Register',
            function (string $to, string $subject, string $body) use ($mailer): bool {
                $mailer->send($to, $subject, $body);

                return true;
            },
            new RrSettingsBag,
            'Africa CDC Risk Register',
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    private function authorizeManage(Request $request): void
    {
        if (! RiskPermissions::canManage($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Unauthorized.'], 403));
        }
    }
}
