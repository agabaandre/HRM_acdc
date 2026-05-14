<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function index(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $perPage = min(100, max(5, (int) $request->query('per_page', 40)));

        $paginator = HelpdeskAuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
