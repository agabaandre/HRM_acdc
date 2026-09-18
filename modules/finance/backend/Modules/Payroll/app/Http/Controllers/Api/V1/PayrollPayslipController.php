<?php

namespace Modules\Payroll\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Models\PayrollPayslip;
use Modules\Payroll\Services\PayslipService;
use Modules\Payroll\Support\PayrollAccess;

class PayrollPayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = PayrollPayslip::query()->with(['period', 'run'])->orderByDesc('id');

        if (PayrollAccess::canViewAnyPayslips()) {
            if ($request->filled('staff_id')) {
                $q->where('staff_id', (int) $request->query('staff_id'));
            }
            if ($request->filled('period_id')) {
                $q->where('period_id', (int) $request->query('period_id'));
            }
            if ($request->filled('run_id')) {
                $q->where('run_id', (int) $request->query('run_id'));
            }
        } else {
            if (! PayrollAccess::canViewOwnPayslips() || ! PayrollAccess::staffId()) {
                abort(403, 'You do not have permission to view payslips.');
            }
            $q->where('staff_id', PayrollAccess::staffId());
        }

        $rows = $q->limit(200)->get();
        $this->attachStaffNames($rows);

        return response()->json(['data' => $rows]);
    }

    public function pdf(int $id, PayslipService $payslips)
    {
        $payslip = PayrollPayslip::query()->findOrFail($id);
        $isOwner = PayrollAccess::staffId() && (int) $payslip->staff_id === PayrollAccess::staffId();
        if (! $isOwner && ! PayrollAccess::canViewAnyPayslips()) {
            abort(403, 'You do not have permission to download this payslip.');
        }

        return $payslips->pdfResponse($payslip);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollPayslip>|\Illuminate\Database\Eloquent\Collection<int, PayrollPayslip>  $rows
     */
    private function attachStaffNames($rows): void
    {
        $ids = $rows->pluck('staff_id')->unique()->filter()->values()->all();
        if ($ids === []) {
            return;
        }

        $map = DB::table('staff')
            ->whereIn('staff_id', $ids)
            ->select([
                'staff_id',
                'SAPNO as sap_number',
                'work_email',
                DB::raw("TRIM(CONCAT(COALESCE(fname,''), ' ', COALESCE(lname,''))) as staff_name"),
            ])
            ->get()
            ->keyBy('staff_id');

        foreach ($rows as $row) {
            $staff = $map->get($row->staff_id);
            $row->setAttribute('staff_name', $staff ? trim((string) $staff->staff_name) : null);
            $row->setAttribute('sap_number', $staff->sap_number ?? null);
            $row->setAttribute('work_email', $staff->work_email ?? null);
        }
    }
}
