<?php

namespace App\Http\Controllers;

use App\Models\FaqCategory;
use Illuminate\Http\Request;

class FaqCategoryController extends Controller
{
    public function index()
    {
        $categories = FaqCategory::withCount('faqs')->ordered()->get();
        return view('faq-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('faq-categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:faq_categories,slug',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? (FaqCategory::max('sort_order') + 1);
        $validated['is_active'] = $request->boolean('is_active');

        FaqCategory::create($validated);

        return redirect()->route('faq-categories.index')
            ->with('msg', 'FAQ category created successfully.')
            ->with('type', 'success');
    }

    public function edit(FaqCategory $faq_category)
    {
        return view('faq-categories.edit', ['category' => $faq_category]);
    }

    public function update(Request $request, FaqCategory $faq_category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:faq_categories,slug,' . $faq_category->id,
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active');

        $faq_category->update($validated);

        return redirect()->route('faq-categories.index')
            ->with('msg', 'FAQ category updated successfully.')
            ->with('type', 'success');
    }

    public function destroy(FaqCategory $faq_category)
    {
        if ($faq_category->faqs()->exists()) {
            return redirect()->route('faq-categories.index')
                ->with('msg', 'Cannot delete category that has FAQs. Move or delete the FAQs first.')
                ->with('type', 'danger');
        }
        $faq_category->delete();
        return redirect()->route('faq-categories.index')
            ->with('msg', 'FAQ category deleted.')
            ->with('type', 'success');
    }
}
