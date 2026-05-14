<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft Teams / Azure Bot Service inbound activity endpoint (placeholder).
 *
 * @see https://learn.microsoft.com/en-us/azure/bot-service/rest-api/bot-framework-rest-connector-api-reference
 */
class TeamsWebhookController extends Controller
{
    public function activities(Request $request): JsonResponse
    {
        try {
            Log::channel('iso_json')->info('helpdesk.webhook.teams', [
                'event' => 'teams_inbound',
                'activity_type' => $request->input('type'),
            ]);
        } catch (\Throwable) {
        }

        return response()->json(['type' => 'message', 'text' => 'Helpdesk bot acknowledgement — configure routing in a later iteration.']);
    }
}
