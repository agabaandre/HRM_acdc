<?php

namespace App\Support;

use Illuminate\Support\Str;

final class InformationSystemLanguageNormalizer
{
    /** @var array<string, string> lowercase alias → display name */
    private const ALIASES = [
        'javascript' => 'JavaScript',
        'javasript' => 'JavaScript',
        'javacript' => 'JavaScript',
        'js' => 'JavaScript',
        'typescript' => 'TypeScript',
        'mysql' => 'MySQL',
        'mysqli' => 'MySQL',
        'mysqldb' => 'MySQL',
        'mysql rdmbms' => 'MySQL',
        'mysq l rdmbms' => 'MySQL',
        'sql' => 'SQL',
        'postgresql' => 'PostgreSQL',
        'postgres' => 'PostgreSQL',
        'php' => 'PHP',
        'python' => 'Python',
        'java' => 'Java',
        'xml' => 'XML',
        'html' => 'HTML',
        'css' => 'CSS',
        'laravel' => 'Laravel',
        'codeigniter' => 'CodeIgniter',
        'codeigniter3' => 'CodeIgniter 3',
        'codeigniter 3' => 'CodeIgniter 3',
        'react' => 'React',
        'vue' => 'Vue',
        'nodejs' => 'Node.js',
        'node js' => 'Node.js',
        'node' => 'Node.js',
        'shell script' => 'Shell',
        'shell' => 'Shell',
        'bash' => 'Shell',
        'dhis2' => 'DHIS2',
    ];

    /**
     * @return list<string> unique display names
     */
    public static function normalizeList(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[,\/|;]+|\band\b/i', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $name = self::normalizeToken($part);
            if ($name !== null) {
                $out[$name] = true;
            }
        }

        return array_keys($out);
    }

    public static function normalizeToken(string $token): ?string
    {
        $token = trim($token);
        $token = preg_replace('/\s+/', ' ', $token) ?? $token;
        if ($token === '' || strcasecmp($token, 'for front-end') === 0) {
            return null;
        }

        // Strip trailing junk like "for front-end"
        $token = preg_replace('/\s+for\s+front-?end$/i', '', $token) ?? $token;
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $key = strtolower($token);
        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        // Compact form without spaces for alias lookup
        $compact = str_replace(' ', '', $key);
        if (isset(self::ALIASES[$compact])) {
            return self::ALIASES[$compact];
        }

        // Title-case fallback
        return Str::title($token);
    }

    public static function slugFor(string $displayName): string
    {
        return Str::slug($displayName);
    }
}
