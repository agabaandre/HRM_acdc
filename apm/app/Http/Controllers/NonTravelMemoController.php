<?php

namespace App\Http\Controllers;

use App\Models\NonTravelMemo;
use App\Models\NonTravelMemoCategory;
use App\Models\Staff;
use App\Models\Location;
use App\Models\FundType;
use App\Models\FundCode;
use App\Models\CostItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class NonTravelMemoController extends Controller
{
    /** List all memos with optional filters */
    public function index(Request $request): View
    {
        // Cache lookup tables for 60 minutes
        $staff  = Cache::remember('non_travel_staff', 60 * 60, fn() => Staff::active()->get());
        $categories = Cache::remember('non_travel_categories', 60 * 60, fn() => NonTravelMemoCategory::all());

        // Base query with eager loads
        $query = NonTravelMemo::with(['staff', 'category']);

        // Apply filters when present
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('category_id')) {
            $query->where('non_travel_memo_category_id', $request->category_id);
        }

        // Paginate and preserve filters in the query string
        $nonTravelMemos = $query->latest()->paginate(10)->withQueryString();

        return view('non-travel.index', compact('nonTravelMemos', 'staff', 'categories'));
    }

   public function create(): View
    {
        ini_set('memory_limit', '1024M');

        // Cache locations for 60 minutes to avoid reloading a large dataset
        $locations = Cache::remember('non_travel_locations', 60 * 60, function () {
            return Location::all();
        });
        $fundTypes = FundType::all();

        return view('non-travel.create', [
            'categories' => NonTravelMemoCategory::all(),
            'staffList'  => Staff::active()->get(),
            'locations'  => $locations,
            'budgets'    => FundCode::all(),
            'fundTypes' => $fundTypes
        ]);
    }

    /** Persist new memo */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'staff_id'                     => 'required|exists:staff,id',
            'date_required'                => 'required|date',
            'location_id'                  => 'required|array|min:1',
            'location_id.*'                => 'exists:locations,id',
            'fund_location'                => 'required|string|max:255',
            'budget_code_id'               => 'required|exists:fund_codes,id',
            'non_travel_memo_category_id'  => 'required|exists:non_travel_memo_categories,id',
            'title'                        => 'required|string|max:255',
            'approval'                     => 'required|string',
            'background'                   => 'required|string',
            'description'                  => 'required|string',
            'other_information'            => 'nullable|string',
            'attachments'                  => 'nullable|array',
            'attachments.*'                => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'items'                        => 'nullable|array',
            'items.*.description'          => 'required_with:items|string',
            'items.*.unit'                 => 'required_with:items|string',
            'items.*.quantity'             => 'required_with:items|numeric|min:1',
            'items.*.unit_price'           => 'required_with:items|numeric|min:0',
        ]);

        // handle attachments
        $files = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $f) {
                $path = $f->store('non-travel/attachments', 'public');
                $files[] = [
                    'name' => $f->getClientOriginalName(),
                    'path' => $path,
                ];
            }
        }
        $data['attachments'] = $files;
        $data['items'] = $request->input('items', []);

        NonTravelMemo::create($data + ['created_by' => Auth::id()]);

        return redirect()->route('non-travel.index')
                         ->with('success', 'Request submitted successfully.');
    }

    /** Show one memo */
    public function show(NonTravelMemo $nonTravel): View
    {
        $nonTravel->load(['staff', 'category']);
        return view('non-travel.show', compact('nonTravel'));
    }

    /** Delete memo and its files */
    public function destroy(NonTravelMemo $nonTravel): RedirectResponse
    {
        foreach ($nonTravel->attachments ?? [] as $att) {
            Storage::disk('public')->delete($att['path']);
        }
        $nonTravel->delete();

        return back()->with('success', 'Request deleted.');
    }
}
