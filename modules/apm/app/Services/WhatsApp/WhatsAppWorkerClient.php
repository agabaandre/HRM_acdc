<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppWorkerClient
{
    public function __construct(
        private readonly WhatsAppConfig $config,
    ) {}

    public function isReachable(): bool
    {
        try {
            $response = Http::timeout(4)
                ->withHeaders($this->headers())
                ->get($this->baseUrl().'/health');

            return $response->successful();
        } catch (ConnectionException|RuntimeException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders($this->headers())
                ->get($this->baseUrl().'/internal/status');

            if (! $response->successful()) {
                throw new RuntimeException('Worker status failed: HTTP '.$response->status());
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($e));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPairingCode(string $phone): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/internal/pair', [
                    'phoneNumber' => preg_replace('/\D+/', '', $phone),
                ]);

            if (! $response->successful()) {
                $message = $response->json('error') ?? $response->body();
                throw new RuntimeException(is_string($message) ? $message : 'Pairing request failed.');
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($e));
        }
    }

    public function startQrPairing(): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/internal/qr/start');

            if (! $response->successful()) {
                $message = $response->json('error') ?? $response->body();
                throw new RuntimeException(is_string($message) ? $message : 'Could not start QR pairing.');
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($e));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function qrCode(): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/internal/qr/poll');

            if (! $response->successful()) {
                throw new RuntimeException('Could not load QR code.');
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($e));
        }
    }

    /**
     * @param  'all'|'primary'  $scope
     * @return array<string, mixed>
     */
    public function triggerSync(string $scope = 'all'): array
    {
        try {
            $response = Http::timeout($scope === 'primary' ? 60 : 120)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/internal/sync', [
                    'scope' => $scope,
                ]);

            if (! $response->successful()) {
                $message = $response->json('error') ?? $response->body();
                throw new RuntimeException(is_string($message) ? $message : 'Group sync failed.');
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($e));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function removeGroupMember(string $groupJid, string $memberJid): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/internal/groups/participants/remove', [
                    'groupJid' => $groupJid,
                    'memberJid' => $memberJid,
                ]);

            if (! $response->successful()) {
                $message = $response->json('error') ?? $response->body();
                throw new RuntimeException(is_string($message) ? $message : 'Could not remove group member.');
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($e));
        }
    }

    /**
     * @param  list<string>  $memberJids
     * @return array<string, mixed>
     */
    public function addGroupMembers(string $groupJid, array $memberJids): array
    {
        try {
            $response = Http::timeout(120)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/internal/groups/participants/add', [
                    'groupJid' => $groupJid,
                    'memberJids' => array_values($memberJids),
                ]);

            if (! $response->successful()) {
                $message = $response->json('error') ?? $response->body();
                throw new RuntimeException(is_string($message) ? $message : 'Could not add group members.');
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($e));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function sendGroupMessage(string $groupJid, string $text = '', ?string $imageBase64 = null, string $imageMime = 'image/jpeg', string $caption = ''): array
    {
        try {
            $payload = [
                'groupJid' => $groupJid,
                'text' => $text,
                'caption' => $caption,
            ];
            if ($imageBase64 !== null && $imageBase64 !== '') {
                $payload['imageBase64'] = $imageBase64;
                $payload['imageMime'] = $imageMime;
            }

            $response = Http::timeout(60)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/internal/groups/messages/send', $payload);

            if (! $response->successful()) {
                $message = $response->json('error') ?? $response->body();
                throw new RuntimeException(is_string($message) ? $message : 'Could not send WhatsApp message.');
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($e));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function syncOneGroup(string $groupJid): array
    {
        try {
            $response = Http::timeout(180)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/internal/sync/group', [
                    'groupJid' => $groupJid,
                ]);

            if (! $response->successful()) {
                $message = $response->json('error') ?? $response->body();
                throw new RuntimeException(is_string($message) ? $message : 'Could not refresh group members.');
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($e));
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $token = $this->config->workerToken();
        if ($token === '') {
            throw new RuntimeException('WhatsApp worker token is not configured.');
        }

        return ['X-Worker-Token' => $token];
    }

    private function baseUrl(): string
    {
        return $this->config->workerUrl();
    }

    private function connectionHint(ConnectionException $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, 'Failed to connect') || str_contains($message, 'Connection refused')) {
            return 'Cannot reach the WhatsApp worker. Ensure apm/whatsapp-service is running on localhost.';
        }

        return 'WhatsApp worker request failed.';
    }
}
