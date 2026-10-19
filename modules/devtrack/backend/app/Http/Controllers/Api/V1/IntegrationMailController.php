<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IntegrationSendMailRequest;
use App\Services\EmailAttachmentService;
use App\Services\EmailDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationMailController extends Controller
{
    public function send(
        IntegrationSendMailRequest $request,
        EmailDispatchService $dispatch,
        EmailAttachmentService $attachments,
    ): JsonResponse {
        /** @var \App\Models\ExternalIntegration $integration */
        $integration = $request->attributes->get('integration');

        $requestedProviderId = $request->validated('provider_id');
        $providerId = $integration->email_provider_id;

        if ($requestedProviderId !== null && (int) $requestedProviderId !== (int) $providerId) {
            return response()->json([
                'message' => 'provider_id is not allowed for this integration.',
            ], 403);
        }

        $attachmentPayload = $attachments->normalizeFromRequest($request->input('attachments'));

        $log = $dispatch->queue(
            to: $request->validated('to'),
            subject: $request->validated('subject'),
            body: $request->validated('body'),
            isHtml: $request->boolean('is_html', true),
            providerId: $providerId,
            integration: $integration,
            cc: $request->validated('cc') ?? [],
            bcc: $request->validated('bcc') ?? [],
            source: 'integration',
            senderIp: $request->ip(),
            attachmentPayload: $attachmentPayload,
        );

        $integration->update(['last_used_at' => now()]);

        return response()->json([
            'message' => 'Email accepted and queued for delivery.',
            'log_id' => $log->id,
            'status' => $log->status,
            'attachment_count' => (int) (($log->meta['attachment_count'] ?? count($attachmentPayload))),
        ]);
    }

    public function showLog(int $logId, Request $request): JsonResponse
    {
        /** @var \App\Models\ExternalIntegration $integration */
        $integration = $request->attributes->get('integration');

        $log = \App\Models\EmailLog::query()
            ->where('id', $logId)
            ->where('external_integration_id', $integration->id)
            ->firstOrFail();

        return response()->json([
            'log_id' => $log->id,
            'to' => $log->to,
            'subject' => $log->subject,
            'status' => $log->status,
            'driver' => $log->driver,
            'error_message' => $log->error_message,
            'attachment_count' => (int) (($log->meta['attachment_count'] ?? (is_array($log->meta['attachments'] ?? null) ? count($log->meta['attachments']) : 0))),
            'created_at' => $log->created_at,
            'updated_at' => $log->updated_at,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        /** @var \App\Models\ExternalIntegration $integration */
        $integration = $request->attributes->get('integration');

        return response()->json([
            'integration' => $integration->only(['id', 'name', 'slug', 'is_active']),
            'provider' => $integration->emailProvider?->only(['id', 'name', 'driver']),
        ]);
    }
}
