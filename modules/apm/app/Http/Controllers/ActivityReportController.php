<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityReport;
use App\Models\ActivityReportRelated;
use App\Models\Location;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\Staff;
use App\Services\ActivityReport\ActivityReportAccessService;
use App\Services\ActivityReport\ActivityReportAutofillService;
use App\Services\ActivityReport\ActivityReportFormSchema;
use App\Services\ActivityReport\ActivityReportRelatedSearchService;
use App\Services\ActivityReport\ActivityReportService;
use App\Support\ActivityReportPermission;
use App\Support\ActivityReportingModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ActivityReportController extends Controller
{
    public function __construct(
        private ActivityReportService $reports,
        private ActivityReportAccessService $access,
        private ActivityReportAutofillService $autofill,
        private ActivityReportRelatedSearchService $relatedSearch,
        private ActivityReportFormSchema $formSchema,
    ) {
        $this->middleware(static function ($request, $next) {
            ActivityReportingModule::assertEnabled();

            return $next($request);
        });
    }

    public function open(Request $request): RedirectResponse|View
    {
        $source = $this->resolveSource($request);
        $staffId = $this->sessionStaffId();

        if (! $this->access->canView($source, $staffId) && ! $this->isPrivilegedEditor()) {
            abort(403, 'You are not a participant on this activity.');
        }

        $report = $this->reports->findOrCreateForSource($source, $staffId > 0 ? $staffId : (int) ($source->staff_id ?? 0));

        return redirect()->route('activity-reports.edit', $report);
    }

    public function edit(ActivityReport $activityReport): View
    {
        $staffId = $this->sessionStaffId();
        $activityReport->loadMissing('source');
        $source = $activityReport->source ?? $activityReport;

        if (! $this->access->canView($source, $staffId) && ! $this->isPrivilegedEditor()) {
            abort(403);
        }

        if (
            $activityReport->source
            && (string) ($activityReport->overall_status ?? 'draft') === 'draft'
            && $this->autofill->fillEmptyFromSource($activityReport, $activityReport->source)
        ) {
            $activityReport->save();
            $activityReport->refresh();
        }

        $activityReport->loadMissing('relatedLinks');
        $related = $activityReport->relatedLinks->map(fn (ActivityReportRelated $link) => [
            'value' => $link->related_source_type.'#'.$link->related_source_id,
            'related_source_type' => $link->related_source_type,
            'related_source_id' => (int) $link->related_source_id,
            'title' => $this->relatedLabel($link->related_source_type, (int) $link->related_source_id),
        ])->values()->all();

        $relatedSuggestions = $this->relatedSearch->search($activityReport, '', 30);
        $relatedItems = collect($related)
            ->concat($relatedSuggestions)
            ->unique('value')
            ->values()
            ->all();

        [$qStart, $qEnd] = $this->relatedSearch->quarterBoundsForReport($activityReport);
        $sourceModel = $activityReport->source;
        $showParticipants = $sourceModel ? $this->autofill->supportsParticipants($sourceModel) : true;
        $showBudget = $sourceModel ? $this->autofill->supportsBudget($sourceModel) : true;
        $locVenue = $this->formSchema->parseLocationVenue($activityReport->location_venue);
        $chalRec = $this->formSchema->parseChallengesRecommendations($activityReport->challenges_recommendations);

        $organiserIds = collect([
            $activityReport->lead_organiser_staff_id,
            ...((array) ($activityReport->other_organiser_staff_ids ?? [])),
            $activityReport->staff_id,
            $activityReport->responsible_person_id,
            $activityReport->created_by,
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $staffOptions = $this->staffOptionsForIds($organiserIds);
        $preparedBy = $this->staffDisplayName(
            (int) ($activityReport->created_by ?: $activityReport->staff_id ?: 0)
        );

        $pageConfig = [
            'report' => array_merge($activityReport->toArray(), [
                'start_date' => optional($activityReport->start_date)->toDateString(),
                'end_date' => optional($activityReport->end_date)->toDateString(),
                'location' => $locVenue['location'],
                'venue' => $locVenue['venue'],
                'key_challenges' => $chalRec['challenges'],
                'recommendations' => $chalRec['recommendations'],
                'member_states' => $this->formSchema->parseMemberStates($activityReport->member_states_covered),
                'mission_extras' => is_array($activityReport->mission_extras) ? $activityReport->mission_extras : [],
                'attachments' => is_array($activityReport->attachments) ? $activityReport->attachments : [],
            ]),
            'related' => $related,
            'relatedSuggestions' => $relatedItems,
            'canEdit' => $this->access->canEdit($activityReport, $staffId, $this->isPrivilegedEditor()),
            'missionTypes' => $this->formSchema->missionTypes(),
            'extrasByMissionType' => $this->formSchema->extrasByMissionType(),
            'fieldGuidance' => $this->formSchema->fieldGuidance(),
            'memberStateOptions' => $this->formSchema->memberStates(),
            'locationOptions' => Location::query()->orderBy('name')->limit(500)->pluck('name')->values()->all(),
            'staffOptions' => $staffOptions,
            'staffSearchUrl' => route('activity-reports.staff.search'),
            'updateUrl' => route('activity-reports.update', $activityReport),
            'relatedSearchUrl' => route('activity-reports.related.search', $activityReport),
            'relatedSyncUrl' => route('activity-reports.related.sync', $activityReport),
            'notExecutedUrl' => route('activity-reports.not-executed', $activityReport),
            'attachmentsUploadUrl' => route('activity-reports.attachments.store', $activityReport),
            'attachmentsDeleteUrl' => route('activity-reports.attachments.destroy', $activityReport),
            'csrfToken' => csrf_token(),
            'sourceKind' => $sourceModel ? $this->autofill->sourceKind($sourceModel) : 'unknown',
            'isExternalFunded' => $sourceModel ? $this->autofill->isExternalFunded($sourceModel) : false,
            'showParticipants' => $showParticipants,
            'showBudget' => $showBudget,
            'quarterLabel' => 'Q'.((int) ceil(((int) $qStart->month) / 3)).' '.$qStart->year,
            'quarterWindow' => $qStart->toFormattedDateString().' – '.$qEnd->toFormattedDateString(),
            'preparedBy' => $preparedBy,
            'richFields' => [
                'objectives',
                'key_strategic_needs',
                'narrative_achievements',
                'key_challenges',
                'recommendations',
                'means_of_verification',
                'logistics_actuals',
            ],
        ];

        return view('activity-reports.form', compact('activityReport', 'pageConfig'));
    }

    public function update(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $staffId = $this->sessionStaffId();
        $activityReport->loadMissing('source');
        if (! $this->access->canEdit($activityReport, $staffId, $this->isPrivilegedEditor())) {
            abort(403);
        }

        $validated = $request->validate([
            'mission_type' => 'nullable|string|max:64',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration_days' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
            'venue' => 'nullable|string|max:255',
            'lead_organiser_staff_id' => 'nullable|integer|min:1',
            'other_organiser_staff_ids' => 'nullable|array',
            'other_organiser_staff_ids.*' => 'integer|min:1',
            'objectives' => 'nullable|string',
            'narrative_achievements' => 'nullable|string',
            'key_challenges' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'participants_count' => 'nullable|integer|min:0',
            'member_states' => 'nullable|array',
            'member_states.*' => 'string|max:120',
            'key_strategic_needs' => 'nullable|string',
            'means_of_verification' => 'nullable|string',
            'budget_code' => 'nullable|string|max:100',
            'total_cost_usd' => 'nullable|numeric',
            'logistics_actuals' => 'nullable|string',
            'mission_extras' => 'nullable|array',
        ]);

        $source = $activityReport->source;
        if ($source && ! $this->autofill->supportsParticipants($source)) {
            unset($validated['participants_count'], $validated['member_states']);
        }
        if ($source && ! $this->autofill->supportsBudget($source)) {
            unset($validated['budget_code'], $validated['total_cost_usd']);
        }

        $activityReport->fill([
            'mission_type' => $validated['mission_type'] ?? $activityReport->mission_type,
            'start_date' => $validated['start_date'] ?? $activityReport->start_date,
            'end_date' => $validated['end_date'] ?? $activityReport->end_date,
            'duration_days' => $validated['duration_days'] ?? $activityReport->duration_days,
            'location_venue' => $this->formSchema->encodeLocationVenue(
                $validated['location'] ?? null,
                $validated['venue'] ?? null
            ),
            'lead_organiser_staff_id' => $validated['lead_organiser_staff_id'] ?? null,
            'other_organiser_staff_ids' => $validated['other_organiser_staff_ids'] ?? [],
            'objectives' => $validated['objectives'] ?? null,
            'narrative_achievements' => $validated['narrative_achievements'] ?? null,
            'challenges_recommendations' => $this->formSchema->encodeChallengesRecommendations(
                $validated['key_challenges'] ?? null,
                $validated['recommendations'] ?? null
            ),
            'participants_count' => array_key_exists('participants_count', $validated)
                ? $validated['participants_count']
                : $activityReport->participants_count,
            'member_states_covered' => array_key_exists('member_states', $validated)
                ? $this->formSchema->encodeMemberStates($validated['member_states'] ?? [])
                : $activityReport->member_states_covered,
            'key_strategic_needs' => $validated['key_strategic_needs'] ?? null,
            'means_of_verification' => $validated['means_of_verification'] ?? null,
            'budget_code' => array_key_exists('budget_code', $validated)
                ? ($validated['budget_code'] ?? null)
                : $activityReport->budget_code,
            'total_cost_usd' => array_key_exists('total_cost_usd', $validated)
                ? ($validated['total_cost_usd'] ?? null)
                : $activityReport->total_cost_usd,
            'logistics_actuals' => $validated['logistics_actuals'] ?? null,
            'mission_extras' => $validated['mission_extras'] ?? [],
        ]);
        $activityReport->updated_by = $staffId > 0 ? $staffId : $activityReport->updated_by;
        $activityReport->save();

        return response()->json(['data' => $activityReport->fresh()]);
    }

    public function searchRelated(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $this->assertCanView($activityReport);
        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'data' => $this->relatedSearch->search($activityReport, $q, 30),
        ]);
    }

    public function searchStaff(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $query = Staff::query()
            ->select(['staff_id', 'fname', 'lname', 'oname', 'title'])
            ->where('active', 1)
            ->whereNotIn('status', ['Expired', 'Separated']);

        if (strlen($q) >= 2) {
            $query->where(function ($w) use ($q) {
                $w->where('fname', 'like', '%'.$q.'%')
                    ->orWhere('lname', 'like', '%'.$q.'%')
                    ->orWhere('oname', 'like', '%'.$q.'%')
                    ->orWhere('work_email', 'like', '%'.$q.'%');
            });
        }

        $rows = $query->orderBy('fname')->orderBy('lname')->limit(40)->get()
            ->map(fn (Staff $s) => [
                'value' => (int) $s->staff_id,
                'title' => trim(collect([$s->title, $s->fname, $s->oname, $s->lname])->filter()->implode(' ')),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $rows]);
    }

    public function storeAttachment(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $this->assertCanEdit($activityReport);
        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        if (! in_array($ext, $allowed, true)) {
            return response()->json([
                'message' => 'File type not allowed. Use PDF, image, video or Office documents.',
            ], 422);
        }
        $filename = time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('uploads/activity-reports/'.$activityReport->id, $filename, 'public');

        $attachments = is_array($activityReport->attachments) ? $activityReport->attachments : [];
        $attachments[] = [
            'id' => uniqid('arf_', true),
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'url' => asset('storage/'.$path),
            'uploaded_at' => now()->toIso8601String(),
        ];
        $activityReport->attachments = $attachments;
        $activityReport->updated_by = $this->sessionStaffId() ?: $activityReport->updated_by;
        $activityReport->save();

        return response()->json(['data' => $attachments]);
    }

    public function destroyAttachment(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $this->assertCanEdit($activityReport);
        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        $attachments = is_array($activityReport->attachments) ? $activityReport->attachments : [];
        $kept = [];
        foreach ($attachments as $att) {
            if (($att['id'] ?? '') === $validated['id']) {
                if (! empty($att['path']) && Storage::disk('public')->exists($att['path'])) {
                    Storage::disk('public')->delete($att['path']);
                }
                continue;
            }
            $kept[] = $att;
        }
        $activityReport->attachments = $kept;
        $activityReport->updated_by = $this->sessionStaffId() ?: $activityReport->updated_by;
        $activityReport->save();

        return response()->json(['data' => $kept]);
    }

    public function syncRelated(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $this->assertCanEdit($activityReport);
        $validated = $request->validate([
            'related' => 'nullable|array',
            'related.*.related_source_type' => 'required|string',
            'related.*.related_source_id' => 'required|integer|min:1',
        ]);

        $items = $validated['related'] ?? [];
        DB::transaction(function () use ($activityReport, $items) {
            ActivityReportRelated::query()->where('activity_report_id', $activityReport->id)->delete();
            foreach ($items as $item) {
                if ($item['related_source_type'] === $activityReport->source_type
                    && (int) $item['related_source_id'] === (int) $activityReport->source_id) {
                    continue;
                }
                ActivityReportRelated::query()->create([
                    'activity_report_id' => $activityReport->id,
                    'related_source_type' => $item['related_source_type'],
                    'related_source_id' => (int) $item['related_source_id'],
                ]);
            }
        });

        return response()->json(['message' => 'Related activities saved.']);
    }

    public function setNotExecuted(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $this->assertCanEdit($activityReport);
        $validated = $request->validate([
            'not_executed' => 'required|boolean',
        ]);
        $activityReport->not_executed = (bool) $validated['not_executed'];
        $activityReport->updated_by = (int) (user_session('staff_id') ?? $activityReport->updated_by);
        $activityReport->save();

        return response()->json(['data' => $activityReport->fresh()]);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{value: int, title: string}>
     */
    private function staffOptionsForIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Staff::query()
            ->select(['staff_id', 'fname', 'lname', 'oname', 'title'])
            ->whereIn('staff_id', $ids)
            ->get()
            ->map(fn (Staff $s) => [
                'value' => (int) $s->staff_id,
                'title' => trim(collect([$s->title, $s->fname, $s->oname, $s->lname])->filter()->implode(' ')),
            ])
            ->values()
            ->all();
    }

    private function staffDisplayName(int $staffId): string
    {
        if ($staffId <= 0) {
            return '—';
        }
        $s = Staff::query()->select(['fname', 'lname', 'oname', 'title'])->where('staff_id', $staffId)->first();
        if (! $s) {
            return '—';
        }

        return trim(collect([$s->title, $s->fname, $s->oname, $s->lname])->filter()->implode(' ')) ?: '—';
    }

    private function assertCanView(ActivityReport $activityReport): void
    {
        $staffId = $this->sessionStaffId();
        $activityReport->loadMissing('source');
        $source = $activityReport->source ?? $activityReport;
        if (! $this->access->canView($source, $staffId) && ! $this->isPrivilegedEditor()) {
            abort(403);
        }
    }

    private function assertCanEdit(ActivityReport $activityReport): void
    {
        $staffId = $this->sessionStaffId();
        if (! $this->access->canEdit($activityReport, $staffId, $this->isPrivilegedEditor())) {
            abort(403);
        }
    }

    private function sessionStaffId(): int
    {
        if (function_exists('resolved_session_staff_id')) {
            return (int) (resolved_session_staff_id() ?? 0);
        }

        return (int) (user_session('staff_id') ?? user_session('auth_staff_id') ?? 0);
    }

    private function isPrivilegedEditor(): bool
    {
        return $this->isAdminLike() || ActivityReportPermission::userCanProcess();
    }

    private function relatedLabel(string $type, int $id): string
    {
        $model = match ($type) {
            Activity::class => Activity::query()->find($id),
            SpecialMemo::class => SpecialMemo::query()->find($id),
            NonTravelMemo::class => NonTravelMemo::query()->find($id),
            default => null,
        };
        if (! $model) {
            return $type.'#'.$id;
        }
        $title = (string) ($model->activity_title ?? '');
        $doc = (string) ($model->document_number ?? '');

        return trim(($doc !== '' ? $doc.' — ' : '').$title) ?: ($type.'#'.$id);
    }

    /**
     * @return Activity|SpecialMemo|NonTravelMemo
     */
    private function resolveSource(Request $request): Activity|SpecialMemo|NonTravelMemo
    {
        $validated = $request->validate([
            'source_type' => 'required|string',
            'source_id' => 'required|integer|min:1',
        ]);

        $map = [
            'activity' => Activity::class,
            'Activity' => Activity::class,
            Activity::class => Activity::class,
            'special_memo' => SpecialMemo::class,
            'SpecialMemo' => SpecialMemo::class,
            SpecialMemo::class => SpecialMemo::class,
            'non_travel' => NonTravelMemo::class,
            'non_travel_memo' => NonTravelMemo::class,
            'NonTravelMemo' => NonTravelMemo::class,
            NonTravelMemo::class => NonTravelMemo::class,
        ];

        $class = $map[$validated['source_type']] ?? null;
        if ($class === null) {
            abort(422, 'Invalid source type.');
        }

        return $class::query()->findOrFail((int) $validated['source_id']);
    }

    private function isAdminLike(): bool
    {
        $role = (int) (user_session('role') ?? user_session('user_role') ?? 0);

        return $role === 10;
    }
}
