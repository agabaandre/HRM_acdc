<?php

namespace Modules\AdManager\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\PortalTable;

class AdManagerService
{
    /**
     * Staff whose contracts ended and AD accounts should be disabled (CI3 admanager/expired_accounts).
     */
    public function accountsToDisable(string $search = '', int $limit = 100): Collection
    {
        return $this->paginateAccountsToDisable($search, $limit, 1)->getCollection();
    }

    public function paginateAccountsToDisable(
        string $search = '',
        int $perPage = 20,
        ?int $page = null
    ): LengthAwarePaginator {
        $q = $this->baseStaffQuery()
            ->where('sc.status_id', 3)
            ->where(function ($w): void {
                $w->where('s.email_status', 1)->orWhereNull('s.email_status');
            });

        return PortalTable::paginateDistinct(
            $this->applySearch($q, $search),
            's.staff_id',
            $perPage,
            $page
        );
    }

    /**
     * Staff with disabled email accounts (CI3 admanager/report).
     */
    public function disabledAccounts(string $search = '', int $limit = 100): Collection
    {
        return $this->paginateDisabledAccounts($search, $limit, 1)->getCollection();
    }

    public function paginateDisabledAccounts(
        string $search = '',
        int $perPage = 20,
        ?int $page = null
    ): LengthAwarePaginator {
        $q = $this->baseStaffQuery()->where('s.email_status', 0);

        return PortalTable::paginateDistinct(
            $this->applySearch($q, $search),
            's.staff_id',
            $perPage,
            $page
        );
    }

    private function baseStaffQuery()
    {
        $sub = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        return DB::table('staff as s')
            ->joinSub($sub, 'lc', 'lc.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->select(
                's.staff_id',
                's.fname',
                's.lname',
                's.work_email',
                's.email_status',
                's.email_disabled_at',
                's.email_disabled_by',
                'd.division_name',
                'sc.status_id'
            )
            ->orderBy('s.lname');
    }

    private function applySearch($q, string $search)
    {
        if ($search === '') {
            return $q;
        }
        $term = '%'.$search.'%';

        return $q->where(function ($w) use ($term): void {
            $w->where('s.fname', 'like', $term)
                ->orWhere('s.lname', 'like', $term)
                ->orWhere('s.work_email', 'like', $term);
        });
    }
}
