<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Prevents duplicate tickets when the client retries POST after a transient network error.
 */
final class TicketCreateIdempotency
{
    private const TTL_SECONDS = 86400;

    public static function normalizeClientKey(?string $header): ?string
    {
        if (! is_string($header)) {
            return null;
        }
        $header = trim($header);
        if ($header === '' || strlen($header) > 128) {
            return null;
        }

        return $header;
    }

    public static function findTicketId(int $userId, string $clientKey): ?int
    {
        $cached = Cache::get(self::cacheKey($userId, $clientKey));
        if (! is_numeric($cached)) {
            return null;
        }

        $id = (int) $cached;

        return $id > 0 ? $id : null;
    }

    public static function remember(int $userId, string $clientKey, int $ticketId): void
    {
        if ($ticketId < 1) {
            return;
        }

        Cache::put(self::cacheKey($userId, $clientKey), $ticketId, self::TTL_SECONDS);
    }

    private static function cacheKey(int $userId, string $clientKey): string
    {
        return 'helpdesk_ticket_idem:'.$userId.':'.hash('sha256', $clientKey);
    }
}
