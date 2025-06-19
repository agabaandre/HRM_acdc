<?php

namespace App\Http\Controllers;

use App\Models\NonTravelMemoCategory;
use Illuminate\Http\Request;

class NonTravelMemoCategoryController extends Controller
{
    public function index()
    {
        $categories = NonTravelMemoCategory::latest()->paginate(10);
        
        return view('non-travel-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('non-travel-categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:non_travel_memo_categories,name',
        ], [
            'name.required' => 'The category name is required.',
            'name.unique' => 'A category with this name already exists.',
        ]);

        NonTravelMemoCategory::create($validated);

        return redirect()
            ->route('non-travel-categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function show(NonTravelMemoCategory $nonTravelCategory)
    {
        return view('non-travel-categories.show', compact('nonTravelCategory'));
    }

    public function edit(NonTravelMemoCategory $nonTravelCategory)
    {
        return view('non-travel-categories.edit', compact('nonTravelCategory'));
    }

    public function update(Request $request, NonTravelMemoCategory $nonTravelCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:non_travel_memo_categories,name,' . $nonTravelCategory->id,
        ], [
            'name.required' => 'The category name is required.',
            'name.unique' => 'A category with this name already exists.',
        ]);

        $nonTravelCategory->update($validated);

        return redirect()
            ->route('non-travel-categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(NonTravelMemoCategory $nonTravelCategory)
    {
        // Check if the category is being used by any memos
        if ($nonTravelCategory->nonTravelMemos()->exists()) {
            return redirect()
                ->route('non-travel-categories.index')
                ->with('error', 'Cannot delete category because it is being used by one or more memos.');
        }

        $nonTravelCategory->delete();

        return redirect()
            ->route('non-travel-categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
