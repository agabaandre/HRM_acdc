<?php

namespace Modules\Performance\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Services\PerformanceApprovalService;
use Modules\Performance\Services\PerformanceService;
use Modules\Performance\Services\PpaFormService;
use Modules\Performance\Services\PpaSettingsService;

class PerformanceHubApiController extends Controller
{
    public function hub(
        Request $request,
        PerformanceService $performance,
        PerformanceApprovalService $approval,
        PpaSettingsService $ppaSettings,
        PpaFormService $forms
    ): JsonResponse {
        PortalPermission::authorize(74);

        $user = auth()->user();
        $session = $user instanceof PortalUser ? $user->toSessionArray() : (session('user') ?? []);
        $staffId = (int) ($session['staff_id'] ?? ($user instanceof PortalUser ? $user->auth_staff_id : 0));
        $roleId = (int) ($session['role_id'] ?? $session['role'] ?? ($user instanceof PortalUser ? $user->role : 0));
        $restrictStaff = $roleId === 17 ? $staffId : null;

        $period = (string) ($request->query('period') ?: $performance->currentPeriodSlug());
        $division = $request->filled('division_id') ? (int) $request->query('division_id') : null;
        $tab = (string) $request->query('tab', 'dashboard');

        $pending = $staffId > 0 ? $approval->pendingActionsFor($staffId) : collect();
        $pending = $pending->map(function ($row) use ($performance) {
            $arr = (array) $row;
            $entryId = (string) ($arr['entry_id'] ?? '');
            $sid = (int) ($arr['staff_id'] ?? 0);
            $type = (string) ($arr['approval_type'] ?? 'ppa');
            $phase = PerformancePhase::tryFrom($type) ?? PerformancePhase::Ppa;
            if ($entryId !== '' && $sid > 0) {
                $arr['form_url'] = '/performance/form/'.$phase->value.'/'.$entryId.'/'.$sid;
            }
            $arr['approval_type_label'] = $phase->label();

            return $arr;
        })->values()->all();

        $ppaWindowOpen = $ppaSettings->isSubmissionOpen(PerformancePhase::Ppa);
        $midtermWindowOpen = $ppaSettings->isSubmissionOpen(PerformancePhase::Midterm);
        $endtermWindowOpen = $ppaSettings->isSubmissionOpen(PerformancePhase::Endterm);
        $selfActions = $this->selfActions(
            $staffId,
            $period,
            $forms,
            $ppaWindowOpen,
            $midtermWindowOpen,
            $endtermWindowOpen
        );

        $payload = [
            'summary' => $performance->dashboardSummary($division, $period, $restrictStaff),
            'periods' => $performance->periodOptions(),
            'period' => $period,
            'divisions' => DB::table('divisions')->orderBy('division_name')->get(['division_id', 'division_name']),
            'pending' => $pending,
            'pending_count' => count($pending),
            'workflow_summary' => [
                'ppa' => $ppaSettings->workflowSummaryLine(PerformancePhase::Ppa),
                'midterm' => $ppaSettings->workflowSummaryLine(PerformancePhase::Midterm),
                'endterm' => $ppaSettings->workflowSummaryLine(PerformancePhase::Endterm),
            ],
            'submission_windows' => $ppaSettings->allSubmissionWindowStatuses(),
            'ppa_submission_open' => $ppaWindowOpen,
            'midterm_submission_open' => $midtermWindowOpen,
            'endterm_submission_open' => $endtermWindowOpen,
            'create_ppa_url' => $selfActions['create_ppa_url'],
            'self_actions' => $selfActions,
            'my_ppas' => null,
        ];

        if ($tab === 'my' && $staffId > 0) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(50, max(10, (int) $request->query('per_page', 20)));
            $paginator = $performance->paginateMyPpas($staffId, $period, $perPage, $page);
            $items = collect($paginator->items())->map(function ($row) use ($performance) {
                $arr = (array) $row;
                $entryId = (string) ($arr['entry_id'] ?? '');
                $sid = (int) ($arr['staff_id'] ?? 0);
                if ($entryId !== '' && $sid > 0) {
                    $arr['form_url'] = '/performance/form/ppa/'.$entryId.'/'.$sid;
                    $arr['midterm_url'] = '/performance/form/midterm/'.$entryId.'/'.$sid;
                    $arr['endterm_url'] = '/performance/form/endterm/'.$entryId.'/'.$sid;
                    $arr['print_url'] = url('/api/v1/performance/entries/'.$entryId.'/print?phase=ppa');
                }
                $arr['draft_status_label'] = $performance->draftStatusLabel((int) ($arr['draft_status'] ?? 0));
                $arr['midterm_status_label'] = $performance->midtermStatusLabel(
                    isset($arr['midterm_draft_status']) ? (int) $arr['midterm_draft_status'] : null
                );
                $arr['endterm_status_label'] = $performance->endtermStatusLabel(
                    isset($arr['endterm_draft_status']) ? (int) $arr['endterm_draft_status'] : null
                );

                return $arr;
            })->values()->all();

            $payload['my_ppas'] = [
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ];
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * Personal create/open actions for the selected period (Livewire ppa-tabs parity).
     *
     * @return array<string, mixed>
     */
    protected function selfActions(
        int $staffId,
        string $period,
        PpaFormService $forms,
        bool $ppaWindowOpen,
        bool $midtermWindowOpen,
        bool $endtermWindowOpen
    ): array {
        if ($staffId < 1) {
            return [
                'staff_id' => 0,
                'ppa_exists' => false,
                'ppa_approved' => false,
                'create_ppa_url' => null,
                'current_ppa_url' => null,
                'midterm_url' => null,
                'endterm_url' => null,
                'midterm_label' => 'Midterm',
                'endterm_label' => 'Endterm',
                'show_create_ppa' => false,
                'show_current_ppa' => false,
                'show_midterm' => false,
                'show_endterm' => false,
            ];
        }

        $entry = $forms->findForPeriod($staffId, $period);
        $entryId = $entry ? (string) $entry->entry_id : $forms->entryIdFor($staffId, $period);
        $ppaExists = $entry !== null;
        $ppaApproved = $ppaExists && (int) ($entry->draft_status ?? 0) === 2;
        $midtermExists = $ppaExists && $forms->midtermExists($entryId);
        $endtermExists = $ppaExists && $forms->endtermExists($entryId);

        $base = '/performance/form/';
        $currentPpa = $ppaExists ? $base.'ppa/'.$entryId.'/'.$staffId : null;
        $midtermUrl = $ppaExists ? $base.'midterm/'.$entryId.'/'.$staffId : null;
        $endtermUrl = $ppaExists ? $base.'endterm/'.$entryId.'/'.$staffId : null;

        return [
            'staff_id' => $staffId,
            'entry_id' => $entryId,
            'ppa_exists' => $ppaExists,
            'ppa_approved' => $ppaApproved,
            'midterm_exists' => $midtermExists,
            'endterm_exists' => $endtermExists,
            'create_ppa_url' => (! $ppaExists && $ppaWindowOpen) ? '/performance/create' : null,
            'current_ppa_url' => $currentPpa,
            'midterm_url' => $midtermUrl,
            'endterm_url' => $endtermUrl,
            'midterm_label' => $midtermExists ? 'Current Midterm' : 'Create Midterm',
            'endterm_label' => $endtermExists ? 'Current Endterm' : 'Create Endterm',
            'show_create_ppa' => ! $ppaExists && $ppaWindowOpen,
            'show_current_ppa' => $ppaExists,
            // Match Livewire: open mid/end whenever a PPA exists for the period.
            'show_midterm' => $ppaExists,
            'show_endterm' => $ppaExists,
            'midterm_window_open' => $midtermWindowOpen,
            'endterm_window_open' => $endtermWindowOpen,
        ];
    }
}
