<?php

namespace Modules\Leave\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReferenceCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Leave\Http\Resources\Api\V1\LeaveTypeResource;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeavePolicyService;
use Modules\Leave\Support\LeaveAccess;

class LeaveSettingsController extends Controller
{
    public function showPolicy(LeavePolicyService $policy): JsonResponse
    {
        LeaveAccess::authorizeSettings();

        return response()->json(['data' => $policy->all()]);
    }

    public function updatePolicy(Request $request, LeavePolicyService $policy): JsonResponse
    {
        LeaveAccess::authorizeSettings();

        $data = $request->validate([
            'policy' => 'required|array',
        ]);

        $policyPayload = $data['policy'];
        if (array_key_exists('application_min_notice_days', $policyPayload)) {
            $policyPayload['application_min_notice_days'] = max(
                0,
                (int) $policyPayload['application_min_notice_days']
            );
        }

        $policy->save($policyPayload);

        return response()->json([
            'message' => 'Leave policy and accumulation rules saved.',
            'data' => $policy->all(),
        ]);
    }

    public function types(): JsonResponse
    {
        LeaveAccess::authorizeSettings();

        $types = LeaveType::query()->orderBy('sort_order')->orderBy('leave_name')->get();

        return response()->json([
            'data' => LeaveTypeResource::collection($types)->resolve(),
        ]);
    }

    public function storeType(Request $request): JsonResponse
    {
        LeaveAccess::authorizeSettings();

        $data = $this->validatedType($request);
        $type = LeaveType::query()->create($data + ['is_active' => true]);
        PortalReferenceCache::bustLeaveTypes();

        return response()->json([
            'message' => 'Leave type created.',
            'data' => new LeaveTypeResource($type),
        ], 201);
    }

    public function updateType(Request $request, int $leaveId): JsonResponse
    {
        LeaveAccess::authorizeSettings();

        $type = LeaveType::query()->findOrFail($leaveId);
        $type->update($this->validatedType($request));
        PortalReferenceCache::bustLeaveTypes();

        return response()->json([
            'message' => 'Leave type updated.',
            'data' => new LeaveTypeResource($type->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedType(Request $request): array
    {
        $validated = $request->validate([
            'leave_name' => 'required|string|max:100',
            'code' => 'nullable|string|max:40',
            'leave_days' => 'numeric|min:0',
            'is_accrued' => 'boolean',
            'accrual_rate' => 'numeric|min:0',
            'requires_hr_approval' => 'boolean',
            'requires_medical_certificate' => 'boolean',
            'medical_report_after_days' => 'nullable|integer|min:0',
            'max_instances' => 'nullable|integer|min:0',
            'max_days_per_year' => 'nullable|numeric|min:0',
            'min_days_per_year' => 'nullable|numeric|min:0',
            'deduct_compensatory_first' => 'boolean',
            'policy_notes' => 'nullable|string|max:5000',
        ]);

        return [
            'leave_name' => $validated['leave_name'],
            'code' => ($validated['code'] ?? null) ?: null,
            'leave_days' => (int) ($validated['leave_days'] ?? 0),
            'is_accrued' => ! empty($validated['is_accrued']) ? 1 : 0,
            'accrual_rate' => (float) ($validated['accrual_rate'] ?? 0),
            'requires_hr_approval' => (bool) ($validated['requires_hr_approval'] ?? false),
            'requires_medical_certificate' => (bool) ($validated['requires_medical_certificate'] ?? false),
            'medical_report_after_days' => $validated['medical_report_after_days'] ?? null,
            'max_instances' => $validated['max_instances'] ?? null,
            'max_days_per_year' => $validated['max_days_per_year'] ?? null,
            'min_days_per_year' => $validated['min_days_per_year'] ?? null,
            'deduct_compensatory_first' => (bool) ($validated['deduct_compensatory_first'] ?? false),
            'policy_notes' => ($validated['policy_notes'] ?? null) ?: null,
        ];
    }
}
