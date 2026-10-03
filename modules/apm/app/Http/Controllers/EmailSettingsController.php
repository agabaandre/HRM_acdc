<?php

namespace App\Http\Controllers;

use App\Support\SystemSettingsBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Staff\Shared\ModuleEmailSettings;
use Staff\Shared\StaffApiCredentials;
use Throwable;

class EmailSettingsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function getIndexData(): array
    {
        $bag = new SystemSettingsBag('mail');
        $shareBase = StaffApiCredentials::resolve(new SystemSettingsBag('staff_api'))['base_url'];

        $data = ModuleEmailSettings::snapshot(
            moduleLabel: 'APM',
            defaultFromName: 'Africa CDC APM',
            defaultSubjectPrefix: 'APM',
            moduleEnvPath: base_path('.env'),
            shareBaseUrl: $shareBase,
            bag: $bag,
        );
        $data['update_url'] = route('email-settings.update');
        $data['test_url'] = route('email-settings.test');

        return $data;
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
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
                'Africa CDC APM',
                'APM',
                base_path('.env'),
                bag: new SystemSettingsBag('mail'),
            );
        } catch (Throwable $e) {
            Log::error('APM email settings update failed', ['error' => $e->getMessage()]);

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()
                ->route('system-configs.index', ['tab' => 'email'])
                ->with('type', 'danger')
                ->with('msg', 'Could not save email settings: '.$e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $result['message']]);
        }

        return redirect()
            ->route('system-configs.index', ['tab' => 'email'])
            ->with('type', 'success')
            ->with('msg', $result['message']);
    }

    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'email', 'max:255'],
        ]);

        $bag = new SystemSettingsBag('mail');
        $result = ModuleEmailSettings::sendTest(
            $validated['to'],
            'APM',
            'APM',
            function (string $to, string $subject, string $body): bool {
                if (! function_exists('sendEmail')) {
                    require_once app_path('Helpers/MailingHelper.php');
                }

                return (bool) sendEmail($to, $subject, $body);
            },
            $bag,
            'Africa CDC APM',
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
