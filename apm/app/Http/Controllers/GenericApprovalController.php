<?php

namespace App\Http\Controllers;

use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;

class GenericApprovalController extends Controller
{
    protected ApprovalService $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Resolve model from route parameters.
     */
    protected function resolveModel(string $modelType, int $id): ?Model
    {
        $modelClass = "App\\Models\\{$modelType}";
        
        if (!class_exists($modelClass)) {
            return null;
        }

        return $modelClass::find($id);
    }

    /**
     * Update the approval status of any model.
     */
    public function updateStatus(Request $request, string $model, int $id): RedirectResponse
    {
        $request->validate(['action' => 'required']);

        $modelInstance = $this->resolveModel($model, $id);
        
        if (!$modelInstance) {
            return redirect()->back()->with('error', 'Model not found.');
        }

        $userId = user_session('staff_id');
        
        // Check if user can take action
        if (!$this->approvalService->canTakeAction($modelInstance, $userId)) {
            return redirect()->back()->with('error', 'You are not authorized to perform this action.');
        }

        // Process the approval using the model's own approval workflow
        if (method_exists($modelInstance, 'updateApprovalStatus')) {
            $modelInstance->updateApprovalStatus($request->action, $request->comment ?? '');
        } else {
            // Fallback to approval service
            $this->approvalService->processApproval(
                $modelInstance, 
                $request->action, 
                $request->comment ?? '', 
                $userId
            );
        }

        // Send notifications
        $this->sendNotification($modelInstance, $request->action);

        $message = ucfirst(class_basename($modelInstance)) . " status updated successfully";

        return redirect()->back()->with('success', $message);
    }

    /**
     * Submit a model for approval.
     */
    public function submitForApproval(string $model, int $id): RedirectResponse
    {
        $modelInstance = $this->resolveModel($model, $id);
        
        if (!$modelInstance) {
            return redirect()->back()->with('error', 'Model not found.');
        }

        $userId = user_session('staff_id');
        
        // Check if user is the creator
        if ($modelInstance->staff_id != $userId) {
            return redirect()->back()->with('error', 'Only the creator can submit for approval.');
        }

        // Submit for approval using the model's own method
        if (method_exists($modelInstance, 'submitForApproval')) {
            $modelInstance->submitForApproval();
        } else {
            // Fallback to manual submission
            $modelInstance->overall_status = 'pending';
            $modelInstance->approval_level = 1;
            $modelInstance->forward_workflow_id = 1;
            $modelInstance->save();
            
            // Save approval trail
            if (method_exists($modelInstance, 'saveApprovalTrail')) {
                $modelInstance->saveApprovalTrail('Submitted for approval', 'submitted');
            }
        }

        // Send notification
        $this->sendNotification($modelInstance, 'submitted');

        $message = ucfirst(class_basename($modelInstance)) . " submitted for approval successfully";

        return redirect()->back()->with('success', $message);
    }

    /**
     * Batch update status for multiple models.
     */
    public function batchUpdateStatus(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => 'required',
            'model_ids' => 'required|array',
            'model_type' => 'required|string'
        ]);

        $modelClass = $request->model_type;
        $modelIds = $request->model_ids;
        $action = $request->action;
        $userId = user_session('staff_id');

        $processedCount = 0;

        foreach ($modelIds as $modelId) {
            $model = $modelClass::find($modelId);
            
            if ($model && $this->approvalService->canTakeAction($model, $userId)) {
                $this->approvalService->processApproval($model, $action, $request->comment ?? '', $userId);
                $this->sendNotification($model, $action);
                $processedCount++;
            }
        }

        $message = "Successfully processed {$processedCount} " . strtolower(class_basename($modelClass)) . "(s)";

        return redirect()->back()->with('success', $message);
    }

    /**
     * Show approval trail for a model.
     */
    public function showApprovalTrail(string $model, int $id)
    {
        $modelInstance = $this->resolveModel($model, $id);
        
        if (!$modelInstance) {
            return redirect()->back()->with('error', 'Model not found.');
        }

        $approvalTrails = $this->approvalService->getApprovalTrails($modelInstance);
        
        return view('approvals.trail', compact('modelInstance', 'approvalTrails'));
    }

    /**
     * Get pending approvals for the current user.
     */
    public function pendingApprovals()
    {
        $userId = user_session('staff_id');
        
        // This would need to be implemented based on your specific models
        // For now, returning empty array
        $pendingApprovals = [];
        
        return view('approvals.pending', compact('pendingApprovals'));
    }

    /**
     * Send notification based on approval action.
     */
    protected function sendNotification(Model $model, string $action): void
    {
        $modelType = class_basename($model);
        $notificationType = $action;

        // Get notification recipient
        $recipient = $this->approvalService->getNotificationRecipient($model);

        if ($recipient) {
            // Send email notification
            if (function_exists('send_matrix_email_notification')) {
                send_matrix_email_notification($model, $notificationType);
            }
        }
    }

    /**
     * Check if user can approve a specific model.
     */
    public function canApprove(Model $model): bool
    {
        $userId = user_session('staff_id');
        return $this->approvalService->canTakeAction($model, $userId);
    }

    /**
     * Check if user has already approved a specific model.
     */
    public function hasApproved(Model $model): bool
    {
        $userId = user_session('staff_id');
        return $this->approvalService->hasUserApproved($model, $userId);
    }
} 