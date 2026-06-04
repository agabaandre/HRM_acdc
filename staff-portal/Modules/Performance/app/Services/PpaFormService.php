<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Performance\Support\PerformancePeriod;

class PpaFormService
{
    public function __construct(
        protected SupervisorResolver $supervisors,
    ) {}

    public function entryIdFor(int $staffId, string $periodSlug): string
    {
        return md5($staffId.'_'.str_replace([' ', '-'], '', $periodSlug));
    }

    public function findEntry(string $entryId): ?object
    {
        return DB::table('ppa_entries')->where('entry_id', $entryId)->first();
    }

    public function findForPeriod(int $staffId, string $periodSlug): ?object
    {
        return DB::table('ppa_entries')
            ->where('staff_id', $staffId)
            ->where('performance_period', $periodSlug)
            ->first();
    }

    public function entryExists(string $entryId): bool
    {
        return DB::table('ppa_entries')->where('entry_id', $entryId)->exists();
    }

    public function isPpaApproved(string $entryId): bool
    {
        $row = $this->findEntry($entryId);

        return $row && (int) $row->draft_status === 2;
    }

    public function midtermExists(string $entryId): bool
    {
        $row = $this->findEntry($entryId);

        return $row && ! empty($row->midterm_created_at);
    }

    public function endtermExists(string $entryId): bool
    {
        $row = $this->findEntry($entryId);

        return $row && ! empty($row->endterm_created_at);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function savePpa(array $data, int $actorStaffId, string $submitAction): string
    {
        $staffId = (int) $data['staff_id'];
        $period = PerformancePeriod::toSlug($data['performance_period'] ?? '') ?? PerformancePeriod::currentSlug();
        $entryId = $this->entryIdFor($staffId, $period);

        $resolved = $this->supervisors->fromLatestContract($staffId);
        $save = [
            'staff_id' => $staffId,
            'staff_contract_id' => $data['staff_contract_id'] ?? $resolved['contract_id'],
            'performance_period' => $period,
            'entry_id' => $entryId,
            'supervisor_id' => $data['supervisor_id'] ?? $resolved['supervisor_1'],
            'supervisor2_id' => $data['supervisor2_id'] ?? $resolved['supervisor_2'],
            'objectives' => json_encode($data['objectives'] ?? []),
            'training_recommended' => $data['training_recommended'] ?? 'No',
            'required_skills' => isset($data['required_skills']) ? json_encode($data['required_skills']) : null,
            'training_contributions' => $data['training_contributions'] ?? null,
            'recommended_trainings' => $data['recommended_trainings'] ?? null,
            'recommended_trainings_details' => $data['recommended_trainings_details'] ?? null,
            'staff_sign_off' => 1,
            'draft_status' => $submitAction === 'submit' ? 0 : 1,
            'updated_at' => now(),
        ];

        if ($this->entryExists($entryId)) {
            DB::table('ppa_entries')->where('entry_id', $entryId)->update($save);
        } else {
            $save['created_at'] = now();
            DB::table('ppa_entries')->insert($save);
        }

        if ($submitAction === 'submit') {
            $action = $actorStaffId === $staffId ? 'Submitted' : 'Updated';
            DB::table('ppa_approval_trail')->insert([
                'entry_id' => $entryId,
                'staff_id' => $actorStaffId,
                'comments' => $data['comments'] ?? null,
                'action' => $action,
                'created_at' => now(),
            ]);
        }

        return $entryId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveMidterm(array $data, int $actorStaffId, string $submitAction): void
    {
        $entryId = (string) $data['entry_id'];
        $staffId = (int) $data['staff_id'];

        $save = [
            'midterm_objectives' => isset($data['objectives']) ? json_encode($data['objectives']) : null,
            'midterm_supervisor_1' => $data['supervisor_id'] ?? null,
            'midterm_supervisor_2' => $data['supervisor2_id'] ?? null,
            'midterm_competency' => isset($data['midterm_competency']) ? json_encode($data['midterm_competency']) : null,
            'midterm_comments' => $data['midterm_comments'] ?? null,
            'midterm_training_review' => $data['midterm_training_review'] ?? null,
            'midterm_recommended_skills' => isset($data['midterm_recommended_skills']) ? json_encode($data['midterm_recommended_skills']) : null,
            'midterm_achievements' => $data['midterm_achievements'] ?? null,
            'midterm_non_achievements' => $data['midterm_non_achievements'] ?? null,
            'midterm_training_contributions' => $data['midterm_training_contributions'] ?? null,
            'midterm_recommended_trainings' => $data['midterm_recommended_trainings'] ?? null,
            'midterm_recommended_trainings_details' => $data['midterm_recommended_trainings_details'] ?? null,
            'midterm_rating_by' => $actorStaffId,
            'midterm_sign_off' => 1,
            'midterm_draft_status' => ($data['midterm_submit_action'] ?? $submitAction) === 'submit' ? 0 : 1,
            'midterm_updated_at' => now(),
        ];

        $exists = $this->findEntry($entryId);
        if ($exists && empty($exists->midterm_created_at)) {
            $save['midterm_created_at'] = now();
        }

        DB::table('ppa_entries')->where('entry_id', $entryId)->update($save);

        if ($submitAction === 'submit') {
            $action = $staffId === $actorStaffId ? 'Submitted' : 'Updated';
            DB::table('ppa_approval_trail_midterm')->insert([
                'entry_id' => $entryId,
                'staff_id' => $actorStaffId,
                'comments' => $data['midterm_comments'] ?? '',
                'action' => $action,
                'type' => 'MID-TERM REVIEW',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveEndterm(array $data, int $actorStaffId, string $submitAction): void
    {
        $entryId = (string) $data['entry_id'];
        $staffId = (int) $data['staff_id'];
        $exists = $this->findEntry($entryId);

        $save = [];
        if (isset($data['objectives']) && ! empty($data['objectives'])) {
            $save['endterm_objectives'] = json_encode($data['objectives']);
        }
        if (isset($data['supervisor_id'])) {
            $save['endterm_supervisor_1'] = $data['supervisor_id'];
        }
        if (array_key_exists('supervisor2_id', $data)) {
            $save['endterm_supervisor_2'] = ! empty($data['supervisor2_id']) ? $data['supervisor2_id'] : null;
        }
        if (isset($data['endterm_competency']) && ! empty($data['endterm_competency'])) {
            $save['endterm_competency'] = json_encode($data['endterm_competency']);
        }
        foreach ([
            'endterm_comments',
            'endterm_training_review',
            'endterm_achievements',
            'endterm_non_achievements',
            'endterm_training_contributions',
            'endterm_recommended_trainings',
            'endterm_recommended_trainings_details',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $save[$field] = $data[$field];
            }
        }
        if (isset($data['endterm_recommended_skills']) && ! empty($data['endterm_recommended_skills'])) {
            $save['endterm_recommended_skills'] = json_encode($data['endterm_recommended_skills']);
        }

        $save['endterm_rating_by'] = $actorStaffId;
        $save['endterm_sign_off'] = 1;
        if (isset($data['endterm_submit_action'])) {
            $save['endterm_draft_status'] = $submitAction === 'submit' ? 0 : 1;
        }
        $save['endterm_updated_at'] = now();

        if ($exists && empty($exists->endterm_created_at)) {
            $save['endterm_created_at'] = now();
        }

        if ($save !== []) {
            DB::table('ppa_entries')->where('entry_id', $entryId)->update($save);
        }

        if ($submitAction === 'submit') {
            $action = $staffId === $actorStaffId ? 'Submitted' : 'Updated';
            DB::table('ppa_approval_trail_end_term')->insert([
                'entry_id' => $entryId,
                'staff_id' => $actorStaffId,
                'comments' => $data['endterm_comments'] ?? null,
                'action' => $action,
                'type' => 'END-TERM REVIEW',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @return list<object>
     */
    public function trainingSkills(): array
    {
        return DB::table('training_skills')->orderBy('skill')->get()->all();
    }

    /**
     * Decode JSON objectives from entry.
     *
     * @return list<array<string, mixed>>
     */
    public function decodeObjectives(mixed $raw, int $defaultRows = 5): array
    {
        $decoded = $this->decodeJson($raw);
        $rows = [];
        for ($i = 1; $i <= $defaultRows; $i++) {
            $item = $decoded[$i] ?? $decoded[$i - 1] ?? [];
            $rows[$i] = [
                'objective' => $item['objective'] ?? '',
                'timeline' => $item['timeline'] ?? '',
                'indicator' => $item['indicator'] ?? '',
                'weight' => $item['weight'] ?? '',
                'self_appraisal' => $item['self_appraisal'] ?? '',
                'appraiser_rating' => $item['appraiser_rating'] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeJson(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }
        if (is_object($raw)) {
            return json_decode(json_encode($raw), true) ?: [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * @return list<int|string>
     */
    public function decodeSkillIds(mixed $raw): array
    {
        $decoded = $this->decodeJson($raw);

        return array_values($decoded);
    }
}
