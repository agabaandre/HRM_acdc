<?php

namespace Modules\Staff\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class StaffCreateService
{
    public function __construct(
        private readonly StaffContractService $contracts,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{staff_id: int, contract_id: int}
     */
    public function create(array $data, ?UploadedFile $contractPdf = null, ?UploadedFile $passport = null): array
    {
        return DB::transaction(function () use ($data, $contractPdf, $passport): array {
            $staffPayload = $this->staffPayload($data);
            $staffId = (int) DB::table('staff')->insertGetId($staffPayload);
            if ($staffId < 1) {
                throw new RuntimeException('Could not create staff record.');
            }

            $audit = app(StaffAuditTrailService::class);
            $audit->logChange('staff_create', 'staff', $staffId, $staffId, [], $staffPayload);

            if ($passport !== null) {
                app(StaffProfileService::class)->storePassport($staffId, $passport);
            }

            $contractId = $this->contracts->create($staffId, $this->contractPayload($data), $contractPdf);
            if (! $contractId) {
                throw new RuntimeException('Could not create staff contract.');
            }

            if (
                ! empty($data['pay'])
                && is_array($data['pay'])
                && Schema::hasTable('payroll_staff_pay')
                && class_exists(\Modules\Payroll\Services\StaffPayService::class)
            ) {
                $pay = $data['pay'];
                $payService = app(\Modules\Payroll\Services\StaffPayService::class);
                $payService->upsert($staffId, $pay, $contractId);
                foreach ((array) ($pay['wage_items'] ?? []) as $item) {
                    if (empty($item['wage_type_id'])) {
                        continue;
                    }
                    $payService->createWageItem($staffId, array_merge($item, [
                        'staff_contract_id' => $contractId,
                    ]));
                }
            }

            return [
                'staff_id' => $staffId,
                'contract_id' => $contractId,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function staffPayload(array $data): array
    {
        // Legacy `staff` columns are NOT NULL — store blanks as empty strings (CI3 parity).
        $payload = [
            'SAPNO' => $this->blankToEmpty($data['SAPNO'] ?? null),
            'title' => trim((string) $data['title']),
            'fname' => trim((string) $data['fname']),
            'lname' => trim((string) $data['lname']),
            'oname' => $this->blankToEmpty($data['oname'] ?? null),
            'date_of_birth' => $data['date_of_birth'],
            'gender' => trim((string) $data['gender']),
            'nationality_id' => (int) $data['nationality_id'],
            'initiation_date' => $data['initiation_date'],
            'tel_1' => $this->blankToEmpty($data['tel_1'] ?? null),
            'tel_2' => $this->blankToEmpty($data['tel_2'] ?? null),
            'whatsapp' => $this->blankToEmpty($data['whatsapp'] ?? null),
            'work_email' => trim((string) $data['work_email']),
            'private_email' => $this->blankToEmpty($data['private_email'] ?? null),
            'physical_location' => $this->blankToEmpty($data['physical_location'] ?? null),
            'flag' => 0,
            'email_disabled_by' => 0,
            'email_status' => 0,
            'email_disabled_at' => null,
        ];

        if (Schema::hasColumn('staff', 'next_of_kin_json') && array_key_exists('next_of_kin', $data)) {
            $nok = app(StaffProfileService::class)->normalizeOptionalNextOfKin((array) ($data['next_of_kin'] ?? []));
            $payload['next_of_kin_json'] = json_encode($nok, JSON_UNESCAPED_UNICODE);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function contractPayload(array $data): array
    {
        return [
            'job_id' => (int) $data['job_id'],
            'job_acting_id' => $this->blankToNull($data['job_acting_id'] ?? null),
            'grade_id' => (string) $data['grade_id'],
            'contracting_institution_id' => (int) $data['contracting_institution_id'],
            'funder_id' => (int) $data['funder_id'],
            'first_supervisor' => (int) $data['first_supervisor'],
            'second_supervisor' => $this->blankToNull($data['second_supervisor'] ?? null),
            'contract_type_id' => (int) $data['contract_type_id'],
            'duty_station_id' => (int) $data['duty_station_id'],
            'division_id' => (int) $data['division_id'],
            // staff_contracts.unit_id is NOT NULL (DB default 1).
            'unit_id' => $this->blankToNull($data['unit_id'] ?? null) ?? 1,
            'other_associated_divisions' => array_values(array_map('intval', (array) ($data['other_associated_divisions'] ?? []))),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status_id' => 1,
            'comments' => trim((string) ($data['comments'] ?? '')),
        ];
    }

    private function blankToNull(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function blankToEmpty(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
