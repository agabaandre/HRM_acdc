<?php

namespace Staff\Shared;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Portal-first hybrid mail dispatcher for CBP modules.
 *
 * Modes (STAFF_MAIL_DISPATCH):
 * - auto (default): POST Share /share/mail/send, then active-config + local on failure
 * - portal: Share send only
 * - local: active-config decrypt + local sender (or callable) only
 */
final class StaffPortalMailClient
{
    /** @var array{driver: string, config: array<string, mixed>, from_address: string, from_name: string}|null */
    private ?array $cachedConfig = null;

    private ?string $cachedConfigExpiresAt = null;

    /**
     * @param  (callable(string|array, string, string, array): void)|null  $localSender
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token = null,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly string $dispatch = 'auto',
        private readonly ?string $configKey = null,
        private $localSender = null,
        private readonly int $timeoutSeconds = 30,
    ) {}

    /**
     * @param  string|list<string>  $to
     * @param  array{
     *   cc?: list<string>,
     *   bcc?: list<string>,
     *   attachments?: list<array{name: string, content?: string, content_base64?: string, content_type?: string}>,
     *   provider_uuid?: string|null,
     *   idempotency_key?: string|null,
     *   text?: string|null
     * }  $options
     */
    public function send(string|array $to, string $subject, string $html, array $options = []): void
    {
        $mode = strtolower(trim($this->dispatch ?: 'auto'));
        if (! in_array($mode, ['auto', 'portal', 'local'], true)) {
            $mode = 'auto';
        }

        if ($mode === 'local') {
            $this->sendLocal($to, $subject, $html, $options);

            return;
        }

        try {
            $this->sendViaPortal($to, $subject, $html, $options);

            return;
        } catch (Throwable $e) {
            if ($mode === 'portal') {
                throw $e;
            }
            $this->sendLocal($to, $subject, $html, $options);
        }
    }

    /**
     * @param  string|list<string>  $to
     * @param  array<string, mixed>  $options
     */
    private function sendViaPortal(string|array $to, string $subject, string $html, array $options): void
    {
        $url = $this->endpoint('/share/mail/send');
        $payload = [
            'to' => is_array($to) ? array_values($to) : $to,
            'subject' => $subject,
            'html' => $html,
        ];
        foreach (['cc', 'bcc', 'provider_uuid', 'idempotency_key', 'text'] as $key) {
            if (array_key_exists($key, $options) && $options[$key] !== null && $options[$key] !== []) {
                $payload[$key] = $options[$key];
            }
        }
        if (! empty($options['attachments']) && is_array($options['attachments'])) {
            $payload['attachments'] = array_map(function (array $row): array {
                $b64 = $row['content_base64'] ?? null;
                if ($b64 === null && isset($row['content'])) {
                    $b64 = base64_encode((string) $row['content']);
                }

                return [
                    'name' => (string) ($row['name'] ?? 'attachment'),
                    'content_base64' => (string) $b64,
                    'content_type' => (string) ($row['content_type'] ?? 'application/octet-stream'),
                ];
            }, $options['attachments']);
        }

        $response = $this->http()->asJson()->post($url, $payload);
        if (! $response->successful()) {
            throw new RuntimeException(
                'Portal mail send failed ('.$response->status().'): '.$response->body()
            );
        }
        $json = $response->json();
        if (! is_array($json) || ($json['success'] ?? false) !== true) {
            throw new RuntimeException(
                'Portal mail send rejected: '.(is_array($json) ? ($json['error'] ?? $response->body()) : $response->body())
            );
        }
    }

    /**
     * @param  string|list<string>  $to
     * @param  array<string, mixed>  $options
     */
    private function sendLocal(string|array $to, string $subject, string $html, array $options): void
    {
        if ($this->localSender !== null) {
            ($this->localSender)($to, $subject, $html, $options);

            return;
        }

        $resolved = $this->activeConfig();
        $driver = strtolower((string) ($resolved['driver'] ?? ''));
        $config = is_array($resolved['config'] ?? null) ? $resolved['config'] : [];

        if ($driver === 'http') {
            $this->sendHttpLocal($to, $subject, $html, $config, $options);

            return;
        }

        if ($driver === 'log') {
            error_log('[StaffPortalMailClient] log transport to='.json_encode($to).' subject='.$subject);

            return;
        }

        throw new RuntimeException(
            "Local mail driver \"{$driver}\" requires a localSender callback (Exchange/SMTP)."
        );
    }

    /**
     * @return array{driver: string, config: array<string, mixed>, from_address: string, from_name: string}
     */
    public function activeConfig(bool $forceRefresh = false): array
    {
        if (
            ! $forceRefresh
            && $this->cachedConfig !== null
            && $this->cachedConfigExpiresAt !== null
            && strtotime($this->cachedConfigExpiresAt) > time() + 30
        ) {
            return $this->cachedConfig;
        }

        $response = $this->http()->acceptJson()->get($this->endpoint('/share/mail/active-config'));
        if (! $response->successful()) {
            throw new RuntimeException(
                'Portal active-config failed ('.$response->status().'): '.$response->body()
            );
        }
        $json = $response->json();
        if (! is_array($json) || ($json['success'] ?? false) !== true || ! is_array($json['data'] ?? null)) {
            throw new RuntimeException('Portal active-config returned invalid payload');
        }
        $data = $json['data'];
        $crypto = new MailConfigCrypto($this->configKey);
        $decoded = $crypto->decrypt(
            (string) ($data['ciphertext'] ?? ''),
            (string) ($data['iv'] ?? ''),
            (string) ($data['tag'] ?? '')
        );

        $this->cachedConfig = [
            'driver' => (string) ($decoded['driver'] ?? $data['driver'] ?? ''),
            'config' => is_array($decoded['config'] ?? null) ? $decoded['config'] : [],
            'from_address' => (string) ($decoded['from_address'] ?? $data['from_address'] ?? ''),
            'from_name' => (string) ($decoded['from_name'] ?? $data['from_name'] ?? ''),
        ];
        $this->cachedConfigExpiresAt = (string) ($data['expires_at'] ?? gmdate('c', time() + 3600));

        return $this->cachedConfig;
    }

    /**
     * @param  string|list<string>  $to
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $options
     */
    private function sendHttpLocal(string|array $to, string $subject, string $html, array $config, array $options): void
    {
        $base = rtrim((string) ($config['base_url'] ?? 'https://notifications.africacdc.org/api/v1'), '/');
        $clientId = (string) ($config['client_id'] ?? '');
        $clientSecret = (string) ($config['client_secret'] ?? '');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('HTTP mail config missing client_id/client_secret');
        }

        $tokenRes = Http::acceptJson()->asJson()->timeout($this->timeoutSeconds)
            ->post($base.'/integrations/auth/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);
        if (! $tokenRes->successful()) {
            throw new RuntimeException('HTTP mail auth failed: '.$tokenRes->body());
        }
        $token = (string) ($tokenRes->json('access_token') ?? $tokenRes->json('token') ?? '');
        if ($token === '') {
            throw new RuntimeException('HTTP mail auth returned empty token');
        }

        $recipients = is_array($to) ? $to : [$to];
        $cc = array_values($options['cc'] ?? []);
        $bcc = array_values($options['bcc'] ?? []);
        foreach ($recipients as $recipient) {
            $payload = [
                'to' => $recipient,
                'subject' => $subject,
                'body' => $html,
                'is_html' => true,
            ];
            if ($cc !== []) {
                $payload['cc'] = $cc;
            }
            if ($bcc !== []) {
                $payload['bcc'] = $bcc;
            }
            $res = Http::withToken($token)->acceptJson()->asJson()->timeout($this->timeoutSeconds)
                ->post($base.'/integrations/send', $payload);
            if (! $res->successful()) {
                throw new RuntimeException('HTTP local send failed: '.$res->body());
            }
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function http(): PendingRequest
    {
        $request = Http::acceptJson()->timeout($this->timeoutSeconds);
        $token = trim((string) ($this->token ?? ''));
        if ($token !== '') {
            $request = $request->withToken($token);
        }
        $user = trim((string) ($this->username ?? ''));
        $pass = (string) ($this->password ?? '');
        if ($user !== '') {
            $request = $request->withBasicAuth($user, $pass);
        }

        return $request;
    }
}
