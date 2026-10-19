<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffShareSetting;
use App\Services\StaffShare\StaffShareUserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffShareController extends Controller
{
    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => StaffShareSetting::current()->toApiArray(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => ['sometimes', 'boolean'],
            'base_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url'],
            'api_token' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'clear_api_token' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = StaffShareSetting::current();
        $data = $validator->validated();

        if (array_key_exists('enabled', $data)) {
            $settings->enabled = (bool) $data['enabled'];
        }

        if (array_key_exists('base_url', $data) && filled($data['base_url'])) {
            $settings->base_url = rtrim((string) $data['base_url'], '/');
        }

        if (! empty($data['clear_api_token'])) {
            $settings->api_token = null;
        } elseif (array_key_exists('api_token', $data) && filled($data['api_token'])) {
            // Strip accidental "Bearer " prefix from pasted Authorization headers.
            $token = trim((string) $data['api_token']);
            if (str_starts_with(strtolower($token), 'bearer ')) {
                $token = trim(substr($token, 7));
            }
            $settings->api_token = $token;
        }

        $settings->save();
        $settings->applyToConfig();

        return response()->json([
            'message' => 'Staff Share credentials saved.',
            'data' => $settings->fresh()->toApiArray(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $items = Staff::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('work_email', 'like', $term)
                        ->orWhere('fname', 'like', $term)
                        ->orWhere('lname', 'like', $term)
                        ->orWhere('oname', 'like', $term)
                        ->orWhere('division_name', 'like', $term)
                        ->orWhere('duty_station_name', 'like', $term)
                        ->orWhere('job_name', 'like', $term)
                        ->orWhere('title', 'like', $term);
                });
            })
            ->orderBy('lname')
            ->orderBy('fname')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function sync(StaffShareUserSyncService $sync): JsonResponse
    {
        $settings = StaffShareSetting::current();
        $settings->applyToConfig();

        if (! $settings->enabled) {
            return response()->json(['message' => 'Staff Share sync is disabled.'], 503);
        }

        $token = $settings->resolvedToken();
        $hasBasic = filled(config('services.staff_share.username'))
            && filled(config('services.staff_share.password'));

        if (! filled($token) && ! $hasBasic) {
            return response()->json([
                'message' => 'Save a Bearer API token on the Staff Share settings page first (Authorization: Bearer {STAFF_API_TOKEN}).',
            ], 503);
        }

        try {
            $stats = $sync->syncDirectory($token);
            $settings->forceFill([
                'last_synced_at' => now(),
                'last_sync_total' => $stats['staff']['total'] ?? null,
                'last_sync_message' => sprintf(
                    'Synced %d staff (%d created, %d updated).',
                    $stats['staff']['total'] ?? 0,
                    $stats['staff']['created'] ?? 0,
                    $stats['staff']['updated'] ?? 0,
                ),
            ])->save();
        } catch (\Throwable $e) {
            report($e);
            $settings->forceFill([
                'last_sync_message' => 'Sync failed: '.$e->getMessage(),
            ])->save();

            return response()->json([
                'message' => 'Staff Share sync failed: '.$e->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Staff directory synced into the staff table.',
            'data' => [
                'stats' => $stats,
                'settings' => $settings->fresh()->toApiArray(),
            ],
        ]);
    }
}
