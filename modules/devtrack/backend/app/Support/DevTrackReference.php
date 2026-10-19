<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DevTrackReference
{
    public static function next(string $prefix, string $table, string $column): string
    {
        $year = now()->year;
        $pattern = $prefix.'-'.$year.'-%';

        $last = DB::table($table)
            ->where($column, 'like', $pattern)
            ->orderByDesc('id')
            ->value($column);

        $sequence = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }
}
