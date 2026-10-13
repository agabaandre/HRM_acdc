<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FinanceApmOrgService;
use App\Support\RiskPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class FinanceApmMappingController extends Controller
{
    public function __construct(private readonly FinanceApmOrgService $org = new FinanceApmOrgService) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json([
            'success' => true,
            'data' => $this->org->snapshot(),
        ]);
    }

    public function fetch(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        try {
            $data = $this->org->fetchFromApm();

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Fetched %d APM division(s) and %d funder(s). Alias suggestions refreshed.',
                    count($data['divisions']),
                    count($data['funders']),
                ),
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::error('Finance APM org fetch failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'division_aliases' => ['nullable', 'array'],
            'division_aliases.*.label' => ['required_with:division_aliases', 'string', 'max:255'],
            'division_aliases.*.division_id' => ['nullable', 'integer'],
            'division_aliases.*.match_source' => ['nullable', 'string', 'max:32'],
            'funder_aliases' => ['nullable', 'array'],
            'funder_aliases.*.label' => ['required_with:funder_aliases', 'string', 'max:255'],
            'funder_aliases.*.funder_id' => ['nullable', 'integer'],
            'funder_aliases.*.funder_name' => ['nullable', 'string', 'max:255'],
            'funder_aliases.*.match_source' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $data = $this->org->save($validated);

            return response()->json([
                'success' => true,
                'message' => 'APM division and funder mappings saved.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::error('Finance APM mapping save failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function authorizeManage(Request $request): void
    {
        if (! RiskPermissions::canManage($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Unauthorized.'], 403));
        }
    }
}
