<?php

namespace App\Console\Commands;

use App\Support\PortalReferenceCache;
use Illuminate\Console\Command;

class WarmPortalCachesCommand extends Command
{
    protected $signature = 'portal:cache-warm {--flush : Forget reference keys before warming}';

    protected $description = 'Warm Redis reference caches (form lookups, leave types)';

    public function handle(): int
    {
        if ($this->option('flush')) {
            PortalReferenceCache::flush();
            $this->info('Flushed reference cache keys.');
        }

        $counts = PortalReferenceCache::warm();
        $this->info(sprintf(
            'Warmed form_lookups groups=%d, leave_types=%d (ttl=%ds, store=%s).',
            $counts['form_lookups'],
            $counts['leave_types'],
            PortalReferenceCache::ttl(),
            (string) config('cache.default')
        ));

        return self::SUCCESS;
    }
}
