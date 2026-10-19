<?php

namespace App\Services\StaffShare;

use App\Enums\UserRegistrationSource;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Str;

class StaffShareUserSyncService
{
    public function __construct(
        private readonly StaffShareClient $client,
        private readonly StaffShareDirectorySyncService $directorySync,
    ) {}

    /**
     * Authenticate against Staff Share SSO, persist staff row, upsert local user.
     */
    public function authenticateAndUpsert(string $username, string $password): User
    {
        if (! $this->client->enabled()) {
            throw new StaffShareAuthException('Staff SSO is disabled.');
        }

        // Preferred: POST /share/login { username, password } → JWT (+ optional user)
        // @see https://cbp.africacdc.org/staff/backend/share/docs
        $auth = $this->client->authenticate($username, $password);
        $bearer = $auth['access_token'];
        $email = Str::lower(trim($username));

        $staffRow = null;
        if (is_array($auth['user'] ?? null)) {
            $staffRow = $this->staffRowFromLoginUser($auth['user'], $email);
        }

        $staffRow ??= $this->findStaffRowByEmail($bearer, $email, $username, $password);
        $staffRow ??= [
            'staff_id' => isset($auth['user']['staff_id']) ? (int) $auth['user']['staff_id'] : null,
            'work_email' => $email,
            'fname' => Str::before($email, '@') ?: 'Staff',
            'lname' => '',
        ];

        if (isset($staffRow['staff_id']) && is_numeric($staffRow['staff_id'])) {
            $this->directorySync->upsertFromApiRow($staffRow);
        }

        // First successful SSO with a bearer: backfill the full `staff` directory.
        if (Staff::query()->count() <= 1) {
            try {
                $this->directorySync->sync($bearer);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->requireExistingUserFromStaffRow($staffRow);
    }

    /**
     * Map TokenResponse.user into a StaffRow-compatible shape.
     *
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    private function staffRowFromLoginUser(array $user, string $fallbackEmail): array
    {
        $email = Str::lower(trim((string) ($user['email'] ?? $user['work_email'] ?? $fallbackEmail)));
        $name = trim((string) ($user['name'] ?? ''));
        $parts = $name !== '' ? preg_split('/\s+/', $name, 2) : ['', ''];

        return [
            'staff_id' => isset($user['staff_id']) ? (int) $user['staff_id'] : null,
            'work_email' => $email !== '' ? $email : $fallbackEmail,
            'fname' => (string) ($parts[0] ?? Str::before($fallbackEmail, '@')),
            'lname' => (string) ($parts[1] ?? ''),
            'name' => $name !== '' ? $name : null,
            'division_name' => $this->stringField($user, ['division_name', 'division']),
            'directorate_name' => $this->stringField($user, ['directorate_name', 'directorate']),
            'phone' => $this->stringField($user, ['phone', 'mobile', 'work_phone']),
        ];
    }

    /**
     * Sync Staff Share API into `staff` (and mirror into users when email is present).
     *
     * @return array{staff: array{created: int, updated: int, skipped: int, total: int}, users: array{created: int, updated: int, skipped: int}}
     */
    public function syncDirectory(?string $bearerToken = null): array
    {
        if (! $this->client->enabled()) {
            throw new \RuntimeException('Staff Share sync is disabled.');
        }

        $staffStats = $this->directorySync->sync($bearerToken);

        $usersUpdated = 0;
        $usersSkipped = 0;
        Staff::query()->orderBy('id')->chunkById(100, function ($chunk) use (&$usersUpdated, &$usersSkipped): void {
            foreach ($chunk as $staff) {
                if (! $staff->work_email) {
                    $usersSkipped++;
                    continue;
                }
                $user = $this->findExistingUserForStaff($staff->work_email, $staff->staff_id);
                if ($user === null) {
                    $usersSkipped++;
                    continue;
                }
                $this->syncExistingUserFromStaffModel($staff, $user);
                $usersUpdated++;
            }
        });

        return [
            'staff' => $staffStats,
            'users' => [
                'created' => 0,
                'updated' => $usersUpdated,
                'skipped' => $usersSkipped,
            ],
        ];
    }

    /**
     * Login path: staff email must already map to a users-table account.
     *
     * @param  array<string, mixed>  $row
     */
    public function requireExistingUserFromStaffRow(array $row): User
    {
        $email = $this->emailFromRow($row);
        if ($email === null) {
            throw new StaffShareAuthException('Staff SSO did not return a work email.');
        }

        $staffId = isset($row['staff_id']) ? (int) $row['staff_id'] : null;
        $user = $this->findExistingUserForStaff($email, $staffId);

        if ($user === null) {
            throw new StaffShareAuthException(
                'No platform account exists for this staff member. Ask an administrator to add you in Users before signing in.',
            );
        }

        return $this->syncExistingUserFromStaffRow($row, $user);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function upsertUserFromStaffRow(array $row): User
    {
        // Keep method name for callers, but never auto-create platform users.
        return $this->requireExistingUserFromStaffRow($row);
    }

    public function upsertUserFromStaffModel(Staff $staff): User
    {
        $user = $this->findExistingUserForStaff(
            $staff->work_email ? Str::lower(trim($staff->work_email)) : null,
            $staff->staff_id,
        );

        if ($user === null) {
            throw new StaffShareAuthException(
                'No platform account exists for this staff member. Ask an administrator to add you in Users before signing in.',
            );
        }

        return $this->syncExistingUserFromStaffModel($staff, $user);
    }

    private function findExistingUserForStaff(?string $email, ?int $staffId): ?User
    {
        return User::query()
            ->where(function ($q) use ($email, $staffId) {
                if ($email) {
                    $q->whereRaw('LOWER(email) = ?', [Str::lower($email)]);
                }
                if ($staffId) {
                    $q->orWhere('staff_id', $staffId);
                }
            })
            ->when(! $email && ! $staffId, fn ($q) => $q->whereRaw('1 = 0'))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncExistingUserFromStaffRow(array $row, User $user): User
    {
        $email = $this->emailFromRow($row);
        $staffId = isset($row['staff_id']) ? (int) $row['staff_id'] : null;
        $name = $this->nameFromRow($row);
        $division = $this->stringField($row, ['division_name', 'division', 'div_name']);
        $directorate = $this->stringField($row, ['directorate_name', 'directorate', 'dir_name']);
        $phone = $this->stringField($row, ['phone', 'mobile', 'work_phone', 'telephone']);

        $attributes = [
            'name' => $name ?: $user->name,
            'email' => $email ?: $user->email,
            'phone' => $phone ?: $user->phone,
            'organisation' => $division ?: ($user->organisation ?: 'Africa CDC'),
            'division' => $division ?: $user->division,
            'directorate' => $directorate ?: $user->directorate,
            'staff_id' => $staffId ?: $user->staff_id,
            'staff_synced_at' => now(),
        ];

        if ($user->registration_source === null || $user->registration_source === UserRegistrationSource::Public) {
            $attributes['registration_source'] = UserRegistrationSource::StaffSso;
        }

        $user->fill($attributes);
        $user->save();

        return $user->fresh();
    }

    private function syncExistingUserFromStaffModel(Staff $staff, User $user): User
    {
        return $this->syncExistingUserFromStaffRow([
            'staff_id' => $staff->staff_id,
            'fname' => $staff->fname,
            'lname' => $staff->lname,
            'work_email' => $staff->work_email,
            'phone' => $staff->phone(),
            'tel_1' => $staff->tel_1,
            'division_name' => $staff->division_name,
            'name' => $staff->fullName(),
        ], $user);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findStaffRowByEmail(
        string $bearer,
        string $email,
        ?string $basicUsername = null,
        ?string $basicPassword = null,
    ): ?array {
        $pageSize = max(50, (int) config('services.staff_share.page_size', 200));
        $start = 0;

        do {
            try {
                $chunk = $this->client->getCurrentStaff($bearer, $pageSize, $start);
            } catch (StaffShareAuthException $e) {
                if ($basicUsername === null || $basicPassword === null) {
                    throw $e;
                }
                // Retry this page with HTTP Basic if the Bearer is rejected.
                $chunk = $this->client->getCurrentStaff(
                    null,
                    $pageSize,
                    $start,
                    $basicUsername,
                    $basicPassword,
                );
            }

            foreach ($chunk as $row) {
                $rowEmail = $this->emailFromRow($row);
                if ($rowEmail !== null && Str::lower($rowEmail) === $email) {
                    return $row;
                }
            }
            if ($chunk === [] || count($chunk) < $pageSize) {
                break;
            }
            $start += $pageSize;
        } while (true);

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function emailFromRow(array $row): ?string
    {
        foreach (['work_email', 'email', 'username'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return Str::lower($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function nameFromRow(array $row): string
    {
        $full = trim((string) ($row['name'] ?? ''));
        if ($full !== '') {
            return $full;
        }

        $name = trim(implode(' ', array_filter([
            (string) ($row['fname'] ?? $row['first_name'] ?? ''),
            (string) ($row['lname'] ?? $row['last_name'] ?? ''),
        ])));

        if ($name !== '') {
            return $name;
        }

        $email = $this->emailFromRow($row) ?? 'staff';

        return Str::before($email, '@') ?: 'Africa CDC Staff';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function stringField(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '' && $value !== '—') {
                return $value;
            }
        }

        return null;
    }
}
