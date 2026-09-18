<?php

namespace Modules\Jobs\Services;

use Illuminate\Support\Facades\DB;
use Modules\Staff\Services\StaffPortalAccountService;

class ManageAccountsJobService
{
    public function __construct(private StaffPortalAccountService $accounts) {}

    /**
     * @return array{created:int, enabled:int, disabled:int, renamed:int}
     */
    public function syncAll(): array
    {
        $created = 0;
        $enabled = 0;
        $disabled = 0;
        $renamed = 0;

        $staffIds = DB::table('staff')
            ->whereRaw("TRIM(COALESCE(work_email, '')) != ''")
            ->pluck('staff_id');

        foreach ($staffIds as $staffId) {
            $result = $this->accounts->syncForStaff((int) $staffId);
            if (! ($result['changed'] ?? false)) {
                continue;
            }
            match ($result['action'] ?? '') {
                'created' => $created++,
                'enabled' => $enabled++,
                'disabled' => $disabled++,
                'updated_name' => $renamed++,
                default => null,
            };
        }

        return compact('created', 'enabled', 'disabled', 'renamed');
    }
}
