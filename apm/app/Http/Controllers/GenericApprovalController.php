<?php

namespace App\Http\Controllers;

use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        $modelInstance = $this->resolveModel($model, $id);

    
        
        //dd($modelInstance->has_extramural);
        //dd($modelInstance->has_intramural);
        //dd($this->approvalService->canTakeAction($modelInstance, user_session('staff_id')));
        // Debug: Log the incoming request
        Log::info('GenericApprovalController updateStatus called', [
            'request_all' => $request->all(),
            'model' => $model,
            'id' => $id,
            'user_id' => user_session('staff_id')
        ]);

        $request->validate([
            'action' => 'required',
            'available_budget' => 'nullable|numeric|min:0'
        ]);

        $modelInstance = $this->resolveModel($model, $id);
        
        if (!$modelInstance) {
            Log::error('Model not found', ['model' => $model, 'id' => $id]);
            return redirect()->back()->with('error', 'Model not found.');
        }

        $userId = user_session('staff_id');
        
        Log::info('Model resolved', [
            'model_class' => get_class($modelInstance),
            'model_id' => $modelInstance->id,
            'current_status' => $modelInstance->overall_status ?? 'N/A',
            'current_level' => $modelInstance->approval_level ?? 'N/A'
        ]);
       // dd($this->approvalService->canTakeAction($modelInstance, $userId));
        // Check if user can take action
        if (!$this->approvalService->canTakeAction($modelInstance, $userId)&&!is_with_creator_generic($modelInstance)) {
            Log::error('User not authorized', ['user_id' => $userId, 'model_id' => $modelInstance->id]);
            return redirect()->back()->with('error', 'You are not authorized to perform this action.');
        }

        Log::info('User authorized, processing approval', ['action' => $request->action]);

        // Prepare additional data for approval
        $additionalData = [];
        if ($request->has('available_budget') && $request->available_budget !== null) {
            $additionalData['available_budget'] = $request->available_budget;
        }

        // // Process the approval using the model's own approval workflow
        // if (method_exists($modelInstance, 'updateApprovalStatus')) {
        //     //dd('Test');
        //     Log::info('Using model updateApprovalStatus method');
        //     $modelInstance->updateApprovalStatus($request->action, $request->comment ?? '');
        // } else {
            Log::info('Using approval service fallback');
            // Fallback to approval service
            //dd($modelInstance);
            $this->approvalService->processApproval(
                $modelInstance, 
                $request->action, 
                $request->comment ?? '', 
                $userId,
                $additionalData
            );
       // }

        // Send notifications
        $this->sendNotification($modelInstance, $request->action);

        $message = ucfirst(class_basename($modelInstance)) . " status updated successfully";
        Log::info('Approval completed successfully', ['message' => $message]);

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

        // Check if the model is in draft status
        if (!in_array($modelInstance->overall_status,['returned','draft'])) {
            return redirect()->back()->with('error', 'Only draft and returned items can be submitted for approval.');
        }

        // Submit for approval using the model's own method
        if (method_exists($modelInstance, 'submitForApproval')) {
            $modelInstance->submitForApproval();
        } else {
            // Fallback to manual submission
            $modelInstance->overall_status = 'pending';
            $modelInstance->approval_level = 1;
            $modelInstance->forward_workflow_id = 1;
            
            // Set is_draft to false if the property exists
            if (property_exists($modelInstance, 'is_draft')) {
                $modelInstance->is_draft = false;
            }
            
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
        send_matrix_email_notification($model, $action);
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