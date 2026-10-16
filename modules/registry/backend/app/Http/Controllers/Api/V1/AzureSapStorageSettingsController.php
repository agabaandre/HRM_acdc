<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AzureSapDumpSyncService;
use App\Support\AzureSapStorageCredentials;
use App\Support\RiskPermissions;
use App\Support\RrSettingsBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AzureSapStorageSettingsController extends Controller
{
    public function show(Request $request, AzureSapDumpSyncService $sync): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json([
            'success' => true,
            'data' => array_merge(
                AzureSapStorageCredentials::snapshot(new RrSettingsBag),
                ['sync' => $sync->lastSyncStatus()],
            ),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'azure_sap_connection_string' => ['nullable', 'string', 'max:2000'],
            'azure_sap_account_name' => ['nullable', 'string', 'max:255'],
            'azure_sap_account_key' => ['nullable', 'string', 'max:500'],
            'azure_sap_container' => ['nullable', 'string', 'max:255'],
            'azure_sap_prefix' => ['nullable', 'string', 'max:500'],
            'clear_connection_string' => ['nullable', 'boolean'],
            'clear_account_key' => ['nullable', 'boolean'],
        ]);

        try {
            $message = AzureSapStorageCredentials::persist(new RrSettingsBag, $validated);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => AzureSapStorageCredentials::snapshot(new RrSettingsBag),
            ]);
        } catch (Throwable $e) {
            Log::error('Finance Azure SAP storage settings update failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function test(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $override = [];
        foreach ([
            'azure_sap_connection_string' => 'connection_string',
            'azure_sap_account_name' => 'account_name',
            'azure_sap_account_key' => 'account_key',
            'azure_sap_container' => 'container',
            'azure_sap_prefix' => 'prefix',
        ] as $in => $out) {
            if ($request->filled($in)) {
                $override[$out] = (string) $request->input($in);
            }
        }

        $result = AzureSapStorageCredentials::probe(
            new RrSettingsBag,
            $override !== [] ? $override : null,
        );

        return response()->json($result);
    }

    public function fetchNow(Request $request, AzureSapDumpSyncService $sync): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'keep' => ['nullable', 'boolean'],
        ]);
        $delete = ! (bool) ($validated['keep'] ?? false);
        if ($delete) {
            $delete = (bool) config('azure_sap.delete_after_fetch', true);
        }

        $result = $sync->sync($delete);

        return response()->json([
            'success' => $result['errors'] === [],
            'message' => $result['errors'] === []
                ? sprintf(
                    'Fetched %d file(s); deleted %d remote blob(s).',
                    count($result['imported']),
                    count($result['deleted']),
                )
                : 'Fetch completed with errors.',
            'data' => array_merge(
                AzureSapStorageCredentials::snapshot(new RrSettingsBag),
                [
                    'sync' => $sync->lastSyncStatus(),
                    'run' => $result,
                ],
            ),
        ], $result['errors'] === [] ? 200 : 502);
    }

    private function authorizeManage(Request $request): void
    {
        if (! RiskPermissions::canManage($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Unauthorized.'], 403));
        }
    }
}
