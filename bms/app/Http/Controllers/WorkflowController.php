<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\WorkflowDefinition;
use App\Models\Staff;
use App\Models\Approver;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    /**
     * Display a listing of the workflows.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $workflows = Workflow::all();
        return view('workflows.index', compact('workflows'));
    }

    /**
     * Show the form for creating a new workflow.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('workflows.create');
    }

    /**
     * Store a newly created workflow in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'workflow_name' => 'required|string|max:200',
            'Description' => 'required|string|max:200',
            'is_active' => 'boolean',
        ]);

        // Set default value for is_active if not provided
        $validated['is_active'] = $request->has('is_active') ? 1 : 0;

        Workflow::create($validated);

        return redirect()->route('workflows.index')
            ->with('success', 'Workflow created successfully.');
    }

    /**
     * Display the specified workflow.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\View\View
     */
    public function show(Workflow $workflow)
    {
        $workflowDefinitions = $workflow->workflowDefinitions()
            ->orderBy('approval_order')
            ->get();

        return view('workflows.show', compact('workflow', 'workflowDefinitions'));
    }

    /**
     * Show the form for editing the specified workflow.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\View\View
     */
    public function edit(Workflow $workflow)
    {
        return view('workflows.edit', compact('workflow'));
    }

    /**
     * Update the specified workflow in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Workflow $workflow)
    {
        $validated = $request->validate([
            'workflow_name' => 'required|string|max:200',
            'Description' => 'required|string|max:200',
            'is_active' => 'boolean',
        ]);

        // Set default value for is_active if not provided
        $validated['is_active'] = $request->has('is_active') ? 1 : 0;

        $workflow->update($validated);

        return redirect()->route('workflows.index')
            ->with('success', 'Workflow updated successfully.');
    }

    /**
     * Remove the specified workflow from storage.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Workflow $workflow)
    {
        // Check if there are any memos using this workflow
        if ($workflow->memos()->count() > 0) {
            return redirect()->route('workflows.index')
                ->with('error', 'Cannot delete workflow. It is currently being used by memos.');
        }

        // Delete workflow definitions first
        $workflow->workflowDefinitions()->delete();

        // Delete workflow
        $workflow->delete();

        return redirect()->route('workflows.index')
            ->with('success', 'Workflow deleted successfully.');
    }

    /**
     * Add workflow definition form
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\View\View
     */
    public function addDefinition(Workflow $workflow)
    {
        return view('workflows.add_definition', compact('workflow'));
    }

    /**
     * Store a newly created workflow definition in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeDefinition(Request $request, Workflow $workflow)
    {
        $validated = $request->validate([
            'role' => 'required|string|max:100',
            'approval_order' => 'required|integer|min:1',
            'is_enabled' => 'boolean',
        ]);

        // Set default value for is_enabled if not provided
        $validated['is_enabled'] = $request->has('is_enabled') ? 1 : 0;
        $validated['workflow_id'] = $workflow->id;

        WorkflowDefinition::create($validated);

        return redirect()->route('workflows.show', $workflow->id)
            ->with('success', 'Workflow definition added successfully.');
    }

    /**
     * Show form for assigning staff to workflow definition.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\View\View
     */
    public function assignStaff(Workflow $workflow)
    {
        $workflowDefinitions = $workflow->workflowDefinitions()
            ->orderBy('approval_order')
            ->with(['approvers.staff'])
            ->get();

        $availableStaff = Staff::where('status', 'active')
            ->orderBy('fname')
            ->get();

        return view('workflows.assign_staff', compact('workflow', 'workflowDefinitions', 'availableStaff'));
    }

    /**
     * Store staff assignments for workflow definition.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeStaff(Request $request, Workflow $workflow)
    {
        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.workflow_dfn_id' => 'required|exists:workflow_definition,id',
            'assignments.*.staff_id' => 'required|exists:staff,staff_id',
            'assignments.*.oic_staff_id' => 'nullable|exists:staff,staff_id',
            'assignments.*.start_date' => 'required|date',
            'assignments.*.end_date' => 'nullable|date|after:start_date',
        ]);

        foreach ($validated['assignments'] as $assignment) {
            // Update or create approver record
            Approver::updateOrCreate(
                [
                    'workflow_dfn_id' => $assignment['workflow_dfn_id'],
                    'staff_id' => $assignment['staff_id']
                ],
                [
                    'oic_staff_id' => $assignment['oic_staff_id'] ?? null,
                    'start_date' => $assignment['start_date'],
                    'end_date' => $assignment['end_date']
                ]
            );
        }

        return redirect()->route('workflows.show', $workflow->id)
            ->with('success', 'Staff assignments updated successfully.');
    }

    /**
     * Handle AJAX staff assignment request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxStoreStaff(Request $request, Workflow $workflow)
    {
        try {
            $validated = $request->validate([
                'workflow_dfn_id' => 'required|exists:workflow_definition,id',
                'staff_id' => 'required|exists:staff,staff_id',
                'oic_staff_id' => 'nullable|exists:staff,staff_id',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            $approver = Approver::create([
                'workflow_dfn_id' => $validated['workflow_dfn_id'],
                'staff_id' => $validated['staff_id'],
                'oic_staff_id' => $validated['oic_staff_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            $approver->load(['staff', 'oicStaff']);

            return response()->json([
                'success' => true,
                'message' => 'Staff assigned successfully',
                'approver' => $approver
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Handle AJAX staff removal request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Workflow  $workflow
     * @param  int  $approverId
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxRemoveStaff(Request $request, Workflow $workflow, $approverId)
    {
        try {
            $approver = Approver::findOrFail($approverId);
            $approver->delete();

            return response()->json([
                'success' => true,
                'message' => 'Staff removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}