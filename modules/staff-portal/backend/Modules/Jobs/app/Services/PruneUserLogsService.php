<?php

namespace Modules\Jobs\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneUserLogsService
{
    public function pruneGetAccess(): int
    {
        if (! Schema::hasTable('user_logs')) {
            return 0;
        }

        $q = DB::table('user_logs');
        if (Schema::hasColumn('user_logs', 'method')) {
            $q->where('method', 'GET');
        } elseif (Schema::hasColumn('user_logs', 'request_method')) {
            $q->where('request_method', 'GET');
        } elseif (Schema::hasColumn('user_logs', 'access_type')) {
            $q->where('access_type', 'GET');
        } else {
            return 0;
        }

        return $q->delete();
    }
}
