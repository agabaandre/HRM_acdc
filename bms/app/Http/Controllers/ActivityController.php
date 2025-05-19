<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Matrix;
use App\Models\RequestType;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ActivityController extends Controller
{
    /**
     * Display a listing of activities for a matrix.
     */
    public function index(Matrix $matrix): View
    {
        $activities = $matrix->activities()
            ->with(['staff', 'requestType'])
            ->latest()
            ->paginate(10);

        $requestTypes = RequestType::all();

        return view('activities.index', compact('matrix', 'activities', 'requestTypes'));
    }

    /**
     * Show the form for creating a new activity.
     */
    public function create(Matrix $matrix): View
    {
        $requestTypes = RequestType::all();
        $staff = Staff::active()->get();

        return view('activities.create', compact('matrix', 'requestTypes', 'staff'));
    }

    /**
     * Store a newly created activity.
     */
    public function store(Request $request, Matrix $matrix): RedirectResponse
    {
        $validated = $request->validate([
            'workplan_activity_code' => 'required|string|max:255',
            'staff_id' => 'required|exists:staff,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'location_id' => 'required|array',
            'total_participants' => 'required|integer|min:1',
            'internal_participants' => 'required|array',
            'budget_id' => 'required|array',
            'key_result_area' => 'required|string',
            'request_type_id' => 'required|exists:request_types,id',
            'activity_title' => 'required|string|max:255',
            'background' => 'required|string',
            'activity_request_remarks' => 'required|string',
            'is_sepecial_memo' => 'boolean',
            'budget' => 'required|array',
            'attachment' => 'nullable|array'
        ]);

        // Set initial workflow IDs
        $validated['forward_workflow_id'] = 1; // Default initial workflow
        $validated['reverse_workflow_id'] = 1;

        $matrix->activities()->create($validated);

        return redirect()
            ->route('matrices.activities.index', $matrix)
            ->with('success', 'Activity created successfully.');
    }

    /**
     * Display the specified activity.
     */
    public function show(Matrix $matrix, Activity $activity): View
    {
        $activity->load(['staff', 'requestType', 'serviceRequests']);
        $staff = Staff::active()->get();
        return view('activities.show', compact('matrix', 'activity', 'staff'));
    }

    /**
     * Show the form for editing the specified activity.
     */
    public function edit(Matrix $matrix, Activity $activity): View
    {
        $requestTypes = RequestType::all();
        $staff = Staff::active()->get();

        return view('activities.edit', compact('matrix', 'activity', 'requestTypes', 'staff'));
    }

    /**
     * Update the specified activity.
     */
    public function update(Request $request, Matrix $matrix, Activity $activity): RedirectResponse
    {
        $validated = $request->validate([
            'workplan_activity_code' => 'required|string|max:255',
            'staff_id' => 'required|exists:staff,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'location_id' => 'required|array',
            'total_participants' => 'required|integer|min:1',
            'internal_participants' => 'required|array',
            'budget_id' => 'required|array',
            'key_result_area' => 'required|string',
            'request_type_id' => 'required|exists:request_types,id',
            'activity_title' => 'required|string|max:255',
            'background' => 'required|string',
            'activity_request_remarks' => 'required|string',
            'is_sepecial_memo' => 'boolean',
            'budget' => 'required|array',
            'attachment' => 'nullable|array'
        ]);

        $activity->update($validated);

        return redirect()
            ->route('matrices.activities.index', $matrix)
            ->with('success', 'Activity updated successfully.');
    }

    /**
     * Remove the specified activity.
     */
    public function destroy(Matrix $matrix, Activity $activity): RedirectResponse
    {
        $activity->delete();

        return redirect()
            ->route('matrices.activities.index', $matrix)
            ->with('success', 'Activity deleted successfully.');
    }
}