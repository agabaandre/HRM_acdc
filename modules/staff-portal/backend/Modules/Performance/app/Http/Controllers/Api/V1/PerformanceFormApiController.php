<?php

namespace Modules\Performance\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Services\CsvExportService;
use Modules\Core\Services\PdfService;
use Modules\Core\Support\PortalPermission;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Services\CompetencyService;
use Modules\Performance\Services\PerformanceApprovalService;
use Modules\Performance\Services\PerformanceAnalyticsService;
use Modules\Performance\Services\PerformanceService;
use Modules\Performance\Services\PerformanceWorkflowCorrectionService;
use Modules\Performance\Services\PerformanceWorkflowService;
use Modules\Performance\Services\PpaContractService;
use Modules\Performance\Services\PpaFormService;
use Modules\Performance\Services\PpaSettingsService;
use Modules\Performance\Services\SupervisorResolver;
use Modules\Performance\Support\EndtermScore;
use Modules\Performance\Support\PerformanceFormAccess;
use Modules\Performance\Support\PerformancePeriod;
use Modules\Performance\Support\PerformanceRichText;

class PerformanceFormApiController extends Controller
{
    public function show(
        string $entryId,
        Request $request,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance
    ): JsonResponse {
        PortalPermission::authorize(74);

        $phase = $this->resolvePhase($request);
        $actorStaffId = $this->actorStaffId();

        $entry = $forms->findEntry($entryId);
        if (! $entry) {
            abort(404, 'Entry not found.');
        }

        $entry = $this->syncEntryForPhase($entry, $phase, $workflow, $forms);
        $this->authorizeEntryAccess($entry, $phase, $actorStaffId, $workflow);

        return response()->json([
            'data' => $this->buildExistingPayload(
                $entry,
                $phase,
                $actorStaffId,
                $forms,
                $contracts,
                $settings,
                $competencies,
                $workflow,
                $approval,
                $performance,
            ),
        ]);
    }

    public function create(
        Request $request,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance,
        SupervisorResolver $supervisors
    ): JsonResponse {
        PortalPermission::authorize(74);

        $validated = $request->validate([
            'period' => 'required|string|max:50',
            'staff_id' => 'nullable|integer',
        ]);

        $actorStaffId = $this->actorStaffId();
        $staffId = (int) ($validated['staff_id'] ?? $actorStaffId);
        $this->authorizeTargetStaffAccess($staffId, $actorStaffId, $supervisors);

        $periodSlug = PerformancePeriod::toSlug((string) $validated['period']) ?? PerformancePeriod::currentSlug();
        $existing = $forms->findForPeriod($staffId, $periodSlug);

        if ($existing) {
            $existing = $this->syncEntryForPhase($existing, PerformancePhase::Ppa, $workflow, $forms);
            $this->authorizeEntryAccess($existing, PerformancePhase::Ppa, $actorStaffId, $workflow);

            return response()->json([
                'data' => $this->buildExistingPayload(
                    $existing,
                    PerformancePhase::Ppa,
                    $actorStaffId,
                    $forms,
                    $contracts,
                    $settings,
                    $competencies,
                    $workflow,
                    $approval,
                    $performance,
                ),
            ]);
        }

        return response()->json([
            'data' => $this->buildBootstrapPayload(
                $staffId,
                $periodSlug,
                $actorStaffId,
                $forms,
                $contracts,
                $settings,
                $competencies,
                $supervisors,
            ),
        ]);
    }

    public function update(
        string $entryId,
        Request $request,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance,
        SupervisorResolver $supervisors
    ): JsonResponse {
        return $this->persistEntry(
            $entryId,
            $request,
            'draft',
            $forms,
            $contracts,
            $settings,
            $competencies,
            $workflow,
            $approval,
            $performance,
            $supervisors,
        );
    }

    public function submit(
        string $entryId,
        Request $request,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance,
        SupervisorResolver $supervisors
    ): JsonResponse {
        return $this->persistEntry(
            $entryId,
            $request,
            'submit',
            $forms,
            $contracts,
            $settings,
            $competencies,
            $workflow,
            $approval,
            $performance,
            $supervisors,
        );
    }

    public function updateSupervisors(
        string $entryId,
        Request $request,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance
    ): JsonResponse {
        PortalPermission::authorize(74);

        $phase = $this->resolvePhase($request);
        $actorStaffId = $this->actorStaffId();
        $entry = $forms->findEntry($entryId);
        if (! $entry) {
            abort(404, 'Entry not found.');
        }

        $entry = $this->syncEntryForPhase($entry, $phase, $workflow, $forms);
        $this->authorizeEntryAccess($entry, $phase, $actorStaffId, $workflow);

        if (! PerformanceFormAccess::canChangeSupervisors($entry, $phase, $actorStaffId)) {
            throw ValidationException::withMessages([
                'supervisors' => ['Supervisors can only be changed on draft PPA, midterm, and endterm forms.'],
            ]);
        }

        $validated = $request->validate([
            'supervisor_id' => 'required|integer|min:1',
            'supervisor2_id' => 'nullable|integer|min:1',
        ]);

        $forms->updateSupervisors(
            $entry,
            $phase,
            (int) $validated['supervisor_id'],
            isset($validated['supervisor2_id']) ? (int) $validated['supervisor2_id'] : null,
        );

        $entry = $forms->findEntry($entryId) ?? $entry;

        return response()->json([
            'message' => 'Supervisors updated.',
            'data' => $this->buildExistingPayload(
                $entry,
                $phase,
                $actorStaffId,
                $forms,
                $contracts,
                $settings,
                $competencies,
                $workflow,
                $approval,
                $performance,
            ),
        ]);
    }

    public function approve(
        string $entryId,
        Request $request,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance
    ): JsonResponse {
        PortalPermission::authorize(74);

        $phase = $this->resolvePhase($request);
        $actorStaffId = $this->actorStaffId();
        $entry = $forms->findEntry($entryId);
        if (! $entry) {
            abort(404, 'Entry not found.');
        }

        $entry = $this->syncEntryForPhase($entry, $phase, $workflow, $forms);
        $this->authorizeEntryAccess($entry, $phase, $actorStaffId, $workflow);

        if (! $workflow->canActorApprove($entry, $phase, $actorStaffId)) {
            abort(403, 'You are not allowed to approve this entry.');
        }

        $validated = $request->validate([
            'comments' => 'nullable|string|max:20000',
            'supervisor2_agreement' => 'nullable|boolean',
        ]);

        try {
            $approval->approve(
                $entryId,
                $phase,
                $actorStaffId,
                PerformanceRichText::sanitize((string) ($validated['comments'] ?? '')),
                array_key_exists('supervisor2_agreement', $validated)
                    ? (bool) $validated['supervisor2_agreement']
                    : null,
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'entry' => [$e->getMessage()],
            ]);
        }

        $entry = $this->syncEntryForPhase($forms->findEntry($entryId) ?? $entry, $phase, $workflow, $forms);

        return response()->json([
            'message' => 'Approval recorded.',
            'data' => $this->buildExistingPayload(
                $entry,
                $phase,
                $actorStaffId,
                $forms,
                $contracts,
                $settings,
                $competencies,
                $workflow,
                $approval,
                $performance,
            ),
        ]);
    }

    public function returnEntry(
        string $entryId,
        Request $request,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance
    ): JsonResponse {
        PortalPermission::authorize(74);

        $phase = $this->resolvePhase($request);
        $actorStaffId = $this->actorStaffId();
        $entry = $forms->findEntry($entryId);
        if (! $entry) {
            abort(404, 'Entry not found.');
        }

        $entry = $this->syncEntryForPhase($entry, $phase, $workflow, $forms);
        $this->authorizeEntryAccess($entry, $phase, $actorStaffId, $workflow);

        $canConsent = $phase === PerformancePhase::Endterm
            && ($workflow->resolveState($entry, $phase)['step'] ?? '') === 'employee_consent'
            && $actorStaffId === (int) $entry->staff_id;
        $phaseDraft = (int) ($entry->{$phase->draftStatusColumn()} ?? 1);
        if (! $this->actorCanReturn($phaseDraft, $canConsent, $settings)) {
            abort(403, 'You are not allowed to return this entry.');
        }

        $validated = $request->validate([
            'comments' => 'required|string|max:20000',
        ]);

        $comments = PerformanceRichText::sanitize((string) $validated['comments']);
        if (PerformanceRichText::isEmpty($comments)) {
            throw ValidationException::withMessages([
                'comments' => ['Comments are required when returning a form for revision.'],
            ]);
        }

        try {
            $approval->returnForRevision($entryId, $phase, $actorStaffId, $comments);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'entry' => [$e->getMessage()],
            ]);
        }

        $entry = $this->syncEntryForPhase($forms->findEntry($entryId) ?? $entry, $phase, $workflow, $forms);

        return response()->json([
            'message' => 'Returned for revision.',
            'data' => $this->buildExistingPayload(
                $entry,
                $phase,
                $actorStaffId,
                $forms,
                $contracts,
                $settings,
                $competencies,
                $workflow,
                $approval,
                $performance,
            ),
        ]);
    }

    public function consent(
        string $entryId,
        Request $request,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance
    ): JsonResponse {
        PortalPermission::authorize(74);

        $phase = PerformancePhase::Endterm;
        $actorStaffId = $this->actorStaffId();
        $entry = $forms->findEntry($entryId);
        if (! $entry) {
            abort(404, 'Entry not found.');
        }

        $entry = $this->syncEntryForPhase($entry, $phase, $workflow, $forms);
        $this->authorizeEntryAccess($entry, $phase, $actorStaffId, $workflow);

        $validated = $request->validate([
            'comments' => 'nullable|string|max:20000',
            'accept_rating' => 'nullable|boolean',
        ]);

        try {
            $approval->recordEmployeeConsent(
                $entryId,
                $actorStaffId,
                PerformanceRichText::sanitize((string) ($validated['comments'] ?? '')),
                array_key_exists('accept_rating', $validated) ? (bool) $validated['accept_rating'] : true,
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'entry' => [$e->getMessage()],
            ]);
        }

        $entry = $this->syncEntryForPhase($forms->findEntry($entryId) ?? $entry, $phase, $workflow, $forms);

        return response()->json([
            'message' => 'Consent recorded.',
            'data' => $this->buildExistingPayload(
                $entry,
                $phase,
                $actorStaffId,
                $forms,
                $contracts,
                $settings,
                $competencies,
                $workflow,
                $approval,
                $performance,
            ),
        ]);
    }

    public function analytics(
        Request $request,
        PerformanceAnalyticsService $analytics,
        PerformanceService $performance
    ): JsonResponse {
        PortalPermission::authorize(74);

        $user = auth()->user();
        $session = $user instanceof PortalUser ? $user->toSessionArray() : (session('user') ?? []);
        $staffId = (int) ($session['staff_id'] ?? ($user instanceof PortalUser ? $user->auth_staff_id : 0));
        $roleId = (int) ($session['role_id'] ?? $session['role'] ?? ($user instanceof PortalUser ? $user->role : 0));
        $restrictStaff = $roleId === 17 ? $staffId : null;

        $phase = PerformancePhase::tryFrom((string) $request->query('phase', 'ppa')) ?? PerformancePhase::Ppa;
        $period = (string) ($request->query('period') ?: $performance->currentPeriodSlug());
        $division = $request->filled('division_id') ? (int) $request->query('division_id') : null;
        $funder = $request->filled('funder_id') ? (int) $request->query('funder_id') : null;

        return response()->json([
            'data' => $analytics->dashboard($phase, $division, $period, $restrictStaff, $funder),
            'meta' => [
                'periods' => $performance->periodOptions(),
                'divisions' => DB::table('divisions')->orderBy('division_name')->get(['division_id', 'division_name']),
                'funders' => Schema::hasTable('funders')
                    ? DB::table('funders')->orderBy('funder')->get(['funder_id', 'funder'])
                    : [],
            ],
        ]);
    }

    public function exportCsv(
        Request $request,
        PerformanceAnalyticsService $analytics,
        PerformanceService $performance,
        CsvExportService $csv
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        PortalPermission::authorize(74);

        $phase = PerformancePhase::tryFrom((string) $request->query('phase', 'ppa')) ?? PerformancePhase::Ppa;
        $period = (string) ($request->query('period') ?: $performance->currentPeriodSlug());
        $division = $request->filled('division_id') ? (int) $request->query('division_id') : null;
        $funder = $request->filled('funder_id') ? (int) $request->query('funder_id') : null;
        $data = $analytics->dashboard($phase, $division, $period, $this->analyticsRestrictStaffId(), $funder);

        $rows = [
            ['Phase', $data['phase_label']],
            ['Period', $data['period']],
            ['Total', $data['summary']['total']],
            ['Approved', $data['summary']['approved']],
            ['Submitted / pending', $data['summary']['submitted']],
            ['Draft', $data['summary']['draft']],
            ['Without', $data['summary']['without']],
            ['PDPs', $data['summary']['pdps'] ?? 0],
            ['Require calibration', $data['summary']['require_calibration'] ?? 0],
            ['Avg approval days', $data['avg_approval_days'] ?? 0],
            ['Avg score', $data['avg_score'] ?? ''],
            [],
            ['Division', 'Count'],
        ];
        foreach ($data['by_division'] as $row) {
            $rows[] = [$row['name'] ?? ($row->name ?? ''), $row['y'] ?? ($row->y ?? 0)];
        }
        $rows[] = [];
        $rows[] = ['Contract type', 'Count'];
        foreach ($data['by_contract'] ?? [] as $row) {
            $rows[] = [$row['name'] ?? '', $row['y'] ?? 0];
        }

        return $csv->stream('performance-'.$phase->value.'-dashboard.csv', $rows);
    }

    public function printEntry(
        string $entryId,
        Request $request,
        PpaFormService $forms,
        PdfService $pdf,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PpaContractService $contracts,
        SupervisorResolver $supervisors,
    ): Response {
        PortalPermission::authorize(74);

        $phase = PerformancePhase::tryFrom((string) $request->query('phase', 'ppa')) ?? PerformancePhase::Ppa;
        $withTrail = $request->boolean('with_trail');
        $actorStaffId = $this->actorStaffId();
        $entry = $forms->findEntry($entryId);
        if (! $entry) {
            abort(404, 'Entry not found');
        }
        $entry = $this->syncEntryForPhase($entry, $phase, $workflow, $forms);
        $this->authorizeEntryAccess($entry, $phase, $actorStaffId, $workflow);

        $objectives = $forms->decodeObjectives((string) ($entry->objectives ?? ''), 5);
        if ($phase === PerformancePhase::Midterm) {
            $objectives = $forms->decodeObjectives((string) ($entry->midterm_objectives ?: $entry->objectives ?? ''), 10);
        }
        if ($phase === PerformancePhase::Endterm) {
            $objectives = $forms->decodeObjectives(
                (string) ($entry->endterm_objectives ?: $entry->midterm_objectives ?: $entry->objectives ?? ''),
                10,
            );
        }

        $staff = DB::table('staff')->where('staff_id', $entry->staff_id)->first();
        $contractId = (int) ($entry->staff_contract_id ?? 0);
        $contract = $contractId > 0
            ? ($contracts->forContract($contractId) ?? $contracts->forStaff((int) $entry->staff_id))
            : $contracts->forStaff((int) $entry->staff_id);
        if (! $contract) {
            $contract = $contracts->emptyContractStub($contractId);
            if ($staff) {
                $contract->fname = $staff->fname ?? '';
                $contract->lname = $staff->lname ?? '';
                $contract->SAPNO = $staff->SAPNO ?? '';
            }
        }

        $supervisor1Id = match ($phase) {
            PerformancePhase::Midterm => (int) ($entry->midterm_supervisor_1 ?? $entry->supervisor_id ?? 0),
            PerformancePhase::Endterm => (int) ($entry->endterm_supervisor_1 ?? $entry->supervisor_id ?? 0),
            default => (int) ($entry->supervisor_id ?? $contract->first_supervisor ?? 0),
        };
        $supervisor2Id = match ($phase) {
            PerformancePhase::Midterm => (int) ($entry->midterm_supervisor_2 ?? $entry->supervisor2_id ?? 0),
            PerformancePhase::Endterm => (int) ($entry->endterm_supervisor_2 ?? $entry->supervisor2_id ?? 0),
            default => (int) ($entry->supervisor2_id ?? $contract->second_supervisor ?? 0),
        };

        $skillsMap = [];
        foreach ($forms->trainingSkills() as $skill) {
            $skillsMap[(int) ($skill->id ?? 0)] = (string) ($skill->skill ?? '');
        }
        $selectedSkills = json_decode((string) ($entry->required_skills ?? '[]'), true);
        if (! is_array($selectedSkills)) {
            $selectedSkills = [];
        }
        $skillsLabel = implode(', ', array_values(array_filter(array_map(
            fn ($id) => $skillsMap[(int) $id] ?? '',
            $selectedSkills,
        ))));

        $draftCol = $phase->draftStatusColumn();
        $draftStatus = (int) ($entry->{$draftCol} ?? 1);
        $watermark = null;
        if ($draftStatus !== 2) {
            $watermark = $draftStatus === 1 ? 'DRAFT' : 'PENDING APPROVAL';
        }

        $logoCandidates = [
            base_path('../../assets/images/AU_CDC_Logo-800.png'),
            base_path('../assets/images/AU_CDC_Logo-800.png'),
            public_path('images/AU_CDC_Logo-800.png'),
        ];
        $logoSrc = null;
        foreach ($logoCandidates as $path) {
            if (is_string($path) && is_file($path)) {
                $logoSrc = $path;
                break;
            }
        }

        $html = view('performance::pdf.entry', [
            'entry' => $entry,
            'staff' => $staff,
            'contract' => $contract,
            'phase' => $phase,
            'objectives' => $objectives,
            'withTrail' => $withTrail,
            'trail' => $withTrail ? $approval->printTrail($entryId, $phase, $entry) : [],
            'overallRating' => $phase === PerformancePhase::Endterm ? EndtermScore::fromObjectives($objectives) : null,
            'supervisor1Name' => $supervisors->staffName($supervisor1Id ?: null),
            'supervisor2Name' => $supervisors->staffName($supervisor2Id ?: null),
            'skillsLabel' => $skillsLabel,
            'logoSrc' => $logoSrc,
            'generatedAt' => now()->toDateTimeString(),
        ])->render();

        $fileLabel = match ($phase) {
            PerformancePhase::Midterm => 'Midterm',
            PerformancePhase::Endterm => 'Endterm',
            default => 'PPA',
        };

        $documentUrl = url('/api/v1/performance/entries/'.$entryId.'/print.pdf')
            .'?phase='.urlencode($phase->value)
            .($withTrail ? '&with_trail=1' : '');

        return $pdf->inline($html, $fileLabel.'-'.$entryId.($withTrail ? '-trail' : '').'.pdf', [
            'title' => $fileLabel.' — '.$entry->performance_period,
            'landscape' => false,
            // Body template already has the CI3 logo/tagline header — skip PdfService header to avoid duplication.
            'header' => false,
            'document_url' => $documentUrl,
            'generated_by' => (string) (session('user.name') ?? ''),
            'watermark_text' => $watermark ?? '',
        ]);
    }

    public function formUrls(PerformanceService $performance): JsonResponse
    {
        PortalPermission::authorize(74);

        return response()->json([
            'data' => [
                'create_ppa' => $performance->createPpaUrl(),
                'spa_create' => '/performance/create',
            ],
        ]);
    }

    protected function persistEntry(
        string $entryId,
        Request $request,
        string $submitAction,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance,
        SupervisorResolver $supervisors
    ): JsonResponse {
        PortalPermission::authorize(74);

        $phase = $this->resolvePhase($request);
        $actorStaffId = $this->actorStaffId();

        $request->validate([
            'staff_id' => 'nullable|integer',
            'performance_period' => 'nullable|string|max:50',
            'staff_contract_id' => 'nullable|integer',
            'supervisor_id' => 'nullable|integer',
            'supervisor2_id' => 'nullable|integer',
            'objectives' => 'nullable|array',
            'training_recommended' => 'nullable|in:Yes,No',
            'required_skills' => 'nullable|array',
            'training_contributions' => 'nullable|string',
            'recommended_trainings' => 'nullable|string',
            'recommended_trainings_details' => 'nullable|string',
            'comments' => 'nullable|string|max:20000',
            'midterm_comments' => 'nullable|string',
            'midterm_training_review' => 'nullable|string',
            'midterm_achievements' => 'nullable|string',
            'midterm_non_achievements' => 'nullable|string',
            'midterm_training_contributions' => 'nullable|string',
            'midterm_recommended_trainings' => 'nullable|string',
            'midterm_recommended_trainings_details' => 'nullable|string',
            'midterm_recommended_skills' => 'nullable|array',
            'midterm_competency' => 'nullable|array',
            'endterm_comments' => 'nullable|string',
            'endterm_training_review' => 'nullable|string',
            'endterm_achievements' => 'nullable|string',
            'endterm_non_achievements' => 'nullable|string',
            'endterm_training_contributions' => 'nullable|string',
            'endterm_recommended_trainings' => 'nullable|string',
            'endterm_recommended_trainings_details' => 'nullable|string',
            'endterm_recommended_skills' => 'nullable|array',
            'endterm_competency' => 'nullable|array',
        ]);

        $existing = $forms->findEntry($entryId);
        if ($existing) {
            $existing = $this->syncEntryForPhase($existing, $phase, $workflow, $forms);
            $this->authorizeEntryAccess($existing, $phase, $actorStaffId, $workflow);
        }

        $staffId = (int) ($request->input('staff_id') ?: ($existing->staff_id ?? $actorStaffId));
        $this->authorizeTargetStaffAccess($staffId, $actorStaffId, $supervisors);
        $this->ensureOwnerCanEdit($phase, $staffId, $actorStaffId, $settings);

        if (! PerformanceFormAccess::canChangeSupervisors($existing, $phase, $actorStaffId)) {
            $request->merge([
                'supervisor_id' => $this->phaseSupervisorValue($existing, $phase, true),
                'supervisor2_id' => $this->phaseSupervisorValue($existing, $phase, false),
            ]);
        }

        if ($phase !== PerformancePhase::Ppa && ! $existing) {
            abort(404, 'Entry not found.');
        }

        $payload = PerformanceRichText::sanitizeFormPayload([
            'staff_id' => $staffId,
            'staff_contract_id' => $this->nullableInt($request->input('staff_contract_id'), $existing->staff_contract_id ?? null),
            'performance_period' => (string) ($request->input('performance_period') ?: ($existing->performance_period ?? PerformancePeriod::currentSlug())),
            'entry_id' => $entryId,
            'supervisor_id' => $this->nullableInt(
                $request->input('supervisor_id'),
                $this->phaseSupervisorValue($existing, $phase, true)
            ),
            'supervisor2_id' => $this->nullableInt(
                $request->input('supervisor2_id'),
                $this->phaseSupervisorValue($existing, $phase, false)
            ),
            'objectives' => $request->input('objectives', []),
            'training_recommended' => (string) $request->input('training_recommended', 'No'),
            'required_skills' => $request->input('required_skills', []),
            'training_contributions' => $request->input('training_contributions'),
            'recommended_trainings' => $request->input('recommended_trainings'),
            'recommended_trainings_details' => $request->input('recommended_trainings_details'),
            'comments' => (string) $request->input('comments', ''),
            'midterm_comments' => $request->input('midterm_comments'),
            'midterm_training_review' => $request->input('midterm_training_review'),
            'midterm_achievements' => $request->input('midterm_achievements'),
            'midterm_non_achievements' => $request->input('midterm_non_achievements'),
            'midterm_training_contributions' => $request->input('midterm_training_contributions'),
            'midterm_recommended_trainings' => $request->input('midterm_recommended_trainings'),
            'midterm_recommended_trainings_details' => $request->input('midterm_recommended_trainings_details'),
            'midterm_recommended_skills' => $request->input('midterm_recommended_skills', []),
            'midterm_competency' => $request->input('midterm_competency', []),
            'endterm_comments' => $request->input('endterm_comments'),
            'endterm_training_review' => $request->input('endterm_training_review'),
            'endterm_achievements' => $request->input('endterm_achievements'),
            'endterm_non_achievements' => $request->input('endterm_non_achievements'),
            'endterm_training_contributions' => $request->input('endterm_training_contributions'),
            'endterm_recommended_trainings' => $request->input('endterm_recommended_trainings'),
            'endterm_recommended_trainings_details' => $request->input('endterm_recommended_trainings_details'),
            'endterm_recommended_skills' => $request->input('endterm_recommended_skills', []),
            'endterm_competency' => $request->input('endterm_competency', []),
            'midterm_submit_action' => $submitAction,
            'endterm_submit_action' => $submitAction,
        ]);

        try {
            match ($phase) {
                PerformancePhase::Ppa => $forms->savePpa($payload, $actorStaffId, $submitAction),
                PerformancePhase::Midterm => $forms->saveMidterm($payload, $actorStaffId, $submitAction),
                PerformancePhase::Endterm => $forms->saveEndterm($payload, $actorStaffId, $submitAction),
            };
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'entry' => [$e->getMessage()],
            ]);
        }

        $entry = $forms->findEntry($entryId);
        if (! $entry && $phase === PerformancePhase::Ppa) {
            $entry = $forms->findForPeriod($staffId, (string) $payload['performance_period']);
        }
        if (! $entry) {
            abort(404, 'Entry not found.');
        }

        if (
            PerformanceFormAccess::canChangeSupervisors($existing, $phase, $actorStaffId)
            && (int) ($payload['supervisor_id'] ?? 0) > 0
        ) {
            $forms->updateSupervisors(
                $entry,
                $phase,
                (int) $payload['supervisor_id'],
                ! empty($payload['supervisor2_id']) ? (int) $payload['supervisor2_id'] : null,
            );
            $entry = $forms->findEntry((string) $entry->entry_id) ?? $entry;
        }

        $entry = $this->syncEntryForPhase($entry, $phase, $workflow, $forms);

        return response()->json([
            'message' => $submitAction === 'submit' ? 'Entry submitted.' : 'Draft saved.',
            'data' => $this->buildExistingPayload(
                $entry,
                $phase,
                $actorStaffId,
                $forms,
                $contracts,
                $settings,
                $competencies,
                $workflow,
                $approval,
                $performance,
            ),
        ]);
    }

    protected function buildBootstrapPayload(
        int $staffId,
        string $periodSlug,
        int $actorStaffId,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        SupervisorResolver $supervisors
    ): array {
        $resolved = $supervisors->fromLatestContract($staffId);
        $staffContractId = (int) ($resolved['contract_id'] ?? 0);
        $contract = $staffContractId > 0 ? $contracts->forContract($staffContractId) : $contracts->forStaff($staffId);
        $contractMissing = $contract === null;
        if (! $contract) {
            $contract = $contracts->emptyContractStub($staffContractId);
        }

        $phase = PerformancePhase::Ppa;
        $submissionWindow = $settings->submissionWindowStatus($phase);
        $isOwner = $actorStaffId === $staffId;

        return [
            'phase' => $phase->value,
            'phase_label' => $phase->label(),
            'entry' => [
                'entry_id' => $forms->entryIdFor($staffId, $periodSlug),
                'staff_id' => $staffId,
                'performance_period' => $periodSlug,
                'staff_contract_id' => $staffContractId,
                'supervisor_id' => (int) ($resolved['supervisor_1'] ?? 0),
                'supervisor2_id' => (int) ($resolved['supervisor_2'] ?? 0),
                'draft_status' => 1,
                'midterm_draft_status' => 1,
                'endterm_draft_status' => 1,
            ],
            'form' => $this->emptyFormState(
                $staffId,
                $periodSlug,
                $staffContractId,
                (int) ($resolved['supervisor_1'] ?? 0),
                (int) ($resolved['supervisor_2'] ?? 0),
                $forms
            ),
            'contract' => $this->contractWithSupervisorNames(
                (array) $contract,
                (int) ($resolved['supervisor_1'] ?? 0),
                (int) ($resolved['supervisor_2'] ?? 0),
            ),
            'contract_missing' => $contractMissing,
            'catalogs' => [
                'skills' => $this->normalizeRows($forms->trainingSkills()),
                'competency_groups' => $this->normalizeGroupedRows($competencies->groupedByCategory()),
                'competency_labels' => $competencies->categoryLabels(),
                'supervisor_options' => $this->supervisorCatalog(
                    null,
                    $phase,
                    $isOwner,
                    (int) ($resolved['supervisor_1'] ?? 0),
                    (int) ($resolved['supervisor_2'] ?? 0),
                ),
            ],
            'workflow' => [
                'state' => null,
                'timeline' => [],
                'trail' => [],
            ],
            'submission_window' => $submissionWindow,
            'submission_open' => $submissionWindow['open'],
            'readonly' => '',
            'midreadonly' => '',
            'endreadonly' => '',
            'is_owner' => $isOwner,
            'can_save' => $submissionWindow['open'] && $isOwner,
            'can_change_supervisors' => $isOwner,
            'can_approve' => false,
            'can_return' => false,
            'return_target' => null,
            'can_consent' => false,
            'midterm_exists' => false,
            'endterm_exists' => false,
            'ppa_approved' => false,
            'period_label' => PerformancePeriod::toLabel($periodSlug),
            'period_end_year' => $this->periodEndYear($periodSlug),
        ];
    }

    protected function buildExistingPayload(
        object $entry,
        PerformancePhase $phase,
        int $actorStaffId,
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance
    ): array {
        $contract = null;
        if ((int) ($entry->staff_contract_id ?? 0) > 0) {
            $contract = $contracts->forContract((int) $entry->staff_contract_id);
        }
        if (! $contract) {
            $contract = $contracts->forStaff((int) $entry->staff_id);
        }
        $contractMissing = $contract === null;
        if (! $contract) {
            $contract = $contracts->emptyContractStub((int) ($entry->staff_contract_id ?? 0));
        }

        $isOwner = $actorStaffId === (int) $entry->staff_id;
        $submissionWindow = $settings->submissionWindowStatus($phase);
        $submissionOpen = $submissionWindow['open'];
        $windowBlocksOwner = $isOwner && ! $submissionOpen;

        $readonly = $this->applySubmissionWindowLock(
            $this->computeReadonly($entry, PerformancePhase::Ppa, $actorStaffId, 'ppa'),
            $phase,
            PerformancePhase::Ppa,
            $windowBlocksOwner
        );
        $midreadonly = $this->applySubmissionWindowLock(
            $this->computeReadonly($entry, PerformancePhase::Midterm, $actorStaffId, 'midterm'),
            $phase,
            PerformancePhase::Midterm,
            $windowBlocksOwner
        );
        $endreadonly = $this->applySubmissionWindowLock(
            $this->computeReadonly($entry, PerformancePhase::Endterm, $actorStaffId, 'endterm'),
            $phase,
            PerformancePhase::Endterm,
            $windowBlocksOwner
        );

        $state = $workflow->resolveState($entry, $phase);
        $canAct = $workflow->canActorApprove($entry, $phase, $actorStaffId);
        $canConsent = $phase === PerformancePhase::Endterm
            && ($state['step'] ?? '') === 'employee_consent'
            && $actorStaffId === (int) $entry->staff_id;
        $phaseDraft = (int) ($entry->{$phase->draftStatusColumn()} ?? 1);
        $canReturn = $this->actorCanReturn($phaseDraft, $canConsent, $settings);
        $returnTarget = $canReturn ? ($phaseDraft === 2 ? 'draft' : 'employee') : null;
        $activeReadonly = match ($phase) {
            PerformancePhase::Ppa => $readonly,
            PerformancePhase::Midterm => $midreadonly,
            PerformancePhase::Endterm => $endreadonly,
        };

        $form = $this->formStateFromEntry($entry, $phase, $forms);
        $canChangeSupervisors = PerformanceFormAccess::canChangeSupervisors($entry, $phase, $actorStaffId);

        return [
            'phase' => $phase->value,
            'phase_label' => $phase->label(),
            'entry' => [
                'entry_id' => (string) $entry->entry_id,
                'staff_id' => (int) $entry->staff_id,
                'performance_period' => (string) $entry->performance_period,
                'staff_contract_id' => (int) ($entry->staff_contract_id ?? 0),
                'supervisor_id' => (int) ($entry->supervisor_id ?? 0),
                'supervisor2_id' => (int) ($entry->supervisor2_id ?? 0),
                'draft_status' => (int) ($entry->draft_status ?? 1),
                'midterm_draft_status' => (int) ($entry->midterm_draft_status ?? 1),
                'endterm_draft_status' => (int) ($entry->endterm_draft_status ?? 1),
            ],
            'form' => $form,
            'contract' => $this->contractWithSupervisorNames(
                (array) $contract,
                (int) ($form['supervisor_id'] ?? 0),
                (int) ($form['supervisor2_id'] ?? 0),
            ),
            'contract_missing' => $contractMissing,
            'catalogs' => [
                'skills' => $this->normalizeRows($forms->trainingSkills()),
                'competency_groups' => $this->normalizeGroupedRows($competencies->groupedByCategory()),
                'competency_labels' => $competencies->categoryLabels(),
                'supervisor_options' => $this->supervisorCatalog(
                    $entry,
                    $phase,
                    $canChangeSupervisors,
                    (int) ($form['supervisor_id'] ?? 0),
                    (int) ($form['supervisor2_id'] ?? 0),
                ),
            ],
            'workflow' => [
                'state' => $state,
                'timeline' => $workflow->timeline($entry, $phase),
                'trail' => $this->normalizeRows($approval->trail((string) $entry->entry_id, $phase)->all()),
            ],
            'submission_window' => $submissionWindow,
            'submission_open' => $submissionOpen,
            'readonly' => $readonly,
            'midreadonly' => $midreadonly,
            'endreadonly' => $endreadonly,
            'is_owner' => $isOwner,
            'can_save' => $isOwner && $submissionOpen && $activeReadonly === '',
            'can_change_supervisors' => $canChangeSupervisors,
            'can_approve' => $canAct && ! $canConsent,
            'can_return' => $canReturn,
            'return_target' => $returnTarget,
            'can_consent' => $canConsent,
            'midterm_exists' => $forms->midtermExists((string) $entry->entry_id),
            'endterm_exists' => $forms->endtermExists((string) $entry->entry_id),
            'ppa_approved' => $forms->isPpaApproved((string) $entry->entry_id),
            'period_label' => PerformancePeriod::toLabel((string) $entry->performance_period),
            'period_end_year' => $this->periodEndYear((string) $entry->performance_period),
        ];
    }

    /**
     * @param  array<string, mixed>  $contract
     * @return array<string, mixed>
     */
    protected function contractWithSupervisorNames(array $contract, int $supervisor1Id, int $supervisor2Id): array
    {
        $supervisors = app(SupervisorResolver::class);
        $firstId = $supervisor1Id ?: (int) ($contract['first_supervisor'] ?? 0);
        $secondId = $supervisor2Id ?: (int) ($contract['second_supervisor'] ?? 0);
        $contract['first_supervisor_name'] = $supervisors->staffName($firstId ?: null);
        $contract['second_supervisor_name'] = $supervisors->staffName($secondId ?: null);

        return $contract;
    }

    /**
     * @return list<array{staff_id: int, name: string}>
     */
    protected function supervisorCatalog(
        ?object $entry,
        PerformancePhase $phase,
        bool $canChange,
        int $supervisor1Id = 0,
        int $supervisor2Id = 0
    ): array {
        if (! $canChange) {
            return [];
        }

        return app(SupervisorResolver::class)->activeStaffOptions([
            $supervisor1Id,
            $supervisor2Id,
            $this->phaseSupervisorValue($entry, $phase, true),
            $this->phaseSupervisorValue($entry, $phase, false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyFormState(
        int $staffId,
        string $periodSlug,
        int $staffContractId,
        int $supervisorId,
        int $supervisor2Id,
        PpaFormService $forms
    ): array {
        return [
            'staff_id' => $staffId,
            'performance_period' => $periodSlug,
            'staff_contract_id' => $staffContractId,
            'supervisor_id' => $supervisorId,
            'supervisor2_id' => $supervisor2Id,
            'objectives' => $forms->decodeObjectives(null, 5),
            'training_recommended' => 'No',
            'required_skills' => [],
            'training_contributions' => '',
            'recommended_trainings' => '',
            'recommended_trainings_details' => '',
            'comments' => '',
            'midterm_comments' => '',
            'midterm_training_review' => '',
            'midterm_achievements' => '',
            'midterm_non_achievements' => '',
            'midterm_training_contributions' => '',
            'midterm_recommended_trainings' => '',
            'midterm_recommended_trainings_details' => '',
            'midterm_recommended_skills' => [],
            'midterm_competency' => [],
            'endterm_comments' => '',
            'endterm_training_review' => '',
            'endterm_achievements' => '',
            'endterm_non_achievements' => '',
            'endterm_training_contributions' => '',
            'endterm_recommended_trainings' => '',
            'endterm_recommended_trainings_details' => '',
            'endterm_recommended_skills' => [],
            'endterm_competency' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formStateFromEntry(object $entry, PerformancePhase $phase, PpaFormService $forms): array
    {
        $objectives = match ($phase) {
            PerformancePhase::Midterm => $entry->midterm_objectives ?? $entry->objectives,
            PerformancePhase::Endterm => $entry->endterm_objectives ?? $entry->objectives,
            default => $entry->objectives,
        };
        $rowCount = $phase === PerformancePhase::Ppa ? 5 : 10;

        $supervisorId = (int) ($entry->supervisor_id ?? 0);
        $supervisor2Id = (int) ($entry->supervisor2_id ?? 0);
        if ($phase === PerformancePhase::Midterm) {
            $supervisorId = (int) ($entry->midterm_supervisor_1 ?? $supervisorId);
            $supervisor2Id = (int) ($entry->midterm_supervisor_2 ?? $supervisor2Id);
        }
        if ($phase === PerformancePhase::Endterm) {
            $supervisorId = (int) ($entry->endterm_supervisor_1 ?? $supervisorId);
            $supervisor2Id = (int) ($entry->endterm_supervisor_2 ?? $supervisor2Id);
        }

        return [
            'staff_id' => (int) $entry->staff_id,
            'performance_period' => (string) $entry->performance_period,
            'staff_contract_id' => (int) ($entry->staff_contract_id ?? 0),
            'supervisor_id' => $supervisorId,
            'supervisor2_id' => $supervisor2Id,
            'objectives' => $forms->decodeObjectives($objectives, $rowCount),
            'training_recommended' => (string) ($entry->training_recommended ?? 'No'),
            'required_skills' => $forms->decodeSkillIds($entry->required_skills ?? null),
            'training_contributions' => (string) ($entry->training_contributions ?? ''),
            'recommended_trainings' => (string) ($entry->recommended_trainings ?? ''),
            'recommended_trainings_details' => (string) ($entry->recommended_trainings_details ?? ''),
            'comments' => '',
            'midterm_comments' => (string) ($entry->midterm_comments ?? ''),
            'midterm_training_review' => (string) ($entry->midterm_training_review ?? ''),
            'midterm_achievements' => (string) ($entry->midterm_achievements ?? ''),
            'midterm_non_achievements' => (string) ($entry->midterm_non_achievements ?? ''),
            'midterm_training_contributions' => (string) ($entry->midterm_training_contributions ?? ''),
            'midterm_recommended_trainings' => (string) ($entry->midterm_recommended_trainings ?? ''),
            'midterm_recommended_trainings_details' => (string) ($entry->midterm_recommended_trainings_details ?? ''),
            'midterm_recommended_skills' => $forms->decodeSkillIds($entry->midterm_recommended_skills ?? null),
            'midterm_competency' => $forms->decodeJson($entry->midterm_competency ?? null),
            'endterm_comments' => (string) ($entry->endterm_comments ?? ''),
            'endterm_training_review' => (string) ($entry->endterm_training_review ?? ''),
            'endterm_achievements' => (string) ($entry->endterm_achievements ?? ''),
            'endterm_non_achievements' => (string) ($entry->endterm_non_achievements ?? ''),
            'endterm_training_contributions' => (string) ($entry->endterm_training_contributions ?? ''),
            'endterm_recommended_trainings' => (string) ($entry->endterm_recommended_trainings ?? ''),
            'endterm_recommended_trainings_details' => (string) ($entry->endterm_recommended_trainings_details ?? ''),
            'endterm_recommended_skills' => $forms->decodeSkillIds($entry->endterm_recommended_skills ?? null),
            'endterm_competency' => $forms->decodeJson($entry->endterm_competency ?? null),
        ];
    }

    protected function resolvePhase(Request $request, string $default = 'ppa'): PerformancePhase
    {
        $raw = (string) ($request->input('phase') ?: $request->query('phase', $default));
        $phase = PerformancePhase::tryFrom($raw);

        if (! $phase) {
            throw ValidationException::withMessages([
                'phase' => ['The selected phase is invalid.'],
            ]);
        }

        return $phase;
    }

    protected function syncEntryForPhase(
        object $entry,
        PerformancePhase $phase,
        PerformanceWorkflowService $workflow,
        PpaFormService $forms
    ): object {
        $workflow->syncSupervisorsFromContract($entry, $phase);
        $entry = $forms->findEntry((string) $entry->entry_id) ?? $entry;
        app(PerformanceWorkflowCorrectionService::class)->finalizeIfReady($entry, $phase);

        return $forms->findEntry((string) $entry->entry_id) ?? $entry;
    }

    protected function authorizeEntryAccess(
        object $entry,
        PerformancePhase $phase,
        int $actorStaffId,
        PerformanceWorkflowService $workflow
    ): void {
        $isOwner = $actorStaffId === (int) $entry->staff_id;
        $supervisors = $workflow->supervisorIdsForPhase($entry, $phase);
        $isSupervisor = in_array($actorStaffId, array_filter([
            $supervisors['supervisor_1'] ?? null,
            $supervisors['supervisor_2'] ?? null,
        ]), true);

        if (! $isOwner && ! $isSupervisor && ! PerformanceFormAccess::canViewAnyEntry()) {
            abort(403, 'You are not allowed to access this entry.');
        }
    }

    protected function authorizeTargetStaffAccess(int $staffId, int $actorStaffId, SupervisorResolver $supervisors): void
    {
        if ($staffId === $actorStaffId) {
            return;
        }

        $resolved = $supervisors->fromLatestContract($staffId);
        $allowed = array_filter([
            $resolved['supervisor_1'] ?? null,
            $resolved['supervisor_2'] ?? null,
        ]);

        if (! in_array($actorStaffId, $allowed, true)) {
            abort(403, 'You are not allowed to act for this staff member.');
        }
    }

    protected function ensureOwnerCanEdit(
        PerformancePhase $phase,
        int $staffId,
        int $actorStaffId,
        PpaSettingsService $settings
    ): void {
        if ($staffId === $actorStaffId && ! $settings->isSubmissionOpen($phase)) {
            throw ValidationException::withMessages([
                'phase' => [$settings->submissionWindowStatus($phase)['message']],
            ]);
        }
    }

    protected function actorStaffId(): int
    {
        $session = $this->actorSession();

        return (int) ($session['staff_id'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function actorSession(): array
    {
        $user = auth()->user();

        if ($user instanceof PortalUser) {
            return $user->toSessionArray();
        }

        return session('user') ?? [];
    }

    protected function analyticsRestrictStaffId(): ?int
    {
        $session = $this->actorSession();
        $roleId = (int) ($session['role_id'] ?? $session['role'] ?? 0);

        return $roleId === 17 ? $this->actorStaffId() : null;
    }

    protected function allowSupervisorReturn(PpaSettingsService $settings): bool
    {
        return (bool) ($settings->settings()->allow_supervisor_return ?? true);
    }

    protected function actorCanReturn(int $phaseDraft, bool $canConsent, PpaSettingsService $settings): bool
    {
        if ($canConsent) {
            return false;
        }

        if (! $this->allowSupervisorReturn($settings) || ! PerformanceFormAccess::canReturnOverride()) {
            return false;
        }

        return in_array($phaseDraft, [0, 2], true);
    }

    protected function phaseSupervisorValue(?object $entry, PerformancePhase $phase, bool $first): ?int
    {
        if (! $entry) {
            return null;
        }

        return match ($phase) {
            PerformancePhase::Ppa => $first ? (int) ($entry->supervisor_id ?? 0) : (int) ($entry->supervisor2_id ?? 0),
            PerformancePhase::Midterm => $first ? (int) ($entry->midterm_supervisor_1 ?? 0) : (int) ($entry->midterm_supervisor_2 ?? 0),
            PerformancePhase::Endterm => $first ? (int) ($entry->endterm_supervisor_1 ?? 0) : (int) ($entry->endterm_supervisor_2 ?? 0),
        } ?: null;
    }

    protected function nullableInt(mixed $value, mixed $fallback = null): ?int
    {
        $candidate = $value;
        if ($candidate === null || $candidate === '') {
            $candidate = $fallback;
        }

        if ($candidate === null || $candidate === '') {
            return null;
        }

        return (int) $candidate;
    }

    protected function applySubmissionWindowLock(
        string $readonly,
        PerformancePhase $activePhase,
        PerformancePhase $formPhase,
        bool $blockOwner
    ): string {
        if ($blockOwner && $activePhase === $formPhase && $readonly === '') {
            return 'readonly disabled';
        }

        return $readonly;
    }

    protected function computeReadonly(?object $entry, PerformancePhase $phase, int $actorStaffId, string $context): string
    {
        if (! $entry) {
            return '';
        }

        $col = $phase->draftStatusColumn();
        $status = (int) ($entry->{$col} ?? 1);

        if ($phase === PerformancePhase::Midterm && empty($entry->midterm_created_at)) {
            return '';
        }
        if ($phase === PerformancePhase::Endterm && empty($entry->endterm_created_at)) {
            return '';
        }

        $isDraft = $status === 1;
        $isSubmitted = $status === 0;
        $isApproved = $status === 2;
        $isOwner = $actorStaffId === (int) $entry->staff_id;

        $sup1 = match ($context) {
            'midterm' => (int) ($entry->midterm_supervisor_1 ?? 0),
            'endterm' => (int) ($entry->endterm_supervisor_1 ?? 0),
            default => (int) ($entry->supervisor_id ?? 0),
        };
        $sup2 = match ($context) {
            'midterm' => (int) ($entry->midterm_supervisor_2 ?? 0),
            'endterm' => (int) ($entry->endterm_supervisor_2 ?? 0),
            default => (int) ($entry->supervisor2_id ?? 0),
        };
        $isSupervisor = in_array($actorStaffId, [$sup1, $sup2], true);

        if ($isApproved || ($isSubmitted && ! $isSupervisor) || ($isDraft && ! $isOwner)) {
            return 'readonly disabled';
        }

        return '';
    }

    /**
     * @param  list<object>|array<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    protected function normalizeRows(array $rows): array
    {
        return array_values(array_map(fn ($row) => (array) $row, $rows));
    }

    /**
     * @param  array<string, list<object>>  $groups
     * @return array<string, list<array<string, mixed>>>
     */
    protected function normalizeGroupedRows(array $groups): array
    {
        $normalized = [];
        foreach ($groups as $key => $rows) {
            $normalized[$key] = $this->normalizeRows($rows);
        }

        return $normalized;
    }

    protected function periodEndYear(string $periodSlug): int
    {
        if (preg_match('/\d{4}/', $periodSlug, $matches)) {
            return (int) $matches[0];
        }

        return (int) date('Y');
    }
}
