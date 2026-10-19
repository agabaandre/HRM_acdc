<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EmailDriver;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\ExternalIntegration;
use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class EmailLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'sent', 'failed'])],
            'driver' => ['sometimes', 'nullable', Rule::in(EmailDriver::values())],
            'external_integration_id' => ['sometimes', 'nullable'],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $allowedIds = $user->allowedExternalIntegrationIds();

        $logs = EmailLog::query()
            ->with(['emailProvider:id,name', 'externalIntegration:id,name'])
            ->tap(fn (Builder $q) => $this->scopeToAllowedIntegrations($q, $allowedIds))
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->query('status'))
            )
            ->when(
                $request->filled('driver'),
                fn ($q) => $q->where('driver', $request->query('driver'))
            )
            ->when(
                $request->filled('external_integration_id'),
                function ($q) use ($request, $allowedIds) {
                    $client = (string) $request->query('external_integration_id');
                    if ($client === 'none' || $client === '0') {
                        // Internal/admin sends — only unrestricted (admin) users may filter these.
                        if ($allowedIds !== null) {
                            $q->whereRaw('0 = 1');

                            return;
                        }
                        $q->whereNull('external_integration_id');
                    } else {
                        $id = (int) $client;
                        if ($allowedIds !== null && ! in_array($id, $allowedIds, true)) {
                            $q->whereRaw('0 = 1');

                            return;
                        }
                        $q->where('external_integration_id', $id);
                    }
                }
            )
            ->when(
                $request->filled('q'),
                function ($q) use ($request) {
                    $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('q')).'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('to', 'like', $term)
                            ->orWhere('subject', 'like', $term)
                            ->orWhere('error_message', 'like', $term)
                            ->orWhere('meta->sender_ip', 'like', $term);
                    });
                }
            )
            ->latest()
            ->paginate(min(100, max(1, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => $logs->getCollection()->map->toLogArray()->values(),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ]);
    }

    public function filterOptions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $allowedIds = $user->allowedExternalIntegrationIds();

        $clientsQuery = ExternalIntegration::query()->orderBy('name');
        if ($allowedIds !== null) {
            $clientsQuery->whereIn('id', $allowedIds === [] ? [0] : $allowedIds);
        }

        $clients = $clientsQuery
            ->get(['id', 'name'])
            ->map(fn (ExternalIntegration $i) => [
                'id' => $i->id,
                'name' => $i->name,
            ])
            ->values();

        $drivers = collect(EmailDriver::cases())->map(fn (EmailDriver $driver) => [
            'value' => $driver->value,
            'label' => $driver->label(),
        ])->values();

        return response()->json([
            'data' => [
                'statuses' => [
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'sent', 'label' => 'Sent'],
                    ['value' => 'failed', 'label' => 'Failed'],
                ],
                'drivers' => $drivers,
                'clients' => $clients,
                'can_view_internal' => $allowedIds === null,
            ],
        ]);
    }

    public function retry(Request $request, EmailLog $emailLog, EmailDispatchService $dispatch): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->canAccessExternalIntegration($emailLog->external_integration_id)) {
            return response()->json(['message' => 'You do not have access to this email log.'], 403);
        }

        try {
            $log = $dispatch->retry($emailLog);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not queue email for resend.',
            ], 500);
        }

        $log->load(['emailProvider:id,name', 'externalIntegration:id,name']);

        return response()->json([
            'message' => 'Email queued for resend.',
            'data' => $log->toLogArray(),
        ]);
    }

    public function retryFailed(Request $request, EmailDispatchService $dispatch): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $allowedIds = $user->allowedExternalIntegrationIds();

        $client = $request->filled('external_integration_id')
            ? (string) $request->input('external_integration_id')
            : null;

        if ($client !== null && $client !== 'none' && $client !== '0') {
            $id = (int) $client;
            if ($allowedIds !== null && ! in_array($id, $allowedIds, true)) {
                return response()->json(['message' => 'You do not have access to that app credential.'], 403);
            }
        }

        if ($allowedIds !== null && ($client === null || $client === 'none' || $client === '0')) {
            // Non-admins cannot resend across all apps / internal logs.
            if ($allowedIds === []) {
                return response()->json([
                    'message' => 'No failed emails found to resend.',
                    'queued' => 0,
                    'skipped' => 0,
                ]);
            }
            // Resend only within their first allowed app when no filter is set —
            // better: iterate all allowed. EmailDispatchService::retryAllFailed takes one client.
            // Call per allowed ID and aggregate.
            $queued = 0;
            $skipped = 0;
            try {
                foreach ($allowedIds as $integrationId) {
                    $result = $dispatch->retryAllFailed((string) $integrationId);
                    $queued += $result['queued'];
                    $skipped += $result['skipped'];
                }
            } catch (Throwable $e) {
                report($e);

                return response()->json([
                    'message' => 'Could not queue failed emails for resend.',
                ], 500);
            }

            return $this->retryFailedResponse($queued, $skipped);
        }

        try {
            $result = $dispatch->retryAllFailed($client);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not queue failed emails for resend.',
            ], 500);
        }

        return $this->retryFailedResponse($result['queued'], $result['skipped']);
    }

    /**
     * @param  list<int>|null  $allowedIds
     */
    private function scopeToAllowedIntegrations(Builder $query, ?array $allowedIds): void
    {
        if ($allowedIds === null) {
            return;
        }

        if ($allowedIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('external_integration_id', $allowedIds);
    }

    private function retryFailedResponse(int $queued, int $skipped): JsonResponse
    {
        if ($queued === 0 && $skipped === 0) {
            return response()->json([
                'message' => 'No failed emails found to resend.',
                'queued' => 0,
                'skipped' => 0,
            ]);
        }

        return response()->json([
            'message' => $skipped > 0
                ? "Queued {$queued} failed email(s) for resend. Skipped {$skipped} without a stored body."
                : "Queued {$queued} failed email(s) for resend.",
            'queued' => $queued,
            'skipped' => $skipped,
        ]);
    }
}
