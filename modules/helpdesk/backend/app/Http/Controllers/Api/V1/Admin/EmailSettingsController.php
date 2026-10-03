<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\HelpdeskSettingsBag;
use App\Support\StaffApiBaseUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Staff\Shared\ModuleEmailSettings;
use Staff\Shared\StaffApiCredentials;
use Staff\Shared\StaffPortalMailClient;
use Throwable;

class EmailSettingsController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function show(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);
        $bag = new HelpdeskSettingsBag;
        $share = StaffApiCredentials::resolve($bag)['base_url'];

        return response()->json([
            'data' => ModuleEmailSettings::snapshot(
                moduleLabel: 'Helpdesk',
                defaultFromName: 'Africa CDC HelpDesk',
                defaultSubjectPrefix: 'HelpDesk',
                moduleEnvPath: base_path('.env'),
                shareBaseUrl: $share,
                bag: $bag,
            ),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

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
                'Africa CDC HelpDesk',
                'HelpDesk',
                base_path('.env'),
                bag: new HelpdeskSettingsBag,
            );

            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (Throwable $e) {
            Log::error('Helpdesk email settings update failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function test(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'to' => ['required', 'email', 'max:255'],
        ]);

        $bag = new HelpdeskSettingsBag;
        $api = StaffApiCredentials::resolve($bag);
        $mail = ModuleEmailSettings::resolve('Africa CDC HelpDesk', 'HelpDesk', $bag);

        $result = ModuleEmailSettings::sendTest(
            $validated['to'],
            'Helpdesk',
            'HelpDesk',
            function (string $to, string $subject, string $body) use ($api, $mail): bool {
                $client = new StaffPortalMailClient(
                    baseUrl: StaffApiBaseUrl::resolve($api['base_url']),
                    token: $api['token'] !== '' ? $api['token'] : null,
                    username: $api['username'] !== '' ? $api['username'] : null,
                    password: $api['password'] !== '' ? $api['password'] : null,
                    dispatch: $mail['staff_mail_dispatch'],
                    configKey: env('STAFF_MAIL_CONFIG_KEY') ?: (string) config('app.key'),
                    localSender: function (string|array $recipients, string $subj, string $html): void {
                        \Illuminate\Support\Facades\Mail::html($html, function ($message) use ($recipients, $subj): void {
                            $message->to($recipients)->subject($subj);
                        });
                    },
                );
                $client->send($to, $subject, $body);

                return true;
            },
            $bag,
            'Africa CDC HelpDesk',
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
