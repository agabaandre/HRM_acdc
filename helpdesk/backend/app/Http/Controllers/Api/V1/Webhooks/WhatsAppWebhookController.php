<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Cloud API webhooks — subscription verification and inbound payloads.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/components
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->input('hub.mode', '');
        $token = (string) $request->input('hub.verify_token', '');
        $challenge = (string) $request->input('hub.challenge', '');

        $expected = (string) HelpdeskSetting::getValue(HelpdeskSetting::KEY_WHATSAPP_VERIFY_TOKEN, '');
        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        try {
            Log::channel('iso_json')->info('helpdesk.webhook.whatsapp', [
                'event' => 'whatsapp_inbound',
                'payload_keys' => array_keys(is_array($payload) ? $payload : []),
            ]);
        } catch (\Throwable) {
        }

        // Ticket creation from WhatsApp is implemented in a follow-up iteration (URS §12–14).
        return response()->json(['ok' => true]);
    }
}
