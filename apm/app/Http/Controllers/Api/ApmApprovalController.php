<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpecialMemo;
use App\Models\Matrix;
use App\Models\Activity;
use App\Models\NonTravelMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use App\Models\ChangeRequest;
use App\Services\ApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApmApprovalController extends Controller
{
    /**
     * Apply action (approve, reject, return, cancel) to a document.
     * Cancel = return when current user is HOD (special memo only); same as web special-memo.
     */
    public function action(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:special_memo,non_travel_memo,single_memo,matrix,service_request,arf,change_request',
            'id' => 'required|integer',
            'action' => 'required|string|in:approved,rejected,returned,cancelled',
            'comment' => 'nullable|string|max:1000',
            'available_budget' => 'nullable|numeric|min:0',
        ]);

        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $staffId = (int) ($sessionData['staff_id'] ?? 0);
        $modelType = $this->modelTypeFor($request->type);
        $modelId = (int) $request->id;
        $action = $request->action;

        // For special_memo, "cancelled" is allowed (HOD return). For others, map cancel to returned.
        if ($action === 'cancelled' && $request->type !== 'special_memo') {
            $action = 'returned';
        }

        $model = $this->resolveModel($modelType, $modelId);
        if (!$model) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        $approvalService = app(ApprovalService::class);
        if (!$approvalService->canTakeAction($model, $staffId) && !is_with_creator_generic($model)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to perform this action.'], 403);
        }

        $comment = $request->input('comment') ?? $request->input('remarks') ?? '';
        $additionalData = [];
        if ($request->filled('available_budget')) {
            $additionalData['available_budget'] = $request->available_budget;
        }

        $approvalService->processApproval($model, $action, $comment, $staffId, $additionalData);
        send_generic_email_notification($model, $action);

        return response()->json([
            'success' => true,
            'message' => 'Action applied successfully.',
            'data' => ['action' => $action, 'document_type' => $request->type, 'document_id' => $modelId],
        ]);
    }

    private function resolveModel(string $modelType, int $id): ?Model
    {
        $modelClass = "App\\Models\\{$modelType}";
        if (!class_exists($modelClass)) {
            return null;
        }
        return $modelClass::find($id);
    }

    private function modelTypeFor(string $type): string
    {
        $map = [
            'special_memo' => 'SpecialMemo',
            'non_travel_memo' => 'NonTravelMemo',
            'single_memo' => 'Activity',
            'matrix' => 'Matrix',
            'service_request' => 'ServiceRequest',
            'arf' => 'RequestARF',
            'change_request' => 'ChangeRequest',
        ];
        return $map[$type] ?? 'SpecialMemo';
    }
}
