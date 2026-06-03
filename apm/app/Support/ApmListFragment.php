<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tab/list partial loads for memo index pages.
 *
 * Index URLs must not return JSON on normal navigation (back button, Livewire navigate).
 * Only requests that send X-APM-List-Fragment: 1 receive the JSON partial.
 */
final class ApmListFragment
{
    public const HEADER = 'X-APM-List-Fragment';

    public static function wants(Request $request): bool
    {
        return $request->ajax()
            && $request->filled('tab')
            && $request->header(self::HEADER) === '1';
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
            ->header('Vary', self::HEADER.', X-Requested-With');
    }
}
