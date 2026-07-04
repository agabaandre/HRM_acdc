<?php

namespace App\Http\Controllers;

use App\Services\DivisionWeeklyBriefGate;
use App\Services\PendingApprovalsService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $pendingCounts = $this->resolvePendingCounts();

        return view('home', [
            'pageConfig' => [
                'userName' => (string) user_session('name', ''),
                'totalPending' => array_sum($pendingCounts),
                'showWeeklyBrief' => DivisionWeeklyBriefGate::canAccessModule(),
                'modules' => $this->buildModules($pendingCounts),
            ],
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function resolvePendingCounts(): array
    {
        $counts = [
            'matrices' => 0,
            'non-travel' => 0,
            'special-memo' => 0,
            'service-requests' => 0,
            'request-arf' => 0,
            'single-memo' => 0,
            'change-request' => 0,
        ];

        try {
            $summaryStats = (new PendingApprovalsService([
                'staff_id' => (int) user_session('staff_id'),
                'division_id' => user_session('division_id'),
                'permissions' => user_session('permissions', []),
                'name' => user_session('name', ''),
                'email' => user_session('email', ''),
                'base_url' => config('app.url'),
            ]))->getSummaryStats();

            $byCategory = $summaryStats['by_category'] ?? [];
            $counts['matrices'] = (int) ($byCategory['Matrix'] ?? 0);
            $counts['non-travel'] = (int) ($byCategory['Non-Travel Memo'] ?? 0);
            $counts['special-memo'] = (int) ($byCategory['Special Memo'] ?? 0);
            $counts['service-requests'] = (int) ($byCategory['Service Request'] ?? 0);
            $counts['request-arf'] = (int) ($byCategory['ARF'] ?? 0);
            $counts['single-memo'] = (int) ($byCategory['Single Memo'] ?? 0);
            $counts['change-request'] = (int) ($byCategory['Change Request'] ?? 0);
        } catch (\Throwable $e) {
            // Keep zeros if dashboard summary fails.
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $pendingCounts
     * @return list<array<string, mixed>>
     */
    private function buildModules(array $pendingCounts): array
    {
        $modules = [
            [
                'key' => 'matrices',
                'title' => 'Quarterly Travel Matrix (QM)',
                'description' => 'Plan and track quarterly travel for all staff.',
                'icon' => 'mdi-calendar-range',
                'accent' => '#3498db',
                'openUrl' => route('matrices.index'),
                'openLabel' => 'Open',
                'openIcon' => 'mdi-open-in-new',
                'pendingUrl' => route('matrices.pending-approvals'),
                'pendingCount' => $pendingCounts['matrices'],
            ],
            [
                'key' => 'non-travel',
                'title' => 'Non-Travel Memo (NT)',
                'description' => 'Manage activities that are not related to travel logistics.',
                'icon' => 'mdi-file-document-outline',
                'accent' => '#9b59b6',
                'openUrl' => route('non-travel.index'),
                'openLabel' => 'Open',
                'openIcon' => 'mdi-open-in-new',
                'pendingUrl' => route('non-travel.pending-approvals'),
                'pendingCount' => $pendingCounts['non-travel'],
            ],
            [
                'key' => 'special-memo',
                'title' => 'Special Memo (SPM)',
                'description' => 'Create and send special memos for specific activities.',
                'icon' => 'mdi-email-newsletter',
                'accent' => '#e67e22',
                'openUrl' => url('special-memo'),
                'openLabel' => 'Open',
                'openIcon' => 'mdi-open-in-new',
                'pendingUrl' => route('special-memo.pending-approvals'),
                'pendingCount' => $pendingCounts['special-memo'],
            ],
            [
                'key' => 'service-requests',
                'title' => 'Request for Services (RQS)',
                'description' => 'Submit requests for tickets, DSA, procurement, or imprest.',
                'icon' => 'mdi-tools',
                'accent' => '#2ecc71',
                'openUrl' => url('service-requests'),
                'openLabel' => 'Open',
                'openIcon' => 'mdi-open-in-new',
                'pendingUrl' => url('service-requests/pending-approvals'),
                'pendingCount' => $pendingCounts['service-requests'],
            ],
            [
                'key' => 'request-arf',
                'title' => 'Request for ARF',
                'description' => 'Submit your Activity Request Form for approvals.',
                'icon' => 'mdi-file-sign',
                'accent' => '#e74c3c',
                'openUrl' => url('request-arf'),
                'openLabel' => 'Open',
                'openIcon' => 'mdi-open-in-new',
                'pendingUrl' => route('request-arf.pending-approvals'),
                'pendingCount' => $pendingCounts['request-arf'],
            ],
            [
                'key' => 'single-memo',
                'title' => 'Single Memo (SM)',
                'description' => 'View submitted single memos.',
                'icon' => 'mdi-file-document',
                'accent' => '#d97706',
                'openUrl' => route('activities.single-memos.index'),
                'openLabel' => 'Open',
                'openIcon' => 'mdi-open-in-new',
                'pendingUrl' => route('activities.single-memos.pending-approvals'),
                'pendingCount' => $pendingCounts['single-memo'],
            ],
            [
                'key' => 'change-request',
                'title' => 'Change Request (CR)',
                'description' => 'Request changes to existing memos and activities.',
                'icon' => 'mdi-file-edit-outline',
                'accent' => '#8e44ad',
                'openUrl' => route('change-requests.index'),
                'openLabel' => 'View all',
                'openIcon' => 'mdi-format-list-bulleted',
                'pendingUrl' => route('change-requests.pending-approvals'),
                'pendingCount' => $pendingCounts['change-request'],
            ],
            [
                'key' => 'reports',
                'title' => 'Reports & Weekly brief',
                'description' => 'View and download performance reports.',
                'icon' => 'mdi-chart-bar',
                'accent' => '#34495e',
                'openUrl' => url('reports'),
                'openLabel' => 'All reports',
                'openIcon' => 'mdi-chart-box-outline',
                'pendingUrl' => null,
                'pendingCount' => 0,
                'extraActions' => DivisionWeeklyBriefGate::canAccessModule()
                    ? [[
                        'label' => 'Open weekly brief',
                        'icon' => 'mdi-calendar-week',
                        'url' => route('weekly-briefing.index'),
                    ]]
                    : [],
            ],
        ];

        return $modules;
    }
}
