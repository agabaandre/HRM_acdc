<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\StaffDivisionContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DivisionContextController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'division_id' => ['required', 'integer', 'min:1'],
        ]);

        $ok = StaffDivisionContext::setActiveOnSession($request, (int) $validated['division_id']);
        if (! $ok) {
            return response()->json(['message' => 'You cannot switch to that division.'], 422);
        }

        $staffId = (int) $request->attributes->get('risk_staff_id', 0);
        $session = [];
        try {
            $fromSession = $request->session()->get('risk_register');
            if (is_array($fromSession)) {
                $session = $fromSession;
            }
        } catch (\Throwable) {
            // ignore
        }
        if ($session === []) {
            $bearer = $request->bearerToken();
            if ($bearer) {
                $cached = \Illuminate\Support\Facades\Cache::get('risk_api_token:'.$bearer);
                if (is_array($cached)) {
                    $session = $cached;
                }
            }
        }

        $activeId = StaffDivisionContext::resolveDivisionId($session, $staffId);
        $request->attributes->set('risk_division_id', $activeId);

        $name = '';
        foreach (StaffDivisionContext::switchableDivisions($staffId) as $row) {
            if ((int) $row['id'] === $activeId) {
                $name = (string) $row['name'];
                break;
            }
        }

        return response()->json([
            'data' => [
                'division_id' => $activeId > 0 ? $activeId : null,
                'division_name' => $name !== '' ? $name : null,
                'message' => $name !== '' ? ('Now acting as '.$name.'.') : 'Division context updated.',
                'division_context' => StaffDivisionContext::payloadFor($staffId, $activeId),
            ],
        ]);
    }
}
