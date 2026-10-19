<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SendMailRequest;
use App\Services\EmailAttachmentService;
use App\Services\EmailDispatchService;
use Illuminate\Http\JsonResponse;
use Throwable;

class MailController extends Controller
{
    public function send(
        SendMailRequest $request,
        EmailDispatchService $dispatch,
        EmailAttachmentService $attachments,
    ): JsonResponse {
        $attachmentPayload = $attachments->normalizeFromRequest($request->input('attachments'));

        try {
            $log = $dispatch->queue(
                to: $request->validated('to'),
                subject: $request->validated('subject'),
                body: $request->validated('body'),
                isHtml: $request->boolean('is_html', true),
                providerId: $request->validated('provider_id'),
                integration: null,
                cc: $request->validated('cc') ?? [],
                bcc: $request->validated('bcc') ?? [],
                source: 'admin',
                senderIp: $request->ip(),
                attachmentPayload: $attachmentPayload,
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Failed to queue email.',
            ], 422);
        }

        return response()->json([
            'message' => 'Email accepted and queued for delivery.',
            'log_id' => $log->id,
            'status' => $log->status,
            'attachment_count' => (int) (($log->meta['attachment_count'] ?? 0)),
        ]);
    }
}
