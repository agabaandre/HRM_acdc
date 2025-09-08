<?php

namespace App\Http\Controllers;

use App\Models\CostItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $costItems = CostItem::latest()->paginate(10);
        return view('cost-items.index', compact('costItems'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cost-items.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cost_type' => 'required|in:Individual Cost,Other Cost',
        ]);

        try {
            CostItem::create($validated);
            return redirect()->route('cost-items.index')
                ->with('msg', 'Cost item created successfully.')
                ->with('type', 'success');
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('msg', 'Error creating cost item: ' . $e->getMessage())
                ->with('type', 'error');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(CostItem $costItem)
    {
        return view('cost-items.show', compact('costItem'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CostItem $costItem)
    {
        return view('cost-items.edit', compact('costItem'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CostItem $costItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cost_type' => 'required|in:Individual Cost,Other Cost',
        ]);

        try {
            $costItem->update($validated);
            return redirect()->route('cost-items.index')
                ->with('msg', 'Cost item updated successfully.')
                ->with('type', 'success');
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('msg', 'Error updating cost item: ' . $e->getMessage())
                ->with('type', 'error');
        }
    }

}
