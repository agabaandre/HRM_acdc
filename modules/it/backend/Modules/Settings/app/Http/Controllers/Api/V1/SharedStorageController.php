<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\PortalPermission;
use Modules\Settings\Services\SharedStorageService;

class SharedStorageController extends Controller
{
    public function __construct(
        protected SharedStorageService $storage,
    ) {}

    public function show(): JsonResponse
    {
        PortalPermission::authorize(15);

        return response()->json(['data' => $this->storage->status()]);
    }

    public function migrate(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $validated = $request->validate([
            'module' => 'required|string|in:ci,apm,helpdesk,staff-portal,all',
        ]);

        $result = $this->storage->migrate($validated['module']);
        $status = $result['status'] === 'completed' ? 200 : 422;

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'result' => $result,
                'status' => $this->storage->status(),
            ],
        ], $status);
    }

    public function enableHost(): JsonResponse
    {
        PortalPermission::authorize(15);

        $result = $this->storage->enableHostStorage();
        $code = $result['status'] === 'completed' ? 200 : 422;

        return response()->json([
            'message' => $result['message'],
            'data' => $this->storage->status(),
        ], $code);
    }

    public function purgeCi(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $validated = $request->validate([
            'confirm' => 'required|string|in:DELETE_CI_UPLOADS',
            'dry_run' => 'sometimes|boolean',
        ]);

        $result = $this->storage->purgeCiLegacy((bool) ($validated['dry_run'] ?? false));
        $code = $result['status'] === 'completed' ? 200 : 422;

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'result' => $result,
                'status' => $this->storage->status(),
            ],
        ], $code);
    }
}
