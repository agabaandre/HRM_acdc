<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\WorkflowDefinition;
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
}