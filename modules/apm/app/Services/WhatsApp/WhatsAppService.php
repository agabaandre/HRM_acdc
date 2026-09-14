<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/**
 * Unified WhatsApp platform API for APM controllers.
 * Native mode: MySQL + Node Baileys worker. External mode: legacy HTTP bot proxy.
 */
class WhatsAppService
{
    public function __construct(
        private readonly WhatsAppConfig $config,
        private readonly WhatsAppRepository $repository,
        private readonly WhatsAppWorkerClient $worker,
        private readonly WhatsAppBotClient $externalClient,
    ) {}

    public function usesNative(): bool
    {
        return $this->config->usesNativeDriver();
    }

    /**
     * @return array<string, mixed>
     */
    public function publicStatus(?string $apiUrl = null): array
    {
        if (! $this->usesNative()) {
            return $this->externalClient->publicStatus($apiUrl);
        }

        if (! $this->worker->isReachable()) {
            return [
                'reachable' => false,
                'connected' => false,
                'registered' => false,
                'error' => 'WhatsApp worker is not running. Start apm/whatsapp-service (default port 8765).',
            ];
        }

        try {
            $workerStatus = $this->worker->status();
            $this->repository->upsertSession([
                'phone' => $workerStatus['phone'] ?? null,
                'connected' => (bool) ($workerStatus['connected'] ?? false),
                'registered' => (bool) ($workerStatus['registered'] ?? false),
                'last_error' => $workerStatus['error'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return [
                'reachable' => true,
                'connected' => false,
                'registered' => false,
                'error' => $e->getMessage(),
            ];
        }

        return $this->repository->publicStatus();
    }

    /**
     * @return array<string, mixed>
     */
    public function adminStats(?string $apiUrl = null, ?string $adminPassword = null): array
    {
        if (! $this->usesNative()) {
            return $this->externalClient->adminStats($apiUrl, $adminPassword);
        }

        return $this->repository->adminStats();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groups(): array
    {
        if (! $this->usesNative()) {
            return $this->externalClient->groups();
        }

        return $this->repository->groups();
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function updateGroup(string $jid, array $fields): array
    {
        if (! $this->usesNative()) {
            return $this->externalClient->updateGroup($jid, $fields);
        }

        $this->repository->updateGroup($jid, $fields);

        return ['ok' => true];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groupMembers(string $jid): array
    {
        if (! $this->usesNative()) {
            return $this->externalClient->groupMembers($jid);
        }

        return $this->repository->groupMembers($jid);
    }

    /**
     * @return array{messages: list<array<string, mixed>>, has_more: bool}
     */
    public function groupMessages(string $jid, ?int $beforeId = null, int $limit = 50): array
    {
        if (! $this->usesNative()) {
            throw new RuntimeException('Group chat history is only available with the native WhatsApp platform.');
        }

        return $this->repository->groupMessages($jid, $beforeId, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendGroupChatMessage(string $jid, string $text = '', ?string $imageBase64 = null, string $imageMime = 'image/jpeg', string $caption = ''): array
    {
        if (! $this->usesNative()) {
            throw new RuntimeException('Sending chat messages requires the native WhatsApp platform.');
        }

        return $this->worker->sendGroupMessage($jid, $text, $imageBase64, $imageMime, $caption);
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPairingCode(string $phone): array
    {
        if (! $this->usesNative()) {
            throw new RuntimeException('Pairing is only available with the native WhatsApp platform.');
        }

        $result = $this->worker->requestPairingCode($phone);
        if (! empty($result['code'])) {
            $this->repository->upsertSession([
                'phone' => preg_replace('/\D+/', '', $phone),
                'pairing_code' => (string) $result['code'],
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function startQrPairing(): array
    {
        if (! $this->usesNative()) {
            throw new RuntimeException('QR pairing is only available with the native WhatsApp platform.');
        }

        return $this->worker->startQrPairing();
    }

    /**
     * @return array<string, mixed>
     */
    public function qrCode(): array
    {
        if (! $this->usesNative()) {
            throw new RuntimeException('QR pairing is only available with the native WhatsApp platform.');
        }

        $result = $this->worker->qrCode();

        if (! empty($result['connected'])) {
            $this->repository->upsertSession([
                'connected' => true,
                'registered' => (bool) ($result['registered'] ?? true),
                'pairing_code' => null,
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncGroups(): array
    {
        if (! $this->usesNative()) {
            throw new RuntimeException('Manual sync is only available with the native WhatsApp platform.');
        }

        $result = $this->worker->triggerSync('all');
        $prunedLocal = $this->repository->pruneNonMatchingGroups();
        $result['pruned'] = max((int) ($result['pruned'] ?? 0), $prunedLocal);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncPrimaryGroup(): array
    {
        if (! $this->usesNative()) {
            throw new RuntimeException('Manual sync is only available with the native WhatsApp platform.');
        }

        if ($this->config->primaryGroupJid() === '') {
            throw new RuntimeException('Primary staff group is not configured. Set it in WhatsApp groups first.');
        }

        return $this->worker->triggerSync('primary');
    }

    public function removeGroupMember(string $groupJid, string $memberJid): array
    {
        if (! $this->usesNative()) {
            throw new RuntimeException('Member removal is only available with the native WhatsApp platform.');
        }

        return $this->worker->removeGroupMember($groupJid, $memberJid);
    }

    /**
     * @param  list<string>  $memberJids
     * @return array<string, mixed>
     */
    public function addGroupMembers(string $groupJid, array $memberJids): array
    {
        if (! $this->usesNative()) {
            throw new RuntimeException('Member add is only available with the native WhatsApp platform.');
        }

        return $this->worker->addGroupMembers($groupJid, $memberJids);
    }

    /**
     * Refresh one group's members from WhatsApp (resolves LID → phone).
     *
     * @return array<string, mixed>
     */
    public function refreshGroupMembers(string $groupJid): array
    {
        if (! $this->usesNative()) {
            return ['ok' => false, 'skipped' => true];
        }

        return $this->worker->syncOneGroup($groupJid);
    }
}
