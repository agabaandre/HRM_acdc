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
use Modules\Performance\Services\PpaSettingsService;

class PerformanceHubApiController extends Controller
{
    public function hub(
        Request $request,
        PerformanceService $performance,
        PerformanceApprovalService $approval,
        PpaSettingsService $ppaSettings
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
            'ppa_submission_open' => $ppaSettings->isSubmissionOpen(PerformancePhase::Ppa),
            'create_ppa_url' => '/performance/create',
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
                    $arr['print_url'] = url('/api/v1/performance/entries/'.$entryId.'/print.pdf?phase=ppa');
                }
                $arr['draft_status_label'] = $performance->draftStatusLabel((int) ($arr['draft_status'] ?? 0));
                $arr['midterm_status_label'] = $performance->midtermStatusLabel(
                    isset($arr['midterm_draft_status']) ? (int) $arr['midterm_draft_status'] : null
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
}
