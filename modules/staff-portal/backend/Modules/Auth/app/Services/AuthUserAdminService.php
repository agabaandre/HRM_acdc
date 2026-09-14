<?php

namespace Modules\Auth\Services;

use App\Support\LegacySchema;
use App\Support\PortalReadCache;
use App\Support\StaffPhoto;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogService;
use Modules\Core\Support\PortalTable;
use Modules\Staff\Services\StaffPortalAccountService;

class AuthUserAdminService
{
    private ?bool $hasAllowEmailLogin = null;

    private ?bool $hasCreatedAt = null;

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly StaffPortalAccountService $accounts,
    ) {
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function paginate(array $filters): array
    {
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        // Joins are 1:1 — avoid COUNT(DISTINCT) which is ~50× slower on this table.
        $base = DB::table('user as u')
            ->leftJoin('staff as s', 's.staff_id', '=', 'u.auth_staff_id')
            ->leftJoin('user_groups as ug', 'ug.id', '=', 'u.role');

        $this->applyListFilters($base, $filters);

        $total = (clone $base)->count('u.user_id');

        $rows = (clone $base)
            ->select($this->listSelectColumns())
            ->orderBy('u.name')
            ->forPage($page, $perPage)
            ->get();

        $paginator = new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            PortalTable::paginationOptions()
        );

        $items = array_map(fn ($row) => $this->presentRow((array) $row), $paginator->items());

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $q
     * @param  array<string, mixed>  $filters
     */
    private function applyListFilters($q, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            if (ctype_digit($search)) {
                $id = (int) $search;
                $q->where(function ($w) use ($id, $search): void {
                    $w->where('u.user_id', $id)
                        ->orWhere('s.staff_id', $id)
                        ->orWhere('s.SAPNO', $search)
                        ->orWhere('u.name', 'like', $search.'%');
                });
            } else {
                $prefix = $search.'%';
                $contains = '%'.$search.'%';
                $q->where(function ($w) use ($prefix, $contains): void {
                    // Prefer prefix matches (index-friendly) then broader contains for email/SAP.
                    $w->where('u.name', 'like', $prefix)
                        ->orWhere('s.fname', 'like', $prefix)
                        ->orWhere('s.lname', 'like', $prefix)
                        ->orWhere('s.work_email', 'like', $contains)
                        ->orWhere('s.SAPNO', 'like', $contains)
                        ->orWhere('u.name', 'like', $contains);
                });
            }
        }

        if (isset($filters['group_id']) && $filters['group_id'] !== '' && $filters['group_id'] !== null) {
            $q->where('u.role', (int) $filters['group_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $q->where('u.status', (int) $filters['status']);
        }
    }

    /**
     * @return list<array{id: int, group_name: string}>
     */
    public function groups(): array
    {
        return PortalReadCache::remember(
            PortalReadCache::key('permissions', 'user_groups', 0),
            static function (): array {
                return DB::table('user_groups')
                    ->orderBy('group_name')
                    ->get(['id', 'group_name'])
                    ->map(fn ($row) => [
                        'id' => (int) $row->id,
                        'group_name' => (string) $row->group_name,
                    ])
                    ->all();
            }
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(int $userId, array $payload): array
    {
        $existing = $this->findRow($userId);
        if (! $existing) {
            throw new \InvalidArgumentException('User not found.');
        }

        $allowed = ['name', 'role', 'status', 'allow_email_login'];
        $data = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            if ($key === 'allow_email_login') {
                $data[$key] = ! empty($payload[$key]) ? 1 : 0;
            } elseif ($key === 'status' || $key === 'role') {
                $data[$key] = (int) $payload[$key];
            } else {
                $data[$key] = trim((string) $payload[$key]);
            }
        }

        if ($data === []) {
            throw new \InvalidArgumentException('No valid fields to update.');
        }

        $before = array_intersect_key($existing, array_flip($allowed));
        DB::table('user')->where('user_id', $userId)->update($data);
        $afterRow = $this->findRow($userId) ?? [];
        $after = array_intersect_key($afterRow, array_flip($allowed));

        $this->audit->logRecordChange('updated', 'user', $userId, $before, $after);

        PortalReadCache::bust(['permissions', 'staff']);

        return $this->presentRow($afterRow);
    }

    public function block(int $userId): string
    {
        return $this->setStatus($userId, 0, 'User has been blocked.');
    }

    public function unblock(int $userId): string
    {
        return $this->setStatus($userId, 1, 'User has been unblocked.');
    }

    public function setAllowEmailLogin(int $userId, bool $allow): string
    {
        $existing = $this->findRow($userId);
        if (! $existing) {
            throw new \InvalidArgumentException('User not found.');
        }
        if (! LegacySchema::hasColumn('user', 'allow_email_login')) {
            throw new \RuntimeException('Column user.allow_email_login is missing.');
        }

        $value = $allow ? 1 : 0;
        $before = ['allow_email_login' => (int) ($existing['allow_email_login'] ?? 0)];
        DB::table('user')->where('user_id', $userId)->update(['allow_email_login' => $value]);
        $this->audit->logRecordChange('updated', 'user', $userId, $before, ['allow_email_login' => $value]);
        PortalReadCache::bust(['permissions', 'staff']);

        return $allow
            ? 'Email/password sign-in enabled for this user.'
            : 'Email/password sign-in disabled for this user.';
    }

    public function resetPassword(int $userId): string
    {
        $existing = $this->findRow($userId);
        if (! $existing) {
            throw new \InvalidArgumentException('User not found.');
        }

        $defaultPassword = (string) (DB::table('setting')->value('default_password') ?: 'africacdc.org');
        $update = [
            'password' => password_hash($defaultPassword, PASSWORD_ARGON2ID),
        ];
        if (LegacySchema::hasColumn('user', 'isChanged')) {
            $update['isChanged'] = 0;
        }

        DB::table('user')->where('user_id', $userId)->update($update);
        $this->audit->logRecordChange('updated', 'user', $userId, ['password' => '[redacted]'], ['password' => '[reset]']);

        return 'Password has been reset successfully.';
    }

    /**
     * @return array{created: int, message: string}
     */
    public function bulkCreate(): array
    {
        $staffIds = DB::table('staff as s')
            ->join('staff_contracts as sc', 'sc.staff_id', '=', 's.staff_id')
            ->where('s.work_email', '!=', '')
            ->whereNotNull('s.work_email')
            ->whereIn('sc.status_id', $this->accounts->eligibleContractStatusIds())
            ->whereNotIn('s.staff_id', function ($q): void {
                $q->select('auth_staff_id')->from('user')->whereNotNull('auth_staff_id');
            })
            ->distinct()
            ->pluck('s.staff_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $before = DB::table('user')->whereIn('auth_staff_id', $staffIds)->count();
        foreach ($staffIds as $staffId) {
            $this->accounts->syncForStaff($staffId);
        }
        $after = DB::table('user')->whereIn('auth_staff_id', $staffIds)->count();
        $created = max(0, $after - $before);
        if ($created > 0) {
            PortalReadCache::bust(['permissions', 'staff']);
        }

        return [
            'created' => $created,
            'message' => $created.' staff account(s) created.',
        ];
    }

    /**
     * @return list<string>
     */
    private function listSelectColumns(): array
    {
        $cols = [
            'u.user_id',
            'u.name',
            'u.status',
            'u.role',
            'u.auth_staff_id',
            'ug.group_name',
            's.work_email',
            's.tel_1',
            's.tel_2',
            's.photo',
            's.title',
            's.fname',
            's.lname',
            's.oname',
            DB::raw('s.SAPNO as sap_number'),
            DB::raw("TRIM(CONCAT(COALESCE(s.fname,''), ' ', COALESCE(s.lname,''))) as staff_name"),
        ];
        if ($this->hasAllowEmailLoginColumn()) {
            $cols[] = 'u.allow_email_login';
        }
        if ($this->hasCreatedAtColumn()) {
            $cols[] = 'u.created_at';
        }

        return $cols;
    }

    private function hasAllowEmailLoginColumn(): bool
    {
        return $this->hasAllowEmailLogin ??= LegacySchema::hasColumn('user', 'allow_email_login');
    }

    private function hasCreatedAtColumn(): bool
    {
        return $this->hasCreatedAt ??= LegacySchema::hasColumn('user', 'created_at');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRow(int $userId): ?array
    {
        $row = DB::table('user as u')
            ->leftJoin('staff as s', 's.staff_id', '=', 'u.auth_staff_id')
            ->leftJoin('user_groups as ug', 'ug.id', '=', 'u.role')
            ->select($this->listSelectColumns())
            ->where('u.user_id', $userId)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function presentRow(array $row): array
    {
        $photo = trim((string) ($row['photo'] ?? ''));
        $row['photo_url'] = StaffPhoto::url($photo !== '' ? $photo : null);
        $row['user_id'] = (int) ($row['user_id'] ?? 0);
        $row['auth_staff_id'] = isset($row['auth_staff_id']) ? (int) $row['auth_staff_id'] : null;
        $row['role'] = isset($row['role']) ? (int) $row['role'] : null;
        $row['status'] = (int) ($row['status'] ?? 0);
        $row['allow_email_login'] = (int) ($row['allow_email_login'] ?? 0);
        $row['status_label'] = $row['status'] === 1 ? 'Active' : 'Inactive';

        return $row;
    }

    private function setStatus(int $userId, int $status, string $message): string
    {
        $existing = $this->findRow($userId);
        if (! $existing) {
            throw new \InvalidArgumentException('User not found.');
        }

        $before = ['status' => (int) ($existing['status'] ?? 0)];
        DB::table('user')->where('user_id', $userId)->update(['status' => $status]);
        $this->audit->logRecordChange('updated', 'user', $userId, $before, ['status' => $status]);
        PortalReadCache::bust(['permissions', 'staff']);

        return $message;
    }
}
