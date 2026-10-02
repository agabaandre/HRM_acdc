<?php

namespace Modules\Share\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MailConfigCrypto;
use App\Services\PortalMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Settings\Models\PortalEmailProvider;
use Modules\Settings\Services\EmailProvidersService;
use Throwable;

class ShareMailController extends Controller
{
    public function __construct(
        private PortalMailer $mailer,
        private EmailProvidersService $providers,
        private MailConfigCrypto $crypto,
    ) {}

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required'],
            'subject' => ['required', 'string', 'max:998'],
            'html' => ['required', 'string'],
            'text' => ['nullable', 'string'],
            'cc' => ['sometimes', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['sometimes', 'array'],
            'bcc.*' => ['email'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.content_base64' => ['required_with:attachments', 'string'],
            'attachments.*.content_type' => ['nullable', 'string', 'max:127'],
            'provider_uuid' => ['nullable', 'uuid'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $to = $validated['to'];
        if (is_string($to)) {
            $to = [$to];
        }
        if (! is_array($to) || $to === []) {
            return response()->json([
                'success' => false,
                'error' => 'to must be a non-empty string or array',
            ], 422);
        }

        $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $cacheKey = 'share_mail_idem:'.hash('sha256', $idempotencyKey);
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json($cached);
            }
        }

        $provider = null;
        if (! empty($validated['provider_uuid'])) {
            $provider = PortalEmailProvider::query()
                ->where('uuid', $validated['provider_uuid'])
                ->firstOrFail();
        }

        $attachments = [];
        foreach ($validated['attachments'] ?? [] as $row) {
            $decoded = base64_decode((string) $row['content_base64'], true);
            if ($decoded === false) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid attachment content_base64',
                ], 422);
            }
            $attachments[] = [
                'name' => (string) $row['name'],
                'content' => $decoded,
                'content_type' => (string) ($row['content_type'] ?? 'application/octet-stream'),
            ];
        }

        $resolved = $this->providers->resolveForSend($provider);

        try {
            $this->mailer->send(
                array_values($to),
                (string) $validated['subject'],
                (string) $validated['html'],
                $attachments,
                $resolved['provider']->exists ? $resolved['provider'] : null,
                array_values($validated['bcc'] ?? []),
            );
        } catch (Throwable $e) {
            Log::warning('Share mail send failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 503);
        }

        $payload = [
            'success' => true,
            'driver' => $resolved['driver'],
            'provider_uuid' => $resolved['provider']->uuid ?? null,
        ];

        if ($idempotencyKey !== '') {
            Cache::put(
                'share_mail_idem:'.hash('sha256', $idempotencyKey),
                $payload,
                now()->addMinutes(10)
            );
        }

        return response()->json($payload);
    }

    public function activeConfig(): JsonResponse
    {
        try {
            $resolved = $this->providers->resolveForSend(null);
            $enc = $this->crypto->encrypt([
                'driver' => $resolved['driver'],
                'config' => $resolved['config'],
                'from_address' => $resolved['from_address'],
                'from_name' => $resolved['from_name'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'provider_uuid' => $resolved['provider']->uuid ?? null,
                'driver' => $resolved['driver'],
                'from_address' => $resolved['from_address'],
                'from_name' => $resolved['from_name'],
                'expires_at' => $enc['expires_at'],
                'ciphertext' => $enc['ciphertext'],
                'iv' => $enc['iv'],
                'tag' => $enc['tag'],
            ],
        ]);
    }
}
