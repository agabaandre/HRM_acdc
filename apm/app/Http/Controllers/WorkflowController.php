<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\WorkflowDefinition;
use Illuminate\Support\Facades\Log;
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
     * Show approvers for the workflow.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\View\View
     */
    public function approvers(Workflow $workflow)
    {
        $workflowDefinitions = $workflow->workflowDefinitions()
            ->with(['approvers.staff', 'approvers.oicStaff'])
            ->orderBy('approval_order')
            ->get();

        // Get all staff for potential assignments
        $availableStaff = Staff::active()
            ->select('staff_id', 'fname', 'lname', 'division_name')
            ->orderBy('fname')
            ->get();

        return view('workflows.approvers', compact('workflow', 'workflowDefinitions', 'availableStaff'));
    }

    /**
     * Bulk assign approvers to a workflow definition.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkAssignApprovers(Request $request, Workflow $workflow)
    {
        try {
            $validated = $request->validate([
                'workflow_dfn_id' => 'required|exists:workflow_definition,id',
                'staff_ids' => 'required|array|min:1',
                'staff_ids.*' => 'exists:staff,staff_id',
                'oic_staff_id' => 'nullable|exists:staff,staff_id',
                'start_date' => 'required|date',
            ]);

            $createdApprovers = [];
            $errors = [];

            foreach ($validated['staff_ids'] as $staffId) {
                try {
                    // Check if this staff is already assigned to this workflow definition
                    $existingApprover = Approver::where('workflow_dfn_id', $validated['workflow_dfn_id'])
                        ->where('staff_id', $staffId)
                        ->first();

                    if ($existingApprover) {
                        $errors[] = "Staff ID {$staffId} is already assigned to this workflow definition";
                        continue;
                    }

                    $approver = Approver::create([
                        'workflow_dfn_id' => $validated['workflow_dfn_id'],
                        'staff_id' => $staffId,
                        'oic_staff_id' => $validated['oic_staff_id'],
                        'start_date' => $validated['start_date'],
                        'end_date' => null,
                    ]);

                    $approver->load(['staff', 'oicStaff']);
                    $createdApprovers[] = $approver;
                } catch (\Exception $e) {
                    $errors[] = "Error assigning staff ID {$staffId}: " . $e->getMessage();
                }
            }

            $message = count($createdApprovers) > 0 
                ? "Successfully assigned " . count($createdApprovers) . " approver(s)"
                : "No approvers were assigned";

            if (count($errors) > 0) {
                $message .= ". Errors: " . implode(', ', $errors);
            }

            return response()->json([
                'success' => count($createdApprovers) > 0,
                'message' => $message,
                'created_approvers' => $createdApprovers,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update an approver assignment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Workflow  $workflow
     * @param  \App\Models\Approver  $approver
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateApprover(Request $request, Workflow $workflow, Approver $approver)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|exists:staff,staff_id',
                'oic_staff_id' => 'nullable|exists:staff,staff_id',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            $approver->update($validated);
            $approver->load(['staff', 'oicStaff']);

            return response()->json([
                'success' => true,
                'message' => 'Approver assignment updated successfully',
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
     * Show the form for assigning staff to workflow.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\View\View
     */
    public function assignStaff(Workflow $workflow)
    {
        $workflowDefinitions = $workflow->workflowDefinitions()
            ->orderBy('approval_order')
            ->with(['approvers.staff', 'approvers.oicStaff'])
            ->get();

        $availableStaff = Staff::active()
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
            // Log the incoming request data for debugging
            \Log::info('Workflow store-staff request data:', $request->all());
            
            $validated = $request->validate([
                'workflow_dfn_id' => 'required|exists:workflow_definition,id',
                'staff_id' => 'required|exists:staff,staff_id',
                'oic_staff_id' => 'nullable|exists:staff,staff_id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            // Additional validation: start_date and end_date are required if OIC is selected
            if ($validated['oic_staff_id'] && (!$validated['start_date'] || !$validated['end_date'])) {
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    response()->json([
                        'success' => false,
                        'message' => 'Start date and end date are required when OIC is selected',
                        'errors' => [
                            'start_date' => ['Start date is required when OIC is selected'],
                            'end_date' => ['End date is required when OIC is selected']
                        ]
                    ], 422)
                );
            }

            \Log::info('Validation passed, creating approver with data:', $validated);

            // Delete existing approvers for this workflow definition (replace logic)
            Approver::where('workflow_dfn_id', $validated['workflow_dfn_id'])->delete();
            \Log::info('Deleted existing approvers for workflow definition ID: ' . $validated['workflow_dfn_id']);

            $approver = Approver::create([
                'workflow_dfn_id' => $validated['workflow_dfn_id'],
                'staff_id' => $validated['staff_id'],
                'oic_staff_id' => $validated['oic_staff_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            $approver->load(['staff', 'oicStaff']);

            \Log::info('Approver created successfully:', $approver->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Staff assigned successfully',
                'approver' => $approver
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed:', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Exception in ajaxStoreStaff:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Test method for approver removal.
     *
     * @param  int  $approverId
     * @return \Illuminate\Http\JsonResponse
     */
    public function testRemove($approverId)
    {
        try {
            $approver = Approver::findOrFail($approverId);
            return response()->json([
                'success' => true,
                'message' => 'Approver found',
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