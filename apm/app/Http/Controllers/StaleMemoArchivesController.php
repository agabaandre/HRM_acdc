<?php

namespace App\Http\Controllers;

use App\Models\StaleMemoArchive;
use App\Services\BudgetCommitmentSettings;
use App\Services\StaleDraftArchiveSchedule;
use App\Services\StaleDraftMemosService;
use App\Services\StaleMemoArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaleMemoArchivesController extends Controller
{
    public function userIndex(Request $request): View
    {
        $staffId = (int) user_session('staff_id');
        if ($staffId <= 0) {
            abort(403, 'You must be signed in to view stale drafts.');
        }

        $settings = new BudgetCommitmentSettings();
        $schedule = new StaleDraftArchiveSchedule();
        $staleService = new StaleDraftMemosService($settings);

        $pendingStale = $staleService->getStaleDraftsVisibleToUser($staffId);
        foreach ($pendingStale as &$item) {
            $updatedAt = ! empty($item['updated_at']) ? \Carbon\Carbon::parse($item['updated_at']) : null;
            $item['scheduled_archive_at'] = $updatedAt
                ? $schedule->scheduledArchiveAt($updatedAt)->format('Y-m-d H:i')
                : null;
            $item['can_archive'] = can_archive_stale_draft_memo($item);
        }
        unset($item);

        return view('stale-drafts.index', [
            'pendingStale' => $pendingStale,
            'draftMaxAgeMonths' => $settings->draftMaxAgeMonths(),
            'autoArchiveEnabled' => $settings->staleDraftAutoArchiveEnabled(),
            'nextWeeklyRun' => $schedule->nextWeeklyRun()->format('Y-m-d H:i'),
            'weeklyRunLabel' => 'Mondays at ' . str_pad((string) $schedule->weeklyRunHour(), 2, '0', STR_PAD_LEFT) . ':00',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getIndexData(Request $request): array
    {
        if (! in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to stale memo archives');
        }

        $settings = new BudgetCommitmentSettings();
        $schedule = new StaleDraftArchiveSchedule();
        $staleService = new StaleDraftMemosService($settings);

        $pendingStale = $staleService->getAllStaleDrafts();
        foreach ($pendingStale as &$item) {
            $updatedAt = ! empty($item['updated_at']) ? \Carbon\Carbon::parse($item['updated_at']) : null;
            $item['scheduled_archive_at'] = $updatedAt
                ? $schedule->scheduledArchiveAt($updatedAt)->format('Y-m-d H:i')
                : null;
            $item['can_archive'] = true;
        }
        unset($item);

        $archivedQuery = StaleMemoArchive::query()->orderByDesc('archived_at');
        $archived = $archivedQuery->paginate(25)->withQueryString();

        return [
            'pendingStale' => $pendingStale,
            'archived' => $archived,
            'draftMaxAgeMonths' => $settings->draftMaxAgeMonths(),
            'autoArchiveEnabled' => $settings->staleDraftAutoArchiveEnabled(),
            'nextWeeklyRun' => $schedule->nextWeeklyRun()->format('Y-m-d H:i'),
            'weeklyRunLabel' => 'Mondays at ' . str_pad((string) $schedule->weeklyRunHour(), 2, '0', STR_PAD_LEFT) . ':00',
        ];
    }

    public function archiveOne(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'memo_type' => 'required|string|in:activity,single_memo,special_memo,non_travel_memo,change_request',
            'memo_id' => 'required|integer|min:1',
        ]);

        $staleService = new StaleDraftMemosService();
        $item = $staleService->findStaleDraftItem($validated['memo_type'], (int) $validated['memo_id']);
        if ($item === null) {
            return $this->redirectAfterArchive($request)
                ->with('error', 'Stale draft not found or no longer eligible for archive.');
        }

        if (! can_archive_stale_draft_memo($item)) {
            abort(403, 'You are not allowed to archive this stale draft.');
        }

        $staffId = user_session('staff_id');
        $archivedBy = $staffId !== null && $staffId !== '' ? (int) $staffId : null;

        $archived = app(StaleMemoArchiveService::class)->archiveMemoItem($item, 'manual', $archivedBy);
        if (! $archived) {
            return $this->redirectAfterArchive($request)
                ->with('error', 'Could not archive this memo. It may have been updated or no longer holds budget.');
        }

        return $this->redirectAfterArchive($request)
            ->with('success', 'Stale draft archived. Budget has been released (overall_status set to archived).');
    }

    public function archiveAll(Request $request): RedirectResponse
    {
        if (! in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized');
        }

        $staffId = user_session('staff_id');
        $archivedBy = $staffId !== null && $staffId !== '' ? (int) $staffId : null;

        $result = app(StaleMemoArchiveService::class)->archiveAllStaleDrafts('manual', $archivedBy);

        $message = "Archived {$result['archived']} stale draft memo(s).";
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']}.";
        }
        if ($result['errors'] !== []) {
            return redirect()
                ->route('system-configs.index', ['tab' => 'stale-memos'])
                ->with('error', $message . ' Errors: ' . implode('; ', $result['errors']));
        }

        return redirect()
            ->route('system-configs.index', ['tab' => 'stale-memos'])
            ->with('success', $message);
    }

    private function redirectAfterArchive(Request $request): RedirectResponse
    {
        $redirect = $request->input('redirect');
        if (is_string($redirect) && $redirect !== '' && str_starts_with($redirect, url('/'))) {
            return redirect()->to($redirect);
        }

        if ($request->headers->get('referer') && str_contains((string) $request->headers->get('referer'), 'system-configs')) {
            return redirect()->route('system-configs.index', ['tab' => 'stale-memos']);
        }

        return redirect()->route('stale-drafts.index');
    }
}
