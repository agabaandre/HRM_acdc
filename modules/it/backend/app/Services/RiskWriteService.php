<?php

namespace App\Services;

use App\Support\RiskPermissions;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RiskWriteService
{
    private ResidualRiskCalculator $calculator;

    public function __construct(
        private readonly RiskAuditLogger $audit,
        ?ResidualRiskCalculator $calculator = null,
    ) {
        $this->calculator = $calculator ?? ResidualRiskCalculator::withResolver();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int|string>  $permissions
     * @return array<string, mixed>
     */
    public function create(array $payload, int $actorStaffId, array $permissions): array
    {
        if (! RiskPermissions::canManage($permissions) && ! RiskPermissions::canViewDivision($permissions)) {
            throw new RuntimeException('Forbidden');
        }

        $ownerIds = array_values(array_unique(array_filter(array_map('intval', $payload['owner_staff_ids'] ?? []))));
        unset($payload['owner_staff_ids']);

        $row = $this->normalizeWritable($payload);
        $row['workflow_state'] = 'draft';
        $row['imported'] = false;
        $row['created_at'] = now();
        $row['updated_at'] = now();

        $id = 0;
        DB::transaction(function () use (&$id, $row, $ownerIds, $actorStaffId) {
            $id = (int) DB::table('rr_risks')->insertGetId($row);
            foreach ($ownerIds as $staffId) {
                DB::table('rr_risk_owners')->insert([
                    'risk_id' => $id,
                    'staff_id' => $staffId,
                    'is_default_hod' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->audit->log('created', 'rr_risks', $id, null, $this->snapshot($id), $actorStaffId);
        });

        return $this->snapshot($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int|string>  $permissions
     * @return array<string, mixed>
     */
    public function update(int $riskId, array $payload, int $actorStaffId, array $permissions): array
    {
        $existing = DB::table('rr_risks')->where('id', $riskId)->first();
        if (! $existing) {
            throw new RuntimeException('Not found');
        }

        $canManage = RiskPermissions::canManage($permissions);
        $isSignedOff = in_array((string) $existing->workflow_state, ['signed_off', 'active_imported'], true);
        if ($isSignedOff && ! $canManage) {
            throw new RuntimeException('Forbidden');
        }
        if (! $canManage && ! RiskPermissions::canViewDivision($permissions)) {
            throw new RuntimeException('Forbidden');
        }

        $before = $this->snapshot($riskId);
        $ownerIds = null;
        if (array_key_exists('owner_staff_ids', $payload)) {
            $ownerIds = array_values(array_unique(array_filter(array_map('intval', $payload['owner_staff_ids'] ?? []))));
            unset($payload['owner_staff_ids']);
        }

        $row = $this->normalizeWritable($payload, (array) $existing);
        $row['updated_at'] = now();

        DB::transaction(function () use ($riskId, $row, $ownerIds, $before, $actorStaffId) {
            DB::table('rr_risks')->where('id', $riskId)->update($row);
            if ($ownerIds !== null) {
                DB::table('rr_risk_owners')->where('risk_id', $riskId)->delete();
                foreach ($ownerIds as $staffId) {
                    DB::table('rr_risk_owners')->insert([
                        'risk_id' => $riskId,
                        'staff_id' => $staffId,
                        'is_default_hod' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->audit->log('updated', 'rr_risks', $riskId, $before, $this->snapshot($riskId), $actorStaffId);
        });

        return $this->snapshot($riskId);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $riskId): array
    {
        $risk = DB::table('rr_risks')->where('id', $riskId)->first();
        if (! $risk) {
            return [];
        }
        $owners = DB::table('rr_risk_owners')->where('risk_id', $riskId)->pluck('staff_id')->map(fn ($id) => (int) $id)->all();
        $data = (array) $risk;
        $data['owner_staff_ids'] = $owners;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function normalizeWritable(array $payload, array $existing = []): array
    {
        $likelihood = isset($payload['inherent_likelihood'])
            ? (int) $payload['inherent_likelihood']
            : (int) ($existing['inherent_likelihood'] ?? 1);
        $impact = isset($payload['inherent_impact'])
            ? (int) $payload['inherent_impact']
            : (int) ($existing['inherent_impact'] ?? 1);

        $effId = $payload['mitigation_effectiveness_id'] ?? $existing['mitigation_effectiveness_id'] ?? null;
        $reduction = 0;
        $assessed = false;
        if ($effId) {
            $eff = DB::table('rr_mitigation_effectiveness')->where('id', (int) $effId)->first();
            if ($eff) {
                $reduction = (int) $eff->likelihood_reduction;
                $assessed = (bool) $eff->is_assessed;
            }
        }

        $scores = $this->calculator->compute($likelihood, $impact, $reduction, $assessed);

        $fields = [
            'division_id', 'directorate_id', 'unmapped_business_unit', 'source_business_unit',
            'enterprise_theme_id', 'name', 'consequence', 'root_causes', 'risk_type_id',
            'mitigation', 'management_response', 'timeline', 'status_id', 'action_update',
            'date_of_update', 'oio_verification_notes', 'mitigation_effectiveness_id',
        ];

        $row = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $payload)) {
                $row[$field] = $payload[$field];
            } elseif ($existing !== [] && array_key_exists($field, $existing) && ! array_key_exists('name', $payload)) {
                // only copy existing when updating partial — handled below
            }
        }

        // On create, name required; on update merge unspecified from existing
        if ($existing !== []) {
            foreach ($fields as $field) {
                if (! array_key_exists($field, $row) && array_key_exists($field, $existing)) {
                    $row[$field] = $existing[$field];
                }
            }
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('name is required');
        }
        $row['name'] = mb_substr($name, 0, 512);
        $row['inherent_likelihood'] = $likelihood;
        $row['inherent_impact'] = $impact;
        $row['inherent_score'] = $scores['inherent_score'];
        $row['inherent_rating'] = $scores['inherent_rating'];
        $row['residual_likelihood'] = $scores['residual_likelihood'];
        $row['residual_impact'] = $scores['residual_impact'];
        $row['residual_score'] = $scores['residual_score'];
        $row['residual_rating'] = $scores['residual_rating'];
        $row['risk_movement'] = $scores['movement'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('rr_risks', 'inherent_rating_key')) {
            $row['inherent_rating_key'] = $scores['inherent_rating_key'];
            $row['residual_rating_key'] = $scores['residual_rating_key'];
            $row['rating_key_version'] = $scores['rating_key_version'];
        }

        return $row;
    }
}
