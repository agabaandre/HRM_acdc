<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseMessagingService
{
    protected ?array $credentials = null;

    protected string $projectId;

    protected string $credentialsPath;

    public function __construct(?string $projectId = null, ?string $credentialsPath = null)
    {
        $this->projectId = $projectId ?? config('services.firebase.project_id', '');
        $this->credentialsPath = $credentialsPath ?? config('services.firebase.credentials', storage_path('app/firebase-credentials.json'));
    }

    /**
     * Check if FCM is configured (project_id and credentials file present).
     */
    public function isConfigured(): bool
    {
        if (empty($this->projectId)) {
            return false;
        }
        $path = $this->credentialsPath;
        return is_string($path) && file_exists($path);
    }

    /**
     * Send a data and/or notification message to a single FCM device token.
     *
     * @param  string  $token  FCM device token
     * @param  string  $title  Notification title (optional for data-only)
     * @param  string  $body  Notification body (optional for data-only)
     * @param  array<string, string>  $data  Optional key-value data payload
     * @return bool True if sent successfully
     */
    public function sendToToken(string $token, string $title = '', string $body = '', array $data = []): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Firebase FCM not configured; skipping send.');
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }

        $message = [
            'message' => [
                'token' => $token,
            ],
        ];
        if ($title !== '' || $body !== '') {
            $message['message']['notification'] = [
                'title' => $title,
                'body' => $body,
            ];
        }
        if (!empty($data)) {
            $message['message']['data'] = array_map('strval', $data);
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($url, $message);

        if (!$response->successful()) {
            Log::warning('FCM send failed', [
                'token_preview' => substr($token, 0, 20) . '...',
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Send "pending approvals" notification to a device token.
     */
    public function sendPendingApprovalsNotification(string $token, int $count, string $deepLink = ''): bool
    {
        $title = 'Pending Approvals';
        $body = $count === 1
            ? 'You have 1 item waiting for your approval.'
            : "You have {$count} items waiting for your approval.";
        $data = [
            'type' => 'pending_approvals',
            'count' => (string) $count,
        ];
        if ($deepLink !== '') {
            $data['url'] = $deepLink;
        }
        return $this->sendToToken($token, $title, $body, $data);
    }

    /**
     * Get OAuth2 access token for FCM (cached).
     */
    protected function getAccessToken(): ?string
    {
        $cacheKey = 'firebase_fcm_access_token';
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $credentials = $this->loadCredentials();
        if (!$credentials) {
            return null;
        }

        $jwt = $this->createJwt($credentials);
        if (!$jwt) {
            return null;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            Log::warning('Firebase OAuth2 token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);
        if ($accessToken && $expiresIn > 0) {
            Cache::put($cacheKey, $accessToken, $expiresIn - 60);
        }

        return $accessToken;
    }

    protected function loadCredentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }
        $path = $this->credentialsPath;
        if (!is_string($path) || !file_exists($path)) {
            Log::warning('Firebase credentials file not found: ' . $path);
            return null;
        }
        $json = file_get_contents($path);
        $this->credentials = json_decode($json, true);
        if (!is_array($this->credentials)) {
            Log::warning('Firebase credentials JSON invalid');
            return null;
        }
        return $this->credentials;
    }

    /**
     * Create a JWT for Google OAuth2 (service account).
     */
    protected function createJwt(array $credentials): ?string
    {
        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;
        if (!$clientEmail || !$privateKey) {
            Log::warning('Firebase credentials missing client_email or private_key');
            return null;
        }

        $now = time();
        $payload = [
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ];
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));
        $signatureInput = $headerB64 . '.' . $payloadB64;

        $signature = '';
        $key = openssl_pkey_get_private($privateKey);
        if (!$key) {
            Log::warning('Firebase private key invalid');
            return null;
        }
        $ok = openssl_sign($signatureInput, $signature, $key, OPENSSL_ALGO_SHA256);
        openssl_pkey_free($key);
        if (!$ok) {
            return null;
        }

        $signatureB64 = $this->base64UrlEncode($signature);
        return $signatureInput . '.' . $signatureB64;
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
