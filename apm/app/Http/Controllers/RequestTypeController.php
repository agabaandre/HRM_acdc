<?php

namespace App\Http\Controllers;

use App\Models\RequestType;
use Illuminate\Http\Request;

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
            $query->where('name', 'like', "%{$search}%");
        }

        $requestTypes = $query->latest()->paginate(10);

        return view('request-types.index', compact('requestTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('request-types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:request_types,name'
        ]);

        RequestType::create($validated);

        return redirect()
            ->route('request-types.index')
            ->with('success', 'Request type created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $requestType = RequestType::findOrFail($id);
        return view('request-types.show', compact('requestType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $requestType = RequestType::findOrFail($id);
        return view('request-types.edit', compact('requestType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $requestType = RequestType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:request_types,name,' . $id
        ]);

        $requestType->update($validated);

        return redirect()
            ->route('request-types.index')
            ->with('success', 'Request type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $requestType = RequestType::findOrFail($id);
        
        // Check if the request type is in use (you can add this check if needed)
        // if ($requestType->someRelation()->exists()) {
        //     return redirect()
        //         ->route('request-types.index')
        //         ->with('error', 'Cannot delete this request type because it is in use.');
        // }
        
        $requestType->delete();
        
        return redirect()
            ->route('request-types.index')
            ->with('success', 'Request type deleted successfully.');
    }
}
