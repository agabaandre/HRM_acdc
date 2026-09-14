<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tab/list partial loads for memo index pages.
 *
 * Index URLs must not return JSON on normal navigation (back button, Livewire navigate).
 * Fragment loads are detected via X-APM-List-Fragment header and/or fragment=1 query (AJAX only).
 */
final class ApmListFragment
{
    public const HEADER = 'X-APM-List-Fragment';

    public static function wants(Request $request): bool
    {
        if (! $request->filled('tab')) {
            return false;
        }

        if (! ($request->ajax() || $request->expectsJson())) {
            return false;
        }

        return $request->header(self::HEADER) === '1'
            || filter_var($request->query('fragment'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function applyToUrl(string $url): string
    {
        $parts = parse_url($url);
        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['fragment'] = '1';

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $built = $scheme.'://'.$host.$port.$path.'?'.http_build_query($query);
        if (! empty($parts['fragment'])) {
            $built .= '#'.$parts['fragment'];
        }

        return $built;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function json(array $data): JsonResponse
    {
        return response()
            ->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Vary', self::HEADER.', X-Requested-With, Accept');
    }
}
