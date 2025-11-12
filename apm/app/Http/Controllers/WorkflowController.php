<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
        
        // Load divisions for display
        $divisions = \App\Models\Division::orderBy('division_name')->get()->keyBy('id');

        return view('workflows.show', compact('workflow', 'workflowDefinitions', 'divisions'));
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
        try {
            // Check if workflow is being used by any requests
            $isUsed = DB::table('request_arfs')
                ->where('forward_workflow_id', $workflow->id)
                ->orWhere('reverse_workflow_id', $workflow->id)
                ->exists();

            if ($isUsed) {
                return redirect()->route('workflows.index')
                    ->with('error', 'Cannot delete workflow. It is currently being used by ARF requests.');
            }

            // Delete workflow definitions first (cascade)
            $workflow->workflowDefinitions()->delete();
            
            // Delete the workflow
            $workflow->delete();

            return redirect()->route('workflows.index')
                ->with('success', 'Workflow deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Workflow deletion failed', [
                'workflow_id' => $workflow->id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('workflows.index')
                ->with('error', 'Failed to delete workflow. Please try again.');
        }
    }


    /**
     * Add workflow definition form
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\View\View
     */
    public function addDefinition(Workflow $workflow)
    {
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        $fundTypes = \App\Models\FundType::orderBy('name')->get();
        $funders = \App\Models\Funder::where('is_active', true)->orderBy('name')->get();
        
        return view('workflows.add_definition', compact('workflow', 'divisions', 'fundTypes', 'funders'));
    }

    /**
     * Show the form for assigning models to workflows.
     *
     * @return \Illuminate\View\View
     */
    public function assignModels()
    {
        $workflows = Workflow::where('is_active', 1)->get();
        
        $models = [
            'Matrix' => 'Matrix',
            'Activity' => 'Activity', 
            'NonTravelMemo' => 'Non Travel Memo',
            'SpecialMemo' => 'Special Memo',
            'RequestARF' => 'Request ARF',
            'ServiceRequest' => 'Service Request'
        ];
        
        // Get current assignments
        $currentAssignments = WorkflowModel::with('workflow')->get()->keyBy('model_name');
        
        return view('workflows.assign-models', compact('workflows', 'models', 'currentAssignments'));
    }

    /**
     * Store model workflow assignments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeModelAssignments(Request $request)
    {
        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.model' => 'required|string|in:Matrix,Activity,NonTravelMemo,SpecialMemo,RequestARF,ServiceRequest',
            'assignments.*.workflow_id' => 'required|integer|exists:workflows,id',
        ]);

        try {
            DB::beginTransaction();
            
            foreach ($validated['assignments'] as $assignment) {
                WorkflowModel::setWorkflowIdForModel(
                    $assignment['model'],
                    $assignment['workflow_id'],
                    "Auto-assigned workflow for {$assignment['model']} model"
                );
            }
            
            DB::commit();

            return redirect()->route('workflows.assign-models')
                ->with('success', 'Model workflow assignments updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Model workflow assignment failed', [
                'error' => $e->getMessage(),
                'assignments' => $validated['assignments']
            ]);
            
            return redirect()->route('workflows.assign-models')
                ->with('error', 'Failed to update model workflow assignments. Please try again.');
        }
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
            'is_division_specific' => 'boolean',
            'fund_type' => 'nullable|integer|exists:fund_types,id',
            'memo_print_section' => 'nullable|string|in:from,to,through,others',
            'print_order' => 'nullable|integer|min:1',
            'category' => 'nullable|string|max:20',
            'division_reference_column' => 'nullable|string|max:20',
            'triggers_category_check' => 'boolean',
            'divisions' => 'nullable|array',
            'divisions.*' => 'integer|exists:divisions,id',
            'allowed_funders' => 'nullable|array',
            'allowed_funders.*' => 'integer|exists:funders,id',
        ]);

        // Set default values for checkboxes if not provided
        $validated['is_enabled'] = $request->has('is_enabled') ? 1 : 0;
        $validated['is_division_specific'] = $request->has('is_division_specific') ? 1 : 0;
        $validated['triggers_category_check'] = $request->has('triggers_category_check') ? 1 : 0;
        $validated['workflow_id'] = $workflow->id;
        
        // Set default memo_print_section if not provided
        if (!isset($validated['memo_print_section'])) {
            $validated['memo_print_section'] = 'through';
        }
        
        // Convert arrays to JSON for storage
        if (isset($validated['divisions'])) {
            $validated['divisions'] = json_encode($validated['divisions']);
        }
        if (isset($validated['allowed_funders'])) {
            $validated['allowed_funders'] = json_encode($validated['allowed_funders']);
        }

        WorkflowDefinition::create($validated);

        return redirect()->route('workflows.show', $workflow->id)
            ->with('success', 'Workflow definition added successfully.');
    }

    /**
     * Show the form for editing a workflow definition.
     *
     * @param  \App\Models\Workflow  $workflow
     * @param  \App\Models\WorkflowDefinition  $definition
     * @return \Illuminate\View\View
     */
    public function editDefinition(Workflow $workflow, WorkflowDefinition $definition)
    {
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        $fundTypes = \App\Models\FundType::orderBy('name')->get();
        $funders = \App\Models\Funder::where('is_active', true)->orderBy('name')->get();
        
        return view('workflows.edit_definition', compact('workflow', 'definition', 'divisions', 'fundTypes', 'funders'));
    }

    /**
     * Update the specified workflow definition in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Workflow  $workflow
     * @param  \App\Models\WorkflowDefinition  $definition
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateDefinition(Request $request, Workflow $workflow, WorkflowDefinition $definition)
    {
        $validated = $request->validate([
            'role' => 'required|string|max:100',
            'approval_order' => 'required|integer|min:1',
            'is_enabled' => 'boolean',
            'is_division_specific' => 'boolean',
            'fund_type' => 'nullable|integer|exists:fund_types,id',
            'memo_print_section' => 'nullable|string|in:from,to,through,others',
            'print_order' => 'nullable|integer|min:1',
            'category' => 'nullable|string|max:20',
            'division_reference_column' => 'nullable|string|max:20',
            'triggers_category_check' => 'boolean',
            'divisions' => 'nullable|array',
            'divisions.*' => 'integer|exists:divisions,id',
            'allowed_funders' => 'nullable|array',
            'allowed_funders.*' => 'integer|exists:funders,id',
        ]);

        // Set default values for checkboxes if not provided
        $validated['is_enabled'] = $request->has('is_enabled') ? 1 : 0;
        $validated['is_division_specific'] = $request->has('is_division_specific') ? 1 : 0;
        $validated['triggers_category_check'] = $request->has('triggers_category_check') ? 1 : 0;
        
        // Set default memo_print_section if not provided
        if (!isset($validated['memo_print_section'])) {
            $validated['memo_print_section'] = 'through';
        }
        
        // Convert arrays to JSON for storage
        if (isset($validated['divisions'])) {
            $validated['divisions'] = json_encode($validated['divisions']);
        } else {
            $validated['divisions'] = null;
        }
        if (isset($validated['allowed_funders'])) {
            $validated['allowed_funders'] = json_encode($validated['allowed_funders']);
        } else {
            $validated['allowed_funders'] = null;
        }

        $definition->update($validated);

        return redirect()->route('workflows.show', $workflow->id)
            ->with('success', 'Workflow definition updated successfully.');
    }

    /**
     * Remove the specified workflow definition from storage.
     *
     * @param  \App\Models\Workflow  $workflow
     * @param  \App\Models\WorkflowDefinition  $definition
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteDefinition(Workflow $workflow, WorkflowDefinition $definition)
    {
        try {
            Log::info('Attempting to delete workflow definition', [
                'workflow_id' => $workflow->id,
                'definition_id' => $definition->id,
                'definition_role' => $definition->role
            ]);

            // Check if definition is being used by any approvers
            $isUsed = \DB::table('approvers')
                ->where('workflow_dfn_id', $definition->id)
                ->exists();

            if ($isUsed) {
                Log::info('Cannot delete definition - in use by approvers', [
                    'definition_id' => $definition->id
                ]);
                return redirect()->route('workflows.show', $workflow->id)
                    ->with('error', 'Cannot delete workflow definition. It is currently being used by approvers.');
            }

            $definition->delete();

            Log::info('Workflow definition deleted successfully', [
                'definition_id' => $definition->id
            ]);

            return redirect()->route('workflows.show', $workflow->id)
                ->with('success', 'Workflow definition deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Workflow definition deletion failed', [
                'workflow_id' => $workflow->id,
                'definition_id' => $definition->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('workflows.show', $workflow->id)
                ->with('error', 'Failed to delete workflow definition. Please try again.');
        }
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
            Log::info('Workflow store-staff request data:', $request->all());
            
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

            Log::info('Validation passed, creating approver with data:', $validated);

            // Delete existing approvers for this workflow definition (replace logic)
            Approver::where('workflow_dfn_id', $validated['workflow_dfn_id'])->delete();
            Log::info('Deleted existing approvers for workflow definition ID: ' . $validated['workflow_dfn_id']);

            $approver = Approver::create([
                'workflow_dfn_id' => $validated['workflow_dfn_id'],
                'staff_id' => $validated['staff_id'],
                'oic_staff_id' => $validated['oic_staff_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            $approver->load(['staff', 'oicStaff']);

            Log::info('Approver created successfully:', $approver->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Staff assigned successfully',
                'approver' => $approver
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed:', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Exception in ajaxStoreStaff:', [
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