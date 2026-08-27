<?php

namespace Modules\Staff\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffPortalAccountService
{
    /** @return list<int> */
    public function eligibleContractStatusIds(): array
    {
        return [1, 2, 7];
    }

    public function latestContractStatusId(int $staffId): ?int
    {
        $latestContractId = DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->max('staff_contract_id');

        if (! $latestContractId) {
            return null;
        }

        $statusId = DB::table('staff_contracts')
            ->where('staff_contract_id', $latestContractId)
            ->value('status_id');

        return $statusId !== null ? (int) $statusId : null;
    }

    /**
     * @return array{action: string, changed: bool}
     */
    public function syncForStaff(int $staffId): array
    {
        $staff = DB::table('staff')->where('staff_id', $staffId)->first();
        if (! $staff || trim((string) ($staff->work_email ?? '')) === '') {
            return ['action' => 'skipped_no_email', 'changed' => false];
        }

        $statusId = $this->latestContractStatusId($staffId);
        if ($statusId === null) {
            return ['action' => 'skipped_no_contract', 'changed' => false];
        }

        $eligible = in_array($statusId, $this->eligibleContractStatusIds(), true);
        $existing = DB::table('user')->where('auth_staff_id', $staffId)->first();
        $displayName = trim(($staff->fname ?? '').' '.($staff->lname ?? ''));

        if ($eligible) {
            if ($existing) {
                $payload = [];
                if ($displayName !== '' && (string) $existing->name !== $displayName) {
                    $payload['name'] = $displayName;
                }
                if ((int) $existing->status !== 1) {
                    $payload['status'] = 1;
                }
                if ($payload !== []) {
                    $changed = DB::table('user')
                        ->where('auth_staff_id', $staffId)
                        ->update($payload) > 0;

                    return [
                        'action' => isset($payload['status']) ? 'enabled' : 'updated_name',
                        'changed' => $changed,
                    ];
                }

                return ['action' => 'already_active', 'changed' => false];
            }

            $defaultPassword = (string) (DB::table('setting')->value('default_password') ?: 'africacdc.org');
            $payload = [
                'name' => $displayName,
                'status' => 1,
                'password' => password_hash($defaultPassword, PASSWORD_ARGON2ID),
                'role' => 17,
            ];
            if (Schema::hasColumn('user', 'allow_email_login')) {
                $payload['allow_email_login'] = 0;
            }
            $changed = DB::table('user')->updateOrInsert(
                ['auth_staff_id' => $staffId],
                $payload
            );

            return ['action' => 'created', 'changed' => $changed];
        }

        if ($existing && (int) $existing->status !== 0) {
            $changed = DB::table('user')
                ->where('auth_staff_id', $staffId)
                ->update(['status' => 0]) > 0;

            return ['action' => 'disabled', 'changed' => $changed];
        }

        return ['action' => 'already_inactive', 'changed' => false];
    }
}
