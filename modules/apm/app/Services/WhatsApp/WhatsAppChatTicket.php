<?php

namespace App\Services\WhatsApp;

class WhatsAppChatTicket
{
    public function __construct(private readonly WhatsAppConfig $config) {}

    public function issue(string $groupJid, int $staffId, int $ttlSeconds = 3600): string
    {
        $exp = time() + max(60, $ttlSeconds);
        $payload = $groupJid.'|'.$exp.'|'.$staffId;
        $sig = hash_hmac('sha256', $payload, $this->config->workerToken());

        return rtrim(strtr(base64_encode($payload.'|'.$sig), '+/', '-_'), '=');
    }

    public function websocketUrl(): string
    {
        $configured = trim((string) \App\Models\SystemSetting::get('whatsapp_ws_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        $worker = rtrim($this->config->workerUrl(), '/');
        if (str_starts_with($worker, 'https://')) {
            return 'wss://'.substr($worker, 8).'/chat';
        }

        return 'ws://'.substr($worker, 7).'/chat';
    }
}
