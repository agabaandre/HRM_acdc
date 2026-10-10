<?php

namespace App\Http\Controllers;

use App\Services\Pra\PraOrgUnitMappingService;
use App\Services\Pra\PraSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PraOrgUnitController extends Controller
{
    public function __construct(
        protected PraSettingsService $settings,
        protected PraOrgUnitMappingService $mappings,
    ) {}

    public function settings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->settings->formPayload(),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_url' => ['nullable', 'string', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'tiers' => ['nullable', 'string', 'max:50'],
            'fiscal_year' => ['nullable'],
            'division_aliases' => ['nullable', 'string', 'max:2000'],
            'timeout' => ['nullable', 'integer', 'min:10', 'max:300'],
        ]);

        try {
            $data = $this->settings->save($validated);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'PRA settings saved.',
            'data' => $data,
        ]);
    }

    public function mappings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->mappings->savedMappingsPayload(),
            'meta' => [
                'configured' => $this->settings->isConfigured(),
                'settings' => $this->settings->formPayload(),
            ],
        ]);
    }

    public function fetch(Request $request): JsonResponse
    {
        if (! $this->settings->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'PRA API is not configured. Save the URL and API key first.',
            ], 422);
        }

        $fiscal = $request->input('fiscal_year');
        $fiscalYear = ($fiscal === null || $fiscal === '') ? null : (int) $fiscal;

        try {
            $data = $this->mappings->syncFromPra($fiscalYear);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Synced %d PRA unit(s): %d preserved, %d added, %d unmatched.',
                $data['summary']['total'],
                $data['summary']['preserved'],
                $data['summary']['added'],
                $data['summary']['unmatched']
            ),
            'data' => $data,
        ]);
    }

    public function saveMappings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'units' => ['required', 'array'],
            'units.*.pra_code' => ['required', 'string', 'max:64'],
            'units.*.pra_division_id' => ['nullable', 'string', 'max:64'],
            'units.*.pra_name' => ['nullable', 'string', 'max:255'],
            'units.*.entity_type' => ['nullable', 'string', 'in:division,directorate'],
            'units.*.local_division_id' => ['nullable', 'integer'],
            'units.*.local_directorate_id' => ['nullable', 'integer'],
            'units.*.match_source' => ['nullable', 'string', 'in:auto,alias,manual,unmatched'],
            'units.*.user_set' => ['nullable', 'boolean'],
        ]);

        try {
            $count = $this->mappings->saveMappings($validated['units']);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Saved {$count} PRA mapping(s).",
            'data' => $this->mappings->savedMappingsPayload(),
        ]);
    }
}
