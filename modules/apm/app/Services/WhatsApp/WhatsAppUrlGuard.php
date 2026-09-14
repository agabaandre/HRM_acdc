<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppUrlGuard
{
    /**
     * Validate outbound WhatsApp service URLs to block SSRF to internal/metadata endpoints.
     */
    public static function assertSafe(string $url, bool $nativeWorker = false): string
    {
        $url = rtrim(trim($url), '/');
        if ($url === '') {
            throw new RuntimeException('Service URL is required.');
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('Invalid service URL format.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Only http and https URLs are allowed.');
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '0.0.0.0', '::', '[::]'], true)) {
            $host = '127.0.0.1';
        }

        if ($nativeWorker) {
            if (! in_array($host, ['127.0.0.1', '::1'], true)) {
                throw new RuntimeException('Native worker URL must point to localhost (127.0.0.1) only.');
            }
            if ($scheme !== 'http') {
                throw new RuntimeException('Native worker URL must use http on localhost.');
            }
        } else {
            self::assertHostNotPrivate($host);
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new RuntimeException('Invalid service URL port.');
        }

        $safe = $scheme.'://'.$host;
        if ($port !== null) {
            $safe .= ':'.$port;
        }

        return rtrim($safe, '/');
    }

    private static function assertHostNotPrivate(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Private or reserved IP addresses are not allowed for external bot URLs.');
            }

            return;
        }

        $blocked = ['metadata.google.internal', 'metadata', 'instance-data'];
        if (in_array($host, $blocked, true) || str_ends_with($host, '.internal')) {
            throw new RuntimeException('Internal hostnames are not allowed.');
        }

        $resolved = gethostbynamel($host);
        if (is_array($resolved)) {
            foreach ($resolved as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    Log::warning('WhatsApp URL guard blocked hostname resolving to private IP', ['host' => $host, 'ip' => $ip]);
                    throw new RuntimeException('Hostname resolves to a private or reserved IP address.');
                }
            }
        }
    }
}
