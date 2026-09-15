<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Africa CDC Email Server (notifications.africacdc.org) — client-credentials JWT + /integrations/send.
 *
 * @see https://notifications.africacdc.org/api/documentation
 */
class HttpNotificationsMailClient
{
    /**
     * @param  string|array<int, string>  $to
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     */
    public function send(
        string|array $to,
        string $subject,
        string $htmlBody,
        array $cc = [],
        array $bcc = [],
    ): void {
        $base = rtrim((string) config('mail.http.base_url', env('MAIL_HTTP_BASE_URL', 'https://notifications.africacdc.org/api/v1')), '/');
        $token = $this->bearerToken($base);
        $recipients = is_array($to) ? $to : [$to];

        foreach ($recipients as $recipient) {
            $payload = [
                'to' => $recipient,
                'subject' => $subject,
                'body' => $htmlBody,
                'is_html' => true,
            ];
            if ($cc !== []) {
                $payload['cc'] = array_values($cc);
            }
            if ($bcc !== []) {
                $payload['bcc'] = array_values($bcc);
            }

            $res = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(60)
                ->post($base.'/integrations/send', $payload);

            if (! $res->successful()) {
                throw new RuntimeException(
                    'HTTP notifications send failed ('.$res->status().'): '.$res->body()
                );
            }
        }
    }

    private function bearerToken(string $base): string
    {
        $clientId = (string) config('mail.http.client_id', env('MAIL_HTTP_CLIENT_ID', ''));
        $clientSecret = (string) config('mail.http.client_secret', env('MAIL_HTTP_CLIENT_SECRET', ''));
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException(
                'HTTP mail is not configured. Set MAIL_HTTP_CLIENT_ID and MAIL_HTTP_CLIENT_SECRET.'
            );
        }

        $cacheKey = 'mail_http_jwt:'.hash('sha256', $base.'|'.$clientId);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $res = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($base.'/integrations/auth/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (! $res->successful()) {
            throw new RuntimeException(
                'HTTP notifications auth failed ('.$res->status().'): '.$res->body()
            );
        }

        $token = (string) ($res->json('token') ?? '');
        if ($token === '') {
            throw new RuntimeException('HTTP notifications auth returned no token.');
        }

        $ttl = (int) ($res->json('expires_in') ?? 86400);
        Cache::put($cacheKey, $token, max(60, $ttl - 120));

        return $token;
    }
}
