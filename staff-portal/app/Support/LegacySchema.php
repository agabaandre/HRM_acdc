<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class LegacySchema
{
    public static function has(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
