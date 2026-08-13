<?php

namespace Modules\Payroll\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollRunService;
use Modules\Payroll\Support\PayrollAccess;

class PayrollRunController extends Controller
{
    public function index(): JsonResponse
    {
        PayrollAccess::authorizeRun();
        $runs = PayrollRun::query()->with('period')->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $runs]);
    }

    public function store(Request $request, PayrollRunService $service): JsonResponse
    {
        PayrollAccess::authorizeRun();
        $data = $request->validate([
            'period_id' => 'required|integer',
            'off_cycle' => 'boolean',
            'title' => 'nullable|string|max:160',
            'notes' => 'nullable|string',
        ]);

        return response()->json([
            'message' => 'Payroll run created.',
            'data' => $service->create($data),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        PayrollAccess::authorizeRun();
        $run = PayrollRun::query()->with(['period', 'lines.items.wageType'])->findOrFail($id);
        $this->attachStaffNames($run->lines);

        return response()->json(['data' => $run]);
    }

    public function lines(int $id): JsonResponse
    {
        if (! PayrollAccess::canRunPayroll() && ! PayrollAccess::canViewHub()) {
            abort(403);
        }
        $run = PayrollRun::query()->findOrFail($id);
        $lines = $run->lines()->with('items.wageType')->orderBy('staff_id')->get();
        $this->attachStaffNames($lines);

        return response()->json(['data' => $lines]);
    }

    public function simulate(int $id, PayrollRunService $service): JsonResponse
    {
        PayrollAccess::authorizeRun();
        $run = PayrollRun::query()->findOrFail($id);

        return response()->json([
            'message' => 'Payroll simulated.',
            'data' => $service->simulate($run),
        ]);
    }

    public function post(Request $request, int $id, PayrollRunService $service): JsonResponse
    {
        PayrollAccess::authorizeRun();
        $run = PayrollRun::query()->findOrFail($id);
        $allow = (bool) $request->boolean('allow_negative_net');

        return response()->json([
            'message' => 'Payroll posted.',
            'data' => $service->post($run, $allow),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Modules\Payroll\Models\PayrollRunLine>|\Illuminate\Database\Eloquent\Collection<int, \Modules\Payroll\Models\PayrollRunLine>  $lines
     */
    private function attachStaffNames($lines): void
    {
        $ids = $lines->pluck('staff_id')->unique()->filter()->values()->all();
        if ($ids === []) {
            return;
        }

        $map = DB::table('staff')
            ->whereIn('staff_id', $ids)
            ->select([
                'staff_id',
                'SAPNO as sap_number',
                DB::raw("TRIM(CONCAT(COALESCE(fname,''), ' ', COALESCE(lname,''))) as staff_name"),
            ])
            ->get()
            ->keyBy('staff_id');

        foreach ($lines as $line) {
            $row = $map->get($line->staff_id);
            $line->setAttribute('staff_name', $row ? trim((string) $row->staff_name) : null);
            $line->setAttribute('sap_number', $row->sap_number ?? null);
        }
    }
}
