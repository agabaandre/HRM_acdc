<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Display the public FAQ page (no login required).
     */
    public function publicPage()
    {
        $faqs = Faq::active()->ordered()->get();
        return view('help.faq', compact('faqs'));
    }

    /**
     * Display a listing of FAQs for management (admin).
     */
    public function index(Request $request)
    {
        $query = Faq::query()->ordered();

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qry) use ($q) {
                $qry->where('question', 'like', "%{$q}%")
                    ->orWhere('answer', 'like', "%{$q}%")
                    ->orWhere('search_keywords', 'like', "%{$q}%");
            });
        }

        if ($request->filled('active')) {
            if ($request->active === '1') {
                $query->where('is_active', true);
            } elseif ($request->active === '0') {
                $query->where('is_active', false);
            }
        }

        $faqs = $query->paginate(15)->withQueryString();
        return view('faqs.index', compact('faqs'));
    }

    /**
     * Show the form for creating a new FAQ.
     */
    public function create()
    {
        return view('faqs.create');
    }

    /**
     * Store a newly created FAQ in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
            'search_keywords' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? (Faq::max('sort_order') + 1);
        $validated['is_active'] = $request->boolean('is_active');

        Faq::create($validated);

        return redirect()->route('faqs.index')
            ->with('msg', 'FAQ created successfully.')
            ->with('type', 'success');
    }

    /**
     * Show the form for editing the specified FAQ.
     */
    public function edit(Faq $faq)
    {
        return view('faqs.edit', compact('faq'));
    }

    /**
     * Update the specified FAQ in storage.
     */
    public function update(Request $request, Faq $faq)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
            'search_keywords' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $faq->update($validated);

        return redirect()->route('faqs.index')
            ->with('msg', 'FAQ updated successfully.')
            ->with('type', 'success');
    }

    /**
     * Remove the specified FAQ from storage.
     */
    public function destroy(Faq $faq)
    {
        $faq->delete();
        return redirect()->route('faqs.index')
            ->with('msg', 'FAQ deleted successfully.')
            ->with('type', 'success');
    }
}
