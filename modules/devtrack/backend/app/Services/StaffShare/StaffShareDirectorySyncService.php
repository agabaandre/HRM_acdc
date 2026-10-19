<?php

namespace App\Services\StaffShare;

use App\Models\Staff;
use Illuminate\Support\Str;

class StaffShareDirectorySyncService
{
    public function __construct(private readonly StaffShareClient $client) {}

    /**
     * Pull Staff Share API rows into the local `staff` table.
     *
     * @return array{created: int, updated: int, skipped: int, total: int}
     */
    public function sync(?string $bearerToken = null): array
    {
        if (! $this->client->enabled()) {
            throw new \RuntimeException('Staff Share sync is disabled.');
        }

        $rows = $this->client->getAllStaff($bearerToken);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! isset($row['staff_id']) || ! is_numeric($row['staff_id'])) {
                $skipped++;

                continue;
            }

            $existing = Staff::query()->where('staff_id', (int) $row['staff_id'])->exists();
            $this->upsertFromApiRow($row);
            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function upsertFromApiRow(array $row): Staff
    {
        if (! isset($row['staff_id']) || ! is_numeric($row['staff_id'])) {
            throw new \InvalidArgumentException('Staff row is missing staff_id.');
        }

        $staffId = (int) $row['staff_id'];
        $email = $this->emailFromRow($row);

        $attributes = [
            'staff_id' => $staffId,
            'fname' => $this->stringField($row, ['fname', 'first_name']),
            'lname' => $this->stringField($row, ['lname', 'last_name']),
            'oname' => $this->stringField($row, ['oname', 'other_name', 'middle_name']),
            'work_email' => $email,
            'tel_1' => $this->stringField($row, ['tel_1', 'phone', 'mobile', 'work_phone', 'telephone']),
            'whatsapp' => $this->stringField($row, ['whatsapp']),
            'private_email' => $this->stringField($row, ['private_email']),
            'title' => $this->stringField($row, ['title']),
            'job_name' => $this->stringField($row, ['job_name', 'job_title', 'position']),
            'sap_no' => $this->stringField($row, ['sap_no']),
            'grade' => $this->stringField($row, ['grade']),
            'gender' => $this->stringField($row, ['gender']),
            'nationality' => $this->stringField($row, ['nationality']),
            'contract_type' => $this->stringField($row, ['contract_type']),
            'contracting_institution' => $this->stringField($row, ['contracting_institution']),
            'division_id' => isset($row['division_id']) && is_numeric($row['division_id'])
                ? (int) $row['division_id']
                : null,
            'division_name' => $this->stringField($row, ['division_name', 'division', 'div_name']),
            'directorate_id' => isset($row['directorate_id']) && is_numeric($row['directorate_id'])
                ? (int) $row['directorate_id']
                : null,
            'duty_station_id' => isset($row['duty_station_id']) && is_numeric($row['duty_station_id'])
                ? (int) $row['duty_station_id']
                : null,
            'duty_station_name' => $this->stringField($row, ['duty_station_name', 'duty_station']),
            'supervisor_id' => isset($row['supervisor_id']) && is_numeric($row['supervisor_id'])
                ? (int) $row['supervisor_id']
                : null,
            'associated_divisions' => $this->arrayField($row, 'associated_divisions'),
            'status' => $this->stringField($row, ['status']),
            'active' => $this->activeFromRow($row),
            'photo' => $this->stringField($row, ['photo']),
            'physical_location' => $this->stringField($row, ['physical_location']),
            'signature' => $this->stringField($row, ['signature']),
        ];

        return Staff::query()->updateOrCreate(
            ['staff_id' => $staffId],
            $attributes,
        );
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

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>|null
     */
    private function arrayField(array $row, string $key): ?array
    {
        $value = $row[$key] ?? null;
        if (! is_array($value)) {
            return null;
        }

        return array_values($value);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>|array<string, mixed>|null
     */
    private function arrayOrScalarField(array $row, string $key): mixed
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }

        return ['value' => $value];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function activeFromRow(array $row): bool
    {
        if (array_key_exists('active', $row)) {
            return filter_var($row['active'], FILTER_VALIDATE_BOOLEAN) || (int) $row['active'] === 1;
        }
        if (array_key_exists('is_active', $row)) {
            return filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN) || (int) $row['is_active'] === 1;
        }

        return true;
    }
}
