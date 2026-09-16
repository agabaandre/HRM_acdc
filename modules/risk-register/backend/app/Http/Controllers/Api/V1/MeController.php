<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => (int) $request->attributes->get('risk_staff_id'),
                'name' => 'Risk Register user',
                'email' => '',
                'avatar_url' => null,
                'profile' => [
                    'staff_id' => (int) $request->attributes->get('risk_staff_id'),
                    'role' => 'staff',
                    'role_id' => 0,
                    'division_id' => (int) $request->attributes->get('risk_division_id') ?: null,
                    'permissions' => $request->attributes->get('risk_permissions', []),
                ],
                'enabled_modules' => [
                    'risks' => true,
                    'dashboard' => true,
                ],
            ],
        ]);
    }
}
