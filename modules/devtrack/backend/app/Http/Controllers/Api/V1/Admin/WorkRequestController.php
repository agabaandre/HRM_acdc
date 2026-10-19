<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\WorkRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreWorkRequestRequest;
use App\Models\Project;
use App\Models\WorkRequest;
use App\Services\WorkRequestScoringService;
use App\Support\DevTrackReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkRequestController extends Controller
{
    public function __construct(private readonly WorkRequestScoringService $scoring) {}

    public function index(Request $request): JsonResponse
    {
        $items = WorkRequest::query()
            ->with(['indicator:id,code,title', 'project:id,code,name', 'acknowledger:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('work_class'), fn ($q) => $q->where('work_class', $request->query('work_class')))
            ->when($request->filled('workplan_indicator_id'), fn ($q) => $q->where('workplan_indicator_id', $request->query('workplan_indicator_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhere('requesting_unit', 'like', $term);
                });
            })
            ->latest()
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreWorkRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['reference'] = DevTrackReference::next('REQ', 'work_requests', 'reference');
        $data['created_by'] = $request->user()?->id;
        $data['requester_name'] = $data['requester_name'] ?? $request->user()?->name;
        $data['requester_email'] = $data['requester_email'] ?? $request->user()?->email;

        $item = new WorkRequest($data);
        $this->scoring->apply($item);
        $item->save();
        $item->load(['indicator:id,code,title', 'project:id,code,name']);

        return response()->json(['data' => $item->toApiArray()], 201);
    }

    public function show(WorkRequest $workRequest): JsonResponse
    {
        $workRequest->load(['indicator', 'project:id,code,name', 'acknowledger:id,name']);

        return response()->json(['data' => $workRequest->toApiArray()]);
    }

    public function update(StoreWorkRequestRequest $request, WorkRequest $workRequest): JsonResponse
    {
        $workRequest->fill($request->validated());
        $this->scoring->apply($workRequest);
        $workRequest->save();
        $workRequest->load(['indicator:id,code,title', 'project:id,code,name']);

        return response()->json(['data' => $workRequest->toApiArray()]);
    }

    public function acknowledge(Request $request, WorkRequest $workRequest): JsonResponse
    {
        if ($workRequest->status === WorkRequestStatus::Submitted) {
            $workRequest->status = WorkRequestStatus::Acknowledged;
        }

        $workRequest->acknowledged_by = $request->user()?->id;
        $workRequest->acknowledged_at = now();
        $workRequest->save();
        $workRequest->load(['indicator:id,code,title', 'project:id,code,name', 'acknowledger:id,name']);

        return response()->json(['data' => $workRequest->toApiArray()]);
    }

    public function convert(Request $request, WorkRequest $workRequest): JsonResponse
    {
        if ($workRequest->project_id) {
            return response()->json(['message' => 'This request is already linked to a project.'], 422);
        }

        if (! $workRequest->workplan_indicator_id) {
            return response()->json(['message' => 'Link a workplan indicator before converting this request to a project.'], 422);
        }

        $project = DB::transaction(function () use ($request, $workRequest) {
            $project = Project::query()->create([
                'code' => DevTrackReference::next('PRJ', 'projects', 'code'),
                'name' => $workRequest->title,
                'description' => $workRequest->problem_statement,
                'work_class' => $workRequest->work_class ?? \App\Enums\WorkClass::MediumProject,
                'workplan_indicator_id' => $workRequest->workplan_indicator_id,
                'work_request_id' => $workRequest->id,
                'stage' => ProjectStage::Discovery,
                'priority_category' => $workRequest->priority_category,
                'status' => ProjectStatus::InProgress,
                'data_classification' => $workRequest->data_classification,
                'success_measures' => $workRequest->success_measures,
                'created_by' => $request->user()?->id,
                'start_date' => now()->toDateString(),
            ]);

            $workRequest->project_id = $project->id;
            $workRequest->status = WorkRequestStatus::Converted;
            $workRequest->save();

            $project->stageEvents()->create([
                'from_stage' => ProjectStage::Intake,
                'to_stage' => ProjectStage::Discovery,
                'approved_by' => $request->user()?->id,
                'notes' => 'Converted from intake '.$workRequest->reference,
            ]);

            return $project;
        });

        $workRequest->load(['indicator:id,code,title', 'project:id,code,name']);

        return response()->json([
            'data' => $workRequest->toApiArray(),
            'project' => $project->load(['indicator', 'workRequest:id,reference,title'])->toSummaryArray(),
        ]);
    }
}
