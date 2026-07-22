<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Exports\LicensesExport;
use App\Http\Controllers\Concerns\DownloadsPdfReports;
use App\Http\Controllers\Controller;
use App\Models\HelpdeskLicense;
use App\Services\HelpdeskPdfReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class LicenseController extends Controller
{
    use AuthorizesHelpdeskTools;
    use DownloadsPdfReports;

    public function summary(Request $request): JsonResponse
    {
        $this->ensureLicenseManager($request);

        $licenses = HelpdeskLicense::query()->get();
        $expiringSoon = $licenses->filter(fn ($l) => ($l->expiry['is_expiring_soon'] ?? false))->count();
        $expired = $licenses->filter(fn ($l) => ($l->expiry['is_expired'] ?? false))->count();

        return response()->json([
            'data' => [
                'license_count' => $licenses->count(),
                'expiring_soon' => $expiringSoon,
                'expired' => $expired,
                'total_seats' => (int) $licenses->sum('seats_total'),
                'seats_used' => (int) $licenses->sum('seats_used'),
                'annual_cost' => round($licenses->sum('cost'), 2),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->ensureLicenseManager($request);

        $rows = HelpdeskLicense::query()->orderBy('name')->limit(5000)->get();

        return Excel::download(
            new LicensesExport($rows),
            'licenses-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function exportPdf(Request $request, HelpdeskPdfReportService $pdf): Response
    {
        $this->ensureLicenseManager($request);

        $licenses = HelpdeskLicense::query()->orderBy('expiry_date')->orderBy('name')->limit(2000)->get();
        $rows = $licenses->map(fn (HelpdeskLicense $l) => [
            $l->name,
            $l->vendor,
            $l->seats_used.'/'.$l->seats_total,
            optional($l->expiry_date)?->format('Y-m-d'),
            $l->expiry['days_remaining'] ?? null,
            ! empty($l->expiry['is_expired']) ? 'Expired' : (! empty($l->expiry['is_expiring_soon']) ? 'Expiring soon' : 'OK'),
            $l->cost,
            $l->status,
            $l->responsible_person['name'] ?? null,
        ])->all();

        $summaryLines = [
            'Licenses: '.$licenses->count(),
            'Expiring soon: '.$licenses->filter(fn ($l) => $l->expiry['is_expiring_soon'] ?? false)->count(),
            'Expired: '.$licenses->filter(fn ($l) => $l->expiry['is_expired'] ?? false)->count(),
            'Annual cost: '.round($licenses->sum('cost'), 2),
        ];

        return $this->pdfTableDownload(
            $request,
            $pdf,
            'Software licenses',
            ['Name', 'Vendor', 'Seats', 'Expiry', 'Days left', 'Health', 'Cost', 'Status', 'Responsible'],
            $rows,
            'licenses-'.now()->format('Y-m-d').'.pdf',
            $summaryLines,
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureLicenseManager($request);

        $query = HelpdeskLicense::query();

        if ($request->boolean('expiring_soon')) {
            $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', now()->toDateString())
                ->whereDate('expiry_date', '<=', now()->addDays(30)->toDateString());
        }
        if ($request->boolean('expired')) {
            $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now()->toDateString());
        }
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', $q)
                    ->orWhere('vendor', 'like', $q)
                    ->orWhere('license_key', 'like', $q);
            });
        }

        $rows = $query->orderBy('expiry_date')->orderBy('name')
            ->paginate(min(100, max(10, (int) $request->input('per_page', 25))));

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureLicenseManager($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'vendor' => ['nullable', 'string', 'max:191'],
            'license_key' => ['nullable', 'string'],
            'seats_total' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'seats_used' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'purchase_date' => ['nullable', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'expiry_date' => ['nullable', 'date'],
            'warning_days_before' => ['nullable', 'integer', 'min:1', 'max:365'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'renewal_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'responsible_staff_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (empty($validated['expiry_date']) && ! empty($validated['purchase_date']) && ! empty($validated['duration_months'])) {
            $validated['expiry_date'] = Carbon::parse($validated['purchase_date'])
                ->addMonths((int) $validated['duration_months'])
                ->toDateString();
        }

        $row = HelpdeskLicense::query()->create(array_merge($validated, [
            'created_by_user_id' => $request->user()?->id,
        ]));

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, HelpdeskLicense $license): JsonResponse
    {
        $this->ensureLicenseManager($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'vendor' => ['nullable', 'string', 'max:191'],
            'license_key' => ['nullable', 'string'],
            'seats_total' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'seats_used' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'purchase_date' => ['nullable', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'expiry_date' => ['nullable', 'date'],
            'warning_days_before' => ['nullable', 'integer', 'min:1', 'max:365'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'renewal_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'responsible_staff_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $license->fill($validated);
        $license->save();

        return response()->json(['data' => $license->fresh()]);
    }

    public function destroy(Request $request, HelpdeskLicense $license): JsonResponse
    {
        $this->ensureLicenseManager($request);
        $license->delete();

        return response()->json(['ok' => true]);
    }
}
