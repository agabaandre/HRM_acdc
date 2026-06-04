<?php

namespace Modules\Performance\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Services\CompetencyService;
use Modules\Performance\Services\PerformanceApprovalService;
use Modules\Performance\Services\PerformanceService;
use Modules\Performance\Services\PerformanceWorkflowService;
use Modules\Performance\Services\PpaContractService;
use Modules\Performance\Services\PpaFormService;
use Modules\Performance\Services\PpaSettingsService;
use Modules\Performance\Services\SupervisorResolver;
use Modules\Performance\Support\PerformancePeriod;

#[Layout('core::layouts.app')]
class PerformanceForm extends Component
{
    use ChecksPortalPermission;

    public string $phaseKey = 'ppa';

    public ?string $entryId = null;

    public int $staffId;

    public string $performancePeriod = '';

    public int $staffContractId = 0;

    public int $supervisorId = 0;

    public int $supervisor2Id = 0;

    /** @var array<int, array<string, mixed>> */
    public array $objectives = [];

    public string $trainingRecommended = 'No';

    /** @var list<int|string> */
    public array $requiredSkills = [];

    public string $trainingContributions = '';

    public string $recommendedTrainings = '';

    public string $recommendedTrainingsDetails = '';

    public string $comments = '';

    public string $midtermComments = '';

    public string $midtermTrainingReview = '';

    public string $midtermAchievements = '';

    public string $midtermNonAchievements = '';

    public string $midtermTrainingContributions = '';

    public string $midtermRecommendedTrainings = '';

    public string $midtermRecommendedTrainingsDetails = '';

    /** @var list<int|string> */
    public array $midtermRecommendedSkills = [];

    /** @var array<string, mixed> */
    public array $midtermCompetency = [];

    public string $endtermComments = '';

    public string $endtermTrainingReview = '';

    public string $endtermAchievements = '';

    public string $endtermNonAchievements = '';

    public string $endtermTrainingContributions = '';

    public string $endtermRecommendedTrainings = '';

    public string $endtermRecommendedTrainingsDetails = '';

    /** @var list<int|string> */
    public array $endtermRecommendedSkills = [];

    /** @var array<string, mixed> */
    public array $endtermCompetency = [];

    public string $approvalComments = '';

    public string $approvalAction = '';

    public function mount(): void
    {
        $this->authorizePortal(74);
        $forms = app(PpaFormService::class);

        $route = request()->route();
        $phase = (string) ($route?->parameter('phase') ?? 'ppa');
        $entryId = $route?->parameter('entryId');
        $staffIdParam = $route?->parameter('staffId');
        $period = request()->query('period');

        $this->phaseKey = in_array($phase, ['ppa', 'midterm', 'endterm'], true) ? $phase : 'ppa';
        $actorStaffId = (int) session('user.staff_id');
        $this->staffId = $staffIdParam !== null && $staffIdParam !== '' ? (int) $staffIdParam : $actorStaffId;

        if ($this->phaseKey === 'ppa' && $entryId === null) {
            $ppaSettings = app(PpaSettingsService::class);
            if (! $ppaSettings->isSubmissionOpen(PerformancePhase::Ppa)) {
                session()->flash('error', $ppaSettings->submissionWindowStatus(PerformancePhase::Ppa)['message']);
                $this->redirect(route('performance.index'), navigate: true);

                return;
            }

            $periodSlug = PerformancePeriod::toSlug($period) ?? PerformancePeriod::currentSlug();
            $existing = $forms->findForPeriod($this->staffId, $periodSlug);
            if ($existing) {
                $this->redirect(route('performance.ppa.form', [
                    'entryId' => $existing->entry_id,
                    'staffId' => $this->staffId,
                ]), navigate: true);

                return;
            }
            $this->performancePeriod = $periodSlug;
            $this->bootstrapNewPpa($forms);

            return;
        }

        if (! $entryId) {
            abort(404);
        }

        $this->entryId = $entryId;
        $entry = $forms->findEntry($entryId);
        if (! $entry || (int) $entry->staff_id !== $this->staffId) {
            abort(404);
        }

        $this->hydrateFromEntry($entry, $forms);
    }

    public function saveDraft(PpaFormService $forms): void
    {
        $this->persist('draft', $forms);
    }

    public function saveSubmit(PpaFormService $forms): void
    {
        $this->persist('submit', $forms);
    }

    public function submitApprove(PerformanceApprovalService $approval): void
    {
        $this->validate(['approvalComments' => 'nullable|string|max:5000']);
        if (! $this->entryId) {
            return;
        }
        try {
            $approval->approve(
                $this->entryId,
                $this->phase(),
                (int) session('user.staff_id'),
                $this->approvalComments,
            );
            session()->flash('success', 'Approval recorded.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitReturn(PerformanceApprovalService $approval): void
    {
        $this->validate(['approvalComments' => 'required|string|max:5000']);
        if (! $this->entryId) {
            return;
        }
        try {
            $approval->returnForRevision(
                $this->entryId,
                $this->phase(),
                (int) session('user.staff_id'),
                $this->approvalComments,
            );
            session()->flash('success', 'Returned for revision.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitConsent(PerformanceApprovalService $approval): void
    {
        $this->validate(['approvalComments' => 'nullable|string|max:5000']);
        if (! $this->entryId) {
            return;
        }
        try {
            $approval->recordEmployeeConsent(
                $this->entryId,
                (int) session('user.staff_id'),
                $this->approvalComments,
                true,
            );
            session()->flash('success', 'Consent recorded.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(
        PpaFormService $forms,
        PpaContractService $contracts,
        PpaSettingsService $settings,
        CompetencyService $competencies,
        PerformanceWorkflowService $workflow,
        PerformanceApprovalService $approval,
        PerformanceService $performance,
        SupervisorResolver $supervisors,
    ) {
        $entry = $this->entryId ? $forms->findEntry($this->entryId) : null;
        $phase = $this->phase();

        if ($entry) {
            $workflow->syncSupervisorsFromContract($entry, $phase);
            $entry = $forms->findEntry($this->entryId);
        }

        $contract = null;
        if ($entry && $entry->staff_contract_id) {
            $contract = $contracts->forContract((int) $entry->staff_contract_id);
        }
        if (! $contract) {
            $contract = $contracts->forStaff($this->staffId);
        }
        $contractMissing = $contract === null;
        if ($contractMissing) {
            $contract = $contracts->emptyContractStub($this->staffContractId);
        }

        $actorStaffId = (int) session('user.staff_id');
        $isOwner = ! $entry || $actorStaffId === (int) ($entry->staff_id ?? $this->staffId);
        $submissionWindow = $settings->submissionWindowStatus($phase);
        $submissionOpen = $submissionWindow['open'];
        $windowBlocksOwner = $isOwner && ! $submissionOpen;

        $readonly = $this->applySubmissionWindowLock(
            $this->computeReadonly($entry, $phase, $actorStaffId, 'ppa'),
            $phase,
            PerformancePhase::Ppa,
            $windowBlocksOwner,
        );
        $midreadonly = $this->applySubmissionWindowLock(
            $this->computeReadonly($entry, PerformancePhase::Midterm, $actorStaffId, 'midterm'),
            $phase,
            PerformancePhase::Midterm,
            $windowBlocksOwner,
        );
        $endreadonly = $this->applySubmissionWindowLock(
            $this->computeReadonly($entry, PerformancePhase::Endterm, $actorStaffId, 'endterm'),
            $phase,
            PerformancePhase::Endterm,
            $windowBlocksOwner,
        );

        $state = $entry ? $workflow->resolveState($entry, $phase) : null;
        $canAct = $entry && $state ? $workflow->canActorApprove($entry, $phase, $actorStaffId) : false;

        return view('performance::livewire.performance-form', [
            'phaseKey' => $this->phaseKey,
            'entry' => $entry,
            'phase' => $phase,
            'contract' => $contract,
            'contractMissing' => $contractMissing,
            'readonly' => $readonly,
            'midreadonly' => $midreadonly,
            'endreadonly' => $endreadonly,
            'skills' => $forms->trainingSkills(),
            'competencyGroups' => $competencies->groupedByCategory(),
            'competencyLabels' => $competencies->categoryLabels(),
            'ppaSettings' => $settings->settings(),
            'trail' => $this->entryId ? $approval->trail($this->entryId, $phase) : collect(),
            'timeline' => $entry ? $workflow->timeline($entry, $phase) : [],
            'state' => $state,
            'canAct' => $canAct,
            'isOwner' => $isOwner,
            'submissionWindow' => $submissionWindow,
            'submissionOpen' => $submissionOpen,
            'canEmployeeSave' => $submissionOpen && $isOwner,
            'supervisors' => $supervisors,
            'performance' => $performance,
            'periodLabel' => PerformancePeriod::toLabel($this->performancePeriod ?: PerformancePeriod::currentSlug()),
            'periodEndYear' => $this->periodEndYear(),
            'midtermExists' => $this->entryId ? $forms->midtermExists($this->entryId) : false,
            'endtermExists' => $this->entryId ? $forms->endtermExists($this->entryId) : false,
            'ppaApproved' => $this->entryId ? $forms->isPpaApproved($this->entryId) : false,
            'currentEntryId' => $forms->entryIdFor($this->staffId, PerformancePeriod::currentSlug()),
            'pendingCount' => $approval->pendingActionsFor($actorStaffId)->count(),
        ]);
    }

    protected function bootstrapNewPpa(PpaFormService $forms): void
    {
        $resolved = app(SupervisorResolver::class)->fromLatestContract($this->staffId);
        $this->staffContractId = (int) ($resolved['contract_id'] ?? 0);
        $this->supervisorId = (int) ($resolved['supervisor_1'] ?? 0);
        $this->supervisor2Id = (int) ($resolved['supervisor_2'] ?? 0);
        $this->objectives = $forms->decodeObjectives(null, 5);
        $this->trainingRecommended = 'No';
    }

    protected function hydrateFromEntry(object $entry, PpaFormService $forms): void
    {
        $this->staffId = (int) $entry->staff_id;
        $this->performancePeriod = (string) $entry->performance_period;
        $this->staffContractId = (int) ($entry->staff_contract_id ?? 0);
        $this->supervisorId = (int) ($entry->supervisor_id ?? 0);
        $this->supervisor2Id = (int) ($entry->supervisor2_id ?? 0);

        $rowCount = $this->phaseKey === 'ppa' ? 5 : 10;
        $objectiveField = match ($this->phaseKey) {
            'midterm' => $entry->midterm_objectives ?? $entry->objectives,
            'endterm' => $entry->endterm_objectives ?? $entry->objectives,
            default => $entry->objectives,
        };
        $this->objectives = $forms->decodeObjectives($objectiveField, $rowCount);

        $this->trainingRecommended = (string) ($entry->training_recommended ?? 'No');
        $this->requiredSkills = $forms->decodeSkillIds($entry->required_skills ?? null);
        $this->trainingContributions = (string) ($entry->training_contributions ?? '');
        $this->recommendedTrainings = (string) ($entry->recommended_trainings ?? '');
        $this->recommendedTrainingsDetails = (string) ($entry->recommended_trainings_details ?? '');

        $this->midtermComments = (string) ($entry->midterm_comments ?? '');
        $this->midtermTrainingReview = (string) ($entry->midterm_training_review ?? '');
        $this->midtermAchievements = (string) ($entry->midterm_achievements ?? '');
        $this->midtermNonAchievements = (string) ($entry->midterm_non_achievements ?? '');
        $this->midtermTrainingContributions = (string) ($entry->midterm_training_contributions ?? '');
        $this->midtermRecommendedTrainings = (string) ($entry->midterm_recommended_trainings ?? '');
        $this->midtermRecommendedTrainingsDetails = (string) ($entry->midterm_recommended_trainings_details ?? '');
        $this->midtermRecommendedSkills = $forms->decodeSkillIds($entry->midterm_recommended_skills ?? null);
        $this->midtermCompetency = $forms->decodeJson($entry->midterm_competency ?? null);

        $this->endtermComments = (string) ($entry->endterm_comments ?? '');
        $this->endtermTrainingReview = (string) ($entry->endterm_training_review ?? '');
        $this->endtermAchievements = (string) ($entry->endterm_achievements ?? '');
        $this->endtermNonAchievements = (string) ($entry->endterm_non_achievements ?? '');
        $this->endtermTrainingContributions = (string) ($entry->endterm_training_contributions ?? '');
        $this->endtermRecommendedTrainings = (string) ($entry->endterm_recommended_trainings ?? '');
        $this->endtermRecommendedTrainingsDetails = (string) ($entry->endterm_recommended_trainings_details ?? '');
        $this->endtermRecommendedSkills = $forms->decodeSkillIds($entry->endterm_recommended_skills ?? null);
        $this->endtermCompetency = $forms->decodeJson($entry->endterm_competency ?? null);

        if ($this->phaseKey === 'midterm') {
            $this->supervisorId = (int) ($entry->midterm_supervisor_1 ?? $this->supervisorId);
            $this->supervisor2Id = (int) ($entry->midterm_supervisor_2 ?? $this->supervisor2Id);
        }
        if ($this->phaseKey === 'endterm') {
            $this->supervisorId = (int) ($entry->endterm_supervisor_1 ?? $this->supervisorId);
            $this->supervisor2Id = (int) ($entry->endterm_supervisor_2 ?? $this->supervisor2Id);
        }
    }

    protected function persist(string $submitAction, PpaFormService $forms): void
    {
        $actorStaffId = (int) session('user.staff_id');
        $settings = app(PpaSettingsService::class);

        if ($actorStaffId === $this->staffId && ! $settings->isSubmissionOpen($this->phase())) {
            session()->flash('error', $settings->submissionWindowStatus($this->phase())['message']);

            return;
        }

        $payload = [
            'staff_id' => $this->staffId,
            'staff_contract_id' => $this->staffContractId,
            'performance_period' => $this->performancePeriod,
            'entry_id' => $this->entryId,
            'supervisor_id' => $this->supervisorId,
            'supervisor2_id' => $this->supervisor2Id,
            'objectives' => $this->objectives,
            'training_recommended' => $this->trainingRecommended,
            'required_skills' => $this->requiredSkills,
            'training_contributions' => $this->trainingContributions,
            'recommended_trainings' => $this->recommendedTrainings,
            'recommended_trainings_details' => $this->recommendedTrainingsDetails,
            'comments' => $this->comments,
            'midterm_comments' => $this->midtermComments,
            'midterm_training_review' => $this->midtermTrainingReview,
            'midterm_achievements' => $this->midtermAchievements,
            'midterm_non_achievements' => $this->midtermNonAchievements,
            'midterm_training_contributions' => $this->midtermTrainingContributions,
            'midterm_recommended_trainings' => $this->midtermRecommendedTrainings,
            'midterm_recommended_trainings_details' => $this->midtermRecommendedTrainingsDetails,
            'midterm_recommended_skills' => $this->midtermRecommendedSkills,
            'midterm_competency' => $this->midtermCompetency,
            'endterm_comments' => $this->endtermComments,
            'endterm_training_review' => $this->endtermTrainingReview,
            'endterm_achievements' => $this->endtermAchievements,
            'endterm_non_achievements' => $this->endtermNonAchievements,
            'endterm_training_contributions' => $this->endtermTrainingContributions,
            'endterm_recommended_trainings' => $this->endtermRecommendedTrainings,
            'endterm_recommended_trainings_details' => $this->endtermRecommendedTrainingsDetails,
            'endterm_recommended_skills' => $this->endtermRecommendedSkills,
            'endterm_competency' => $this->endtermCompetency,
            'endterm_submit_action' => $submitAction,
            'midterm_submit_action' => $submitAction,
        ];

        try {
            if ($this->phaseKey === 'ppa') {
                $entryId = $forms->savePpa($payload, $actorStaffId, $submitAction);
                $this->entryId = $entryId;
                session()->flash('success', $submitAction === 'submit' ? 'PPA submitted.' : 'Draft saved.');
                $this->redirect(route('performance.ppa.form', ['entryId' => $entryId, 'staffId' => $this->staffId]), navigate: true);

                return;
            }
            if ($this->phaseKey === 'midterm') {
                $forms->saveMidterm($payload, $actorStaffId, $submitAction);
                session()->flash('success', $submitAction === 'submit' ? 'Midterm submitted.' : 'Midterm draft saved.');
            } else {
                $forms->saveEndterm($payload, $actorStaffId, $submitAction);
                session()->flash('success', $submitAction === 'submit' ? 'Endterm submitted.' : 'Endterm draft saved.');
            }
            $this->redirect($this->formRoute(), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    protected function formRoute(): string
    {
        return match ($this->phaseKey) {
            'midterm' => route('performance.midterm.form', ['entryId' => $this->entryId, 'staffId' => $this->staffId]),
            'endterm' => route('performance.endterm.form', ['entryId' => $this->entryId, 'staffId' => $this->staffId]),
            default => route('performance.ppa.form', ['entryId' => $this->entryId, 'staffId' => $this->staffId]),
        };
    }

    protected function phase(): PerformancePhase
    {
        return PerformancePhase::from($this->phaseKey);
    }

    protected function periodEndYear(): int
    {
        if (preg_match('/\d{4}/', $this->performancePeriod, $m)) {
            return (int) $m[0];
        }

        return (int) date('Y');
    }

    protected function applySubmissionWindowLock(string $readonly, PerformancePhase $activePhase, PerformancePhase $formPhase, bool $blockOwner): string
    {
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
}
