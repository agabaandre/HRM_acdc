<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreStatusReportRequest;
use App\Models\StatusReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = StatusReport::query()
            ->with(['project:id,code,name', 'submitter:id,name'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->query('period')))
            ->when($request->boolean('portfolio'), fn ($q) => $q->whereNull('project_id'))
            ->orderByDesc('period_end')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreStatusReportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['submitted_by'] = $request->user()?->id;
        $data['submitted_at'] = now();

        $report = StatusReport::query()->create($data);
        $report->load(['project:id,code,name', 'submitter:id,name']);

        return response()->json(['data' => $report->toApiArray()], 201);
    }

    public function show(StatusReport $statusReport): JsonResponse
    {
        $statusReport->load(['project:id,code,name', 'submitter:id,name']);

        return response()->json(['data' => $statusReport->toApiArray()]);
    }

    public function update(StoreStatusReportRequest $request, StatusReport $statusReport): JsonResponse
    {
        $statusReport->update($request->validated());
        $statusReport->load(['project:id,code,name', 'submitter:id,name']);

        return response()->json(['data' => $statusReport->toApiArray()]);
    }
}
