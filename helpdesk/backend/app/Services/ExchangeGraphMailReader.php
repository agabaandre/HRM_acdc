<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Application (client credentials) Microsoft Graph helpers for mailbox intake.
 */
class ExchangeGraphMailReader
{
    public ?string $lastError = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function listUnreadInbox(string $mailboxUpn, int $top = 25): array
    {
        $top = max(1, min(50, $top));
        $mailbox = trim($mailboxUpn);
        $url = sprintf(
            'https://graph.microsoft.com/v1.0/users/%s/mailFolders/inbox/messages',
            rawurlencode($mailbox)
        );

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->get($url, [
                '$filter' => 'isRead eq false',
                '$top' => $top,
                '$orderby' => 'receivedDateTime asc',
                '$select' => 'id,internetMessageId,subject,bodyPreview,body,from,receivedDateTime',
            ]);

        if (! $response->successful()) {
            $this->lastError = $response->body();
            throw new RuntimeException('Graph list unread failed: '.$this->summarizeError($response->json() ?? $response->body()));
        }

        $value = $response->json('value');

        return is_array($value) ? $value : [];
    }

    public function ensureProcessedFolderId(string $mailboxUpn): string
    {
        $mailbox = trim($mailboxUpn);
        $listUrl = sprintf(
            'https://graph.microsoft.com/v1.0/users/%s/mailFolders',
            rawurlencode($mailbox)
        );

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->get($listUrl, [
                '$top' => 100,
                '$select' => 'id,displayName',
            ]);

        if (! $response->successful()) {
            $this->lastError = $response->body();
            throw new RuntimeException('Graph list folders failed: '.$this->summarizeError($response->json() ?? $response->body()));
        }

        foreach ($response->json('value') ?? [] as $folder) {
            if (! is_array($folder)) {
                continue;
            }
            $name = (string) ($folder['displayName'] ?? '');
            if (strcasecmp($name, 'Processed') === 0 && ! empty($folder['id'])) {
                return (string) $folder['id'];
            }
        }

        $create = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($listUrl, [
                'displayName' => 'Processed',
                'isHidden' => false,
            ]);

        if (! $create->successful() || empty($create->json('id'))) {
            $this->lastError = $create->body();
            throw new RuntimeException('Graph create Processed folder failed: '.$this->summarizeError($create->json() ?? $create->body()));
        }

        return (string) $create->json('id');
    }

    public function markReadAndMoveToProcessed(string $mailboxUpn, string $messageId): void
    {
        $mailbox = trim($mailboxUpn);
        $messageId = trim($messageId);
        $folderId = $this->ensureProcessedFolderId($mailbox);

        $patchUrl = sprintf(
            'https://graph.microsoft.com/v1.0/users/%s/messages/%s',
            rawurlencode($mailbox),
            rawurlencode($messageId)
        );

        $patch = Http::withToken($this->accessToken())
            ->acceptJson()
            ->patch($patchUrl, ['isRead' => true]);

        if (! $patch->successful()) {
            $this->lastError = $patch->body();
            throw new RuntimeException('Graph mark read failed: '.$this->summarizeError($patch->json() ?? $patch->body()));
        }

        $moveUrl = sprintf(
            'https://graph.microsoft.com/v1.0/users/%s/messages/%s/move',
            rawurlencode($mailbox),
            rawurlencode($messageId)
        );

        $move = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($moveUrl, ['destinationId' => $folderId]);

        if (! $move->successful()) {
            $this->lastError = $move->body();
            throw new RuntimeException('Graph move to Processed failed: '.$this->summarizeError($move->json() ?? $move->body()));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMessageAttachments(string $mailboxUpn, string $messageId): array
    {
        $url = sprintf(
            'https://graph.microsoft.com/v1.0/users/%s/messages/%s/attachments',
            rawurlencode(trim($mailboxUpn)),
            rawurlencode(trim($messageId))
        );

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30)
            ->get($url, ['$top' => 50]);

        if (! $response->successful()) {
            $this->lastError = $response->body();
            throw new RuntimeException('Graph list attachments failed: '.$this->summarizeError($response->json() ?? $response->body()));
        }

        $value = $response->json('value');

        return is_array($value) ? $value : [];
    }

    public function downloadMessageAttachmentBytes(string $mailboxUpn, string $messageId, string $attachmentId): string
    {
        $url = sprintf(
            'https://graph.microsoft.com/v1.0/users/%s/messages/%s/attachments/%s/$value',
            rawurlencode(trim($mailboxUpn)),
            rawurlencode(trim($messageId)),
            rawurlencode(trim($attachmentId))
        );

        $response = Http::withToken($this->accessToken())->timeout(30)->get($url);

        if (! $response->successful()) {
            $this->lastError = $response->body();
            throw new RuntimeException('Graph download attachment failed: '.$this->summarizeError($response->json() ?? $response->body()));
        }

        return (string) $response->body();
    }

    protected function accessToken(): string
    {
        $tenant = (string) config('exchange-email.tenant_id');
        $clientId = (string) config('exchange-email.client_id');
        $clientSecret = (string) config('exchange-email.client_secret');
        $scope = (string) (config('exchange-email.scope') ?: 'https://graph.microsoft.com/.default');

        if ($tenant === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Exchange Graph is not configured (EXCHANGE_TENANT_ID / CLIENT_ID / CLIENT_SECRET).');
        }

        $tokenUrl = 'https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token';
        $response = Http::asForm()->post($tokenUrl, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => $scope,
            'grant_type' => 'client_credentials',
        ]);

        if (! $response->successful() || empty($response->json('access_token'))) {
            $this->lastError = $response->body();
            throw new RuntimeException('Exchange token request failed: '.$this->summarizeError($response->json() ?? $response->body()));
        }

        return (string) $response->json('access_token');
    }

    protected function summarizeError(mixed $payload): string
    {
        if (is_string($payload)) {
            return mb_substr($payload, 0, 500);
        }
        if (! is_array($payload)) {
            return 'unknown error';
        }
        if (isset($payload['error']['message'])) {
            return (string) $payload['error']['message'];
        }
        if (isset($payload['error_description'])) {
            return (string) $payload['error_description'];
        }

        return mb_substr(json_encode($payload) ?: 'unknown error', 0, 500);
    }
}
