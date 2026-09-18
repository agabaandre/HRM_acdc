<?php

namespace Modules\AdManager\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdManager\Services\AdManagerService;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;

class AdManagerApiController extends Controller
{
    public function hub(AdManagerService $ad): JsonResponse
    {
        PortalPermission::authorize(77);

        $toDisable = $ad->paginateAccountsToDisable('', 10, 1)->total();
        $disabled = $ad->paginateDisabledAccounts('', 10, 1)->total();

        return response()->json([
            'data' => [
                'summary' => [
                    'to_disable' => $toDisable,
                    'disabled' => $disabled,
                ],
                'links' => [
                    [
                        'to' => '/admanager/expired',
                        'label' => 'Accounts to disable',
                        'description' => 'Expired contracts whose AD/email accounts are still active.',
                        'icon' => 'fa-solid fa-user-slash',
                        'count' => $toDisable,
                    ],
                    [
                        'to' => '/admanager/disabled',
                        'label' => 'Disabled accounts',
                        'description' => 'Accounts already marked disabled — review or re-enable.',
                        'icon' => 'fa-solid fa-ban',
                        'count' => $disabled,
                    ],
                ],
            ],
        ]);
    }

    public function expired(Request $request, AdManagerService $ad): JsonResponse
    {
        PortalPermission::authorize(77);
        $paginator = $ad->paginateAccountsToDisable(
            (string) $request->query('q', ''),
            min(100, max(10, (int) $request->query('per_page', 20))),
            max(1, (int) $request->query('page', 1))
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function disabled(Request $request, AdManagerService $ad): JsonResponse
    {
        PortalPermission::authorize(77);
        $paginator = $ad->paginateDisabledAccounts(
            (string) $request->query('q', ''),
            min(100, max(10, (int) $request->query('per_page', 20))),
            max(1, (int) $request->query('page', 1))
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function markDisabled(int $staff, AdManagerService $ad): JsonResponse
    {
        PortalPermission::authorize(77);
        $updated = $ad->markDisabled($staff, $this->actorStaffId());

        return response()->json([
            'data' => $updated,
            'message' => 'Account marked as disabled.',
        ]);
    }

    public function markEnabled(int $staff, AdManagerService $ad): JsonResponse
    {
        PortalPermission::authorize(77);
        $updated = $ad->markEnabled($staff, $this->actorStaffId());

        return response()->json([
            'data' => $updated,
            'message' => 'Account marked as enabled.',
        ]);
    }

    protected function actorStaffId(): int
    {
        $user = auth()->user();
        if ($user instanceof PortalUser && $user->auth_staff_id) {
            return (int) $user->auth_staff_id;
        }

        return (int) (session('user.staff_id') ?? 0);
    }
}
