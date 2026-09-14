<?php

namespace Modules\Workplan\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\PortalPermission;
use Modules\Workplan\Services\PraWorkplanSettingsService;

class PraWorkplanSettingsController extends Controller
{
    public function show(PraWorkplanSettingsService $settings): JsonResponse
    {
        PortalPermission::authorize(15);

        return response()->json(['data' => $settings->formPayload()]);
    }

    public function update(Request $request, PraWorkplanSettingsService $settings): JsonResponse
    {
        PortalPermission::authorize(15);

        if ($request->exists('fiscal_year') && $request->input('fiscal_year') === '') {
            $request->merge(['fiscal_year' => null]);
        }

        $data = $request->validate([
            'base_url' => ['required', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'tiers' => ['nullable', 'string', 'max:50'],
            'fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'divisions' => ['nullable', 'string', 'max:500'],
            'division_aliases' => ['nullable', 'string', 'max:500'],
            'timeout' => ['nullable', 'integer', 'min:10', 'max:300'],
        ]);

        $settings->save($data);
        PortalReadCache::bust('staff');

        return response()->json([
            'data' => $settings->formPayload(),
            'message' => 'PRA workplan settings saved.',
        ]);
    }
}
