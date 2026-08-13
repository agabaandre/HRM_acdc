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

    /**
     * Mark staff AD/email account as disabled (CI3 admanager/mark_disabled).
     *
     * @return array{staff_id: int, email_status: int, work_email: ?string, fname: ?string, lname: ?string}
     */
    public function markDisabled(int $staffId, int $actorStaffId): array
    {
        return $this->setEmailStatus($staffId, $actorStaffId, 0);
    }

    /**
     * Mark staff AD/email account as enabled (CI3 admanager/mark_enabled).
     *
     * @return array{staff_id: int, email_status: int, work_email: ?string, fname: ?string, lname: ?string}
     */
    public function markEnabled(int $staffId, int $actorStaffId): array
    {
        return $this->setEmailStatus($staffId, $actorStaffId, 1);
    }

    /**
     * @return array{staff_id: int, email_status: int, work_email: ?string, fname: ?string, lname: ?string}
     */
    private function setEmailStatus(int $staffId, int $actorStaffId, int $status): array
    {
        $staff = DB::table('staff')
            ->where('staff_id', $staffId)
            ->first(['staff_id', 'fname', 'lname', 'work_email', 'email_status']);

        if (! $staff) {
            abort(404, 'Staff record not found.');
        }

        $current = $staff->email_status;
        if ($current !== null && (int) $current === $status) {
            abort(422, $status === 0
                ? 'This account is already marked as disabled.'
                : 'This account is already marked as enabled.');
        }

        DB::table('staff')->where('staff_id', $staffId)->update([
            'email_status' => $status,
            'email_disabled_at' => now()->format('Y-m-d H:i:s'),
            'email_disabled_by' => $actorStaffId > 0 ? $actorStaffId : null,
        ]);

        return [
            'staff_id' => $staffId,
            'email_status' => $status,
            'work_email' => $staff->work_email !== null ? (string) $staff->work_email : null,
            'fname' => $staff->fname !== null ? (string) $staff->fname : null,
            'lname' => $staff->lname !== null ? (string) $staff->lname : null,
        ];
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
