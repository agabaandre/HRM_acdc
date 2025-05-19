<?php

namespace App\Http\Controllers;

use App\Models\RequestType;
use App\Models\Activity;
use App\Models\Memo;
use App\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = RequestType::query();

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $status = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $status);
        }

        $requestTypes = $query->latest()->paginate(10);

        return view('request-types.index', compact('requestTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $workflows = Workflow::where('is_active', 1)->orderBy('name')->get();
        return view('request-types.create', compact('workflows'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'request_type' => 'required|string|max:255|unique:request_types,request_type',
            'description' => 'nullable|string',
            'workflow_id' => 'nullable|exists:workflows,id',
            'is_active' => 'nullable'
        ]);

        $requestType = new RequestType();
        $requestType->request_type = $request->request_type;
        $requestType->description = $request->description;
        $requestType->workflow_id = $request->workflow_id;
        $requestType->is_active = $request->has('is_active') ? 1 : 0;
        $requestType->save();

        return redirect()->route('request-types.index')
            ->with('success', 'Request type created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $requestType = RequestType::with('workflow')->findOrFail($id);
        
        // Get related activities and memos
        $activities = Activity::where('request_type_id', $id)->latest()->take(5)->get();
        $memos = Memo::where('request_type_id', $id)->latest()->take(5)->get();

        return view('request-types.show', compact('requestType', 'activities', 'memos'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $requestType = RequestType::findOrFail($id);
        $workflows = Workflow::where('is_active', 1)->orderBy('name')->get();
        
        return view('request-types.edit', compact('requestType', 'workflows'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $requestType = RequestType::findOrFail($id);

        $request->validate([
            'request_type' => 'required|string|max:255|unique:request_types,request_type,' . $id,
            'description' => 'nullable|string',
            'workflow_id' => 'nullable|exists:workflows,id',
            'is_active' => 'nullable'
        ]);

        $requestType->request_type = $request->request_type;
        $requestType->description = $request->description;
        $requestType->workflow_id = $request->workflow_id;
        $requestType->is_active = $request->has('is_active') ? 1 : 0;
        $requestType->save();

        return redirect()->route('request-types.index')
            ->with('success', 'Request type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $requestType = RequestType::findOrFail($id);
        
        // Check if the request type is used in activities or memos
        $activitiesCount = Activity::where('request_type_id', $id)->count();
        $memosCount = Memo::where('request_type_id', $id)->count();
        
        if ($activitiesCount > 0 || $memosCount > 0) {
            return redirect()->route('request-types.index')
                ->with('error', 'Cannot delete this request type because it is used in activities or memos.');
        }
        
        $requestType->delete();
        return redirect()->route('request-types.index')
            ->with('success', 'Request type deleted successfully.');
    }
}
