<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\PortalPermission;
use Throwable;

class ConfigurationController extends Controller
{
    public function summary(): JsonResponse
    {
        $this->authorizeView();

        return response()->json([
            'data' => [
                'users' => [
                    'accounts' => $this->count('user'),
                    'roles' => $this->count('user_groups'),
                    'permissions' => $this->count('permissions'),
                    'delegations' => $this->count('jobs_acting'),
                    'acting_contracts' => $this->countWhere('staff_contracts', 'job_acting_id', '>', 0),
                ],
                'organisation' => [
                    'directorates' => $this->count('directorates'),
                    'divisions' => $this->count('divisions'),
                    'units' => $this->count('units'),
                    'locations' => $this->count('duty_stations'),
                    'regions' => $this->count('regions'),
                    'cost_centres' => $this->foreignCount('SELECT COUNT(*) AS c FROM cdc_approvals_management.cost_items'),
                ],
                'workflows' => [
                    'approval_chains' => $this->foreignCount('SELECT COUNT(*) AS c FROM cdc_approvals_management.workflows'),
                    'notifications' => $this->count('email_notifications'),
                    'email_providers' => $this->count('portal_email_providers'),
                    'leave_types' => $this->count('leave_types'),
                    'contract_types' => $this->count('contract_types'),
                ],
                'system' => [
                    'modules' => $this->count('cbp_modules'),
                    'email_providers' => $this->count('portal_email_providers'),
                    'ai_providers' => $this->count('portal_ai_providers'),
                    'oauth_clients' => $this->count('oauth_clients'),
                    'audit' => $this->count('user_logs'),
                    'languages' => $this->count('portal_languages'),
                ],
            ],
        ]);
    }

    public function costCentres(): JsonResponse
    {
        $this->authorizeView();

        try {
            $rows = DB::select('SELECT id, name, cost_type FROM cdc_approvals_management.cost_items ORDER BY name');
        } catch (Throwable) {
            $rows = [];
        }

        return response()->json(['data' => $rows]);
    }

    public function approvalChains(): JsonResponse
    {
        $this->authorizeView();

        try {
            $rows = DB::select('SELECT id, workflow_name, is_active FROM cdc_approvals_management.workflows ORDER BY workflow_name');
        } catch (Throwable) {
            $rows = [];
        }

        return response()->json(['data' => $rows]);
    }

    private function authorizeView(): void
    {
        if (! PortalPermission::can(15) && ! PortalPermission::can(17)) {
            abort(403, 'You do not have permission for this action.');
        }
    }

    private function count(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countWhere(string $table, string $column, string $operator, int $value): int
    {
        try {
            return (int) DB::table($table)->where($column, $operator, $value)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function foreignCount(string $sql): int
    {
        try {
            $row = DB::selectOne($sql);

            return (int) ($row->c ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }
}
