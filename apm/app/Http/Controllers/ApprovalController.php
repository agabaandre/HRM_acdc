<?php

namespace App\Http\Controllers;

use App\Models\Memo;
use App\Models\Workflow;
use App\Models\WorkflowDefinition;
use App\Models\ApprovalCondition;
use App\Models\Approver;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    /**
     * Display a listing of pending approvals for the current user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get user ID from session (from the external system)
        $userId = session('user')['user_id'] ?? null;

        if (!$userId) {
            return view('approvals.index', ['pendingApprovals' => [], 'error' => 'User ID not found in session']);
        }

        // Get all workflow definition IDs where this user is an approver
        $approverWorkflowDfnIds = Approver::where('staff_id', $userId)
            ->orWhere('oic_staff_id', $userId)
            ->pluck('workflow_dfn_id');

        // Get the workflow definitions
        $workflowDefinitions = WorkflowDefinition::whereIn('id', $approverWorkflowDfnIds)->get();

        // Get the workflow IDs associated with these definitions
        $workflowIds = $workflowDefinitions->pluck('workflow_id')->unique();

        // Get all memos that are using these workflows
        $pendingApprovals = Memo::whereIn('workflow_id', $workflowIds)->get();

        return view('approvals.index', compact('pendingApprovals'));
    }

    /**
     * Show a specific memo for approval.
     *
     * @param  \App\Models\Memo  $memo
     * @return \Illuminate\View\View
     */
    public function show(Memo $memo)
    {
        // Get the workflow and its definitions
        $workflow = $memo->workflow;
        $workflowDefinitions = $workflow->workflowDefinitions()
            ->orderBy('approval_order')
            ->get();

        // Get current user ID from session
        $userId = session('user')['user_id'] ?? null;

        // Determine if the current user can approve this memo
        $canApprove = false;

        if ($userId) {
            $canApprove = Approver::whereIn('workflow_dfn_id', $workflowDefinitions->pluck('id'))
                ->where(function ($query) use ($userId) {
                    $query->where('staff_id', $userId)
                        ->orWhere('oic_staff_id', $userId);
                })
                ->exists();
        }

        return view('approvals.show', compact('memo', 'workflow', 'workflowDefinitions', 'canApprove'));
    }

    /**
     * Process an approval action.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Memo  $memo
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(Request $request, Memo $memo)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,return',
            'comments' => 'nullable|string',
        ]);

        // Get user ID from session
        $userId = session('user')['user_id'] ?? null;

        if (!$userId) {
            return redirect()->back()->with('error', 'User ID not found in session');
        }

        // Process the approval based on the action
        switch($validated['action']) {
            case 'approve':
                // Logic for approving the memo
                // In a real implementation, you would update the memo status
                // or create an approval record in a separate approvals table
                break;

            case 'reject':
                // Logic for rejecting the memo
                break;

            case 'return':
                // Logic for returning the memo for correction
                break;
        }

        return redirect()->route('approvals.index')
            ->with('success', 'Memo ' . $validated['action'] . 'd successfully');
    }

    /**
     * Display the approval history for a memo.
     *
     * @param  \App\Models\Memo  $memo
     * @return \Illuminate\View\View
     */
    public function history(Memo $memo)
    {
        // In a real implementation, you would fetch approval history from a dedicated table
        // For now, we'll just return a view with the memo
        return view('approvals.history', compact('memo'));
    }
}
