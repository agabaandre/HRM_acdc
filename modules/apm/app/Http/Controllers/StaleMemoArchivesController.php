<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaleMemoArchive;
use App\Services\BudgetCommitmentSettings;
use App\Services\StaleDraftArchiveSchedule;
use App\Services\StaleDraftMemosService;
use App\Services\StaleMemoArchiveService;
use Illuminate\Http\JsonResponse;
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

        $pendingStale = $this->enrichPeople($pendingStale);

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
        $archiveService = app(StaleMemoArchiveService::class);

        $pendingStale = $staleService->getAllStaleDrafts();
        foreach ($pendingStale as &$item) {
            $updatedAt = ! empty($item['updated_at']) ? \Carbon\Carbon::parse($item['updated_at']) : null;
            $item['scheduled_archive_at'] = $updatedAt
                ? $schedule->scheduledArchiveAt($updatedAt)->format('Y-m-d H:i')
                : null;
            $item['can_archive'] = true;
            $item['budget_total_formatted'] = number_format((float) ($item['budget_total'] ?? 0), 2);
        }
        unset($item);
        $pendingStale = $this->enrichPeople($pendingStale);

        $archivedRows = StaleMemoArchive::query()
            ->orderByDesc('archived_at')
            ->limit(500)
            ->get();

        $archived = [];
        foreach ($archivedRows as $row) {
            $model = $archiveService->resolveModel((string) $row->memo_type, (int) $row->memo_id);
            $stillArchived = $model !== null && (string) ($model->overall_status ?? '') === 'archived';

            $archived[] = [
                'id' => (int) $row->id,
                'memo_type' => (string) $row->memo_type,
                'memo_id' => (int) $row->memo_id,
                'type_label' => $row->typeLabel(),
                'title' => (string) ($row->title ?? 'Untitled'),
                'document_number' => $row->document_number,
                'staff_id' => (int) ($row->staff_id ?? 0),
                'responsible_person_id' => (int) ($row->responsible_person_id ?? 0),
                'budget_total' => (float) $row->budget_total,
                'budget_total_formatted' => number_format((float) $row->budget_total, 2),
                'previous_status' => $row->previous_status,
                'memo_updated_at' => $row->memo_updated_at?->format('Y-m-d H:i'),
                'archived_at' => $row->archived_at?->format('Y-m-d H:i'),
                'trigger' => (string) $row->trigger,
                'is_still_archived' => $stillArchived,
                'can_unarchive' => $stillArchived,
            ];
        }
        $archived = $this->enrichPeople($archived);

        return [
            'pageConfig' => [
                'csrf' => csrf_token(),
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                ],
                'policy' => [
                    'draftMaxAgeMonths' => $settings->draftMaxAgeMonths(),
                    'autoArchiveEnabled' => $settings->staleDraftAutoArchiveEnabled(),
                    'nextWeeklyRun' => $schedule->nextWeeklyRun()->format('Y-m-d H:i'),
                    'weeklyRunLabel' => 'Mondays at ' . str_pad((string) $schedule->weeklyRunHour(), 2, '0', STR_PAD_LEFT) . ':00',
                    'appSettingsUrl' => route('system-configs.index', ['tab' => 'app-settings']),
                ],
                'pendingStale' => $pendingStale,
                'archived' => $archived,
                'routes' => [
                    'archiveOne' => route('stale-drafts.archive'),
                    'archiveAll' => route('stale-memos.archive-all'),
                    'unarchiveOne' => route('stale-memos.unarchive'),
                    'index' => route('system-configs.index', ['tab' => 'stale-memos']),
                ],
            ],
        ];
    }

    public function archiveOne(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'memo_type' => 'required|string|in:activity,single_memo,special_memo,non_travel_memo,change_request',
            'memo_id' => 'required|integer|min:1',
        ]);

        $staleService = new StaleDraftMemosService();
        $item = $staleService->findStaleDraftItem($validated['memo_type'], (int) $validated['memo_id']);
        if ($item === null) {
            return $this->archiveActionResponse($request, false, 'Stale draft not found or no longer eligible for archive.');
        }

        if (! can_archive_stale_draft_memo($item)) {
            abort(403, 'You are not allowed to archive this stale draft.');
        }

        $staffId = user_session('staff_id');
        $archivedBy = $staffId !== null && $staffId !== '' ? (int) $staffId : null;

        $archived = app(StaleMemoArchiveService::class)->archiveMemoItem($item, 'manual', $archivedBy);
        if (! $archived) {
            return $this->archiveActionResponse($request, false, 'Could not archive this memo. It may have been updated or no longer holds budget.');
        }

        return $this->archiveActionResponse($request, true, 'Stale draft archived. Budget has been released (overall_status set to archived).');
    }

    public function archiveAll(Request $request): RedirectResponse|JsonResponse
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
            return $this->archiveActionResponse(
                $request,
                false,
                $message . ' Errors: ' . implode('; ', $result['errors']),
                ['result' => $result]
            );
        }

        return $this->archiveActionResponse($request, true, $message, ['result' => $result]);
    }

    public function unarchiveOne(Request $request): RedirectResponse|JsonResponse
    {
        if (! in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'memo_type' => 'required|string|in:activity,single_memo,special_memo,non_travel_memo,change_request',
            'memo_id' => 'required|integer|min:1',
            'archive_id' => 'nullable|integer|min:1',
        ]);

        $fallback = 'draft';
        if (! empty($validated['archive_id'])) {
            $log = StaleMemoArchive::query()->find((int) $validated['archive_id']);
            if ($log !== null && $log->previous_status) {
                $fallback = (string) $log->previous_status;
            }
        }

        $ok = app(StaleMemoArchiveService::class)->unarchiveMemoItem(
            $validated['memo_type'],
            (int) $validated['memo_id'],
            $fallback
        );

        if (! $ok) {
            return $this->archiveActionResponse($request, false, 'Could not unarchive this memo. It may no longer be archived.');
        }

        return $this->archiveActionResponse($request, true, 'Memo unarchived successfully. It may commit budget again if still within draft age limits.');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function enrichPeople(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $owner = (int) ($item['staff_id'] ?? 0);
            $responsible = (int) ($item['responsible_person_id'] ?? 0);
            if ($owner > 0) {
                $ids[] = $owner;
            }
            if ($responsible > 0) {
                $ids[] = $responsible;
            }
        }

        $names = [];
        if ($ids !== []) {
            $names = Staff::query()
                ->whereIn('staff_id', array_values(array_unique($ids)))
                ->get(['staff_id', 'title', 'fname', 'lname', 'oname'])
                ->keyBy('staff_id')
                ->map(fn (Staff $s) => (string) $s->name)
                ->all();
        }

        foreach ($items as &$item) {
            $ownerId = (int) ($item['staff_id'] ?? 0);
            $responsibleId = (int) ($item['responsible_person_id'] ?? 0);
            $creator = $ownerId > 0 ? ($names[$ownerId] ?? ('Staff #' . $ownerId)) : '—';
            $responsible = $responsibleId > 0
                ? ($names[$responsibleId] ?? ('Staff #' . $responsibleId))
                : '—';

            $item['creator_name'] = $creator;
            $item['responsible_name'] = $responsible;
            $item['people_label'] = $responsibleId > 0 && $responsibleId !== $ownerId
                ? "Creator: {$creator}\nResponsible: {$responsible}"
                : "Creator: {$creator}";
        }
        unset($item);

        return $items;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function archiveActionResponse(Request $request, bool $ok, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(array_merge([
                'success' => $ok,
                'message' => $message,
            ], $extra), $ok ? 200 : 422);
        }

        $redirect = $this->redirectAfterArchive($request);

        return $ok
            ? $redirect->with('success', $message)
            : $redirect->with('error', $message);
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
