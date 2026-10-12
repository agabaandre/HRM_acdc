<?php

namespace App\Services\ActivityReport;

use App\Models\ActivityReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ActivityReportService
{
    /** @var list<string> */
    public const MISSION_TYPES = [
        'official_engagement',
        'training',
        'events',
        'procurement',
        'in_office',
        'recruitment_hr',
        'others',
    ];

    public function __construct(
        private ActivityReportAutofillService $autofill,
    ) {}

    public function findOrCreateForSource(Model $source, int $actorStaffId): ActivityReport
    {
        $existing = ActivityReport::query()
            ->where('source_id', $source->getKey())
            ->where('source_type', $source::class)
            ->first();

        if ($existing) {
            return $existing;
        }

        $attrs = $this->autofill->fromSource($source);
        $attrs['source_id'] = $source->getKey();
        $attrs['source_type'] = $source::class;
        $attrs['overall_status'] = 'draft';
        $attrs['not_executed'] = false;
        $attrs['has_been_submitted'] = false;
        $attrs['created_by'] = $actorStaffId;
        $attrs['updated_by'] = $actorStaffId;

        try {
            return DB::transaction(function () use ($attrs, $source) {
                $again = ActivityReport::query()
                    ->where('source_id', $source->getKey())
                    ->where('source_type', $source::class)
                    ->lockForUpdate()
                    ->first();
                if ($again) {
                    return $again;
                }

                return ActivityReport::withoutEvents(fn () => ActivityReport::query()->create($attrs));
            });
        } catch (QueryException $e) {
            $existing = ActivityReport::query()
                ->where('source_id', $source->getKey())
                ->where('source_type', $source::class)
                ->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function previewPayload(ActivityReport $report): array
    {
        $report->loadMissing(['relatedLinks']);

        $related = $report->relatedLinks->map(fn ($link) => [
            'related_source_id' => $link->related_source_id,
            'related_source_type' => $link->related_source_type,
        ])->values()->all();

        return [
            'id' => $report->id,
            'document_number' => $report->document_number,
            'mission_type' => $report->mission_type,
            'overall_status' => $report->overall_status,
            'not_executed' => (bool) $report->not_executed,
            'activity_name_code' => $report->activity_name_code,
            'tier2' => $report->tier2,
            'tier4' => $report->tier4,
            'broad_activity_amert' => $report->broad_activity_amert,
            'start_date' => optional($report->start_date)->toDateString(),
            'end_date' => optional($report->end_date)->toDateString(),
            'duration_days' => $report->duration_days,
            'location_venue' => $report->location_venue,
            'objectives' => $report->objectives,
            'participants_count' => $report->participants_count,
            'member_states_covered' => $report->member_states_covered,
            'key_strategic_needs' => $report->key_strategic_needs,
            'narrative_achievements' => $report->narrative_achievements,
            'challenges_recommendations' => $report->challenges_recommendations,
            'means_of_verification' => $report->means_of_verification,
            'budget_code' => $report->budget_code,
            'total_cost_usd' => $report->total_cost_usd,
            'logistics_planned' => $report->logistics_planned,
            'logistics_actuals' => $report->logistics_actuals,
            'mission_extras' => $report->mission_extras,
            'related' => $related,
        ];
    }
}
