<?php

namespace App\Http\Controllers;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\Staff;
use Illuminate\Http\Request;

class DirectorateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return view('directorates.index', [
            'pageConfig' => [
                'routes' => [
                    'ajax' => route('directorates.ajax'),
                    'show' => url('directorates'),
                    'create' => route('directorates.create'),
                ],
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                ],
            ],
        ]);
    }

    /**
     * Directorates data for AJAX table (search, status filter, sort, pagination).
     */
    public function getDirectoratesAjax(Request $request)
    {
        $search = trim((string) ($request->get('search') ?? ''));
        $status = trim((string) ($request->get('status') ?? ''));
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = max(5, min(100, (int) $request->get('pageSize', 25)));
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSortColumns = ['id', 'name', 'code', 'is_active', 'created_at', 'updated_at'];
        if (! in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'created_at';
        }
        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $baseQuery = Directorate::query();
        $allDirectorates = (clone $baseQuery)->get(['id', 'is_active']);

        $query = Directorate::query()->with('director');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $recordsTotal = $query->count();
        $totalPages = $pageSize > 0 ? (int) ceil($recordsTotal / $pageSize) : 0;
        $skip = ($page - 1) * $pageSize;
        $data = $query->orderBy($sortBy, $sortDirection)->skip($skip)->take($pageSize)->get();

        return response()->json([
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'summary' => [
                'total_directorates' => $allDirectorates->count(),
                'active_directorates' => $allDirectorates->where('is_active', 1)->count(),
                'inactive_directorates' => $allDirectorates->where('is_active', 0)->count(),
                'filtered_directorates' => $recordsTotal,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $staffForDirector = $this->staffForDirectorPicker();

        return view('directorates.create', compact('staffForDirector'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:directorates,name',
            'code' => 'required|string|max:50|unique:directorates,code',
            'description' => 'nullable|string',
            'is_active' => 'nullable',
            'director_id' => 'nullable|integer|exists:staff,staff_id',
        ]);

        $directorate = new Directorate();
        $directorate->name = $request->name;
        $directorate->code = $request->code;
        $directorate->description = $request->description;
        $directorate->is_active = $request->has('is_active') ? 1 : 0;
        $directorate->director_id = $request->filled('director_id') ? (int) $request->input('director_id') : null;
        $directorate->save();

        return redirect()->route('directorates.index')
            ->with('success', 'Directorate created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $directorate = Directorate::with('director')->findOrFail($id);
        $divisions = Division::where('directorate_id', $id)
            ->orderBy('division_name')
            ->get();

        return view('directorates.show', [
            'pageConfig' => $this->buildDirectorateShowPageConfig($directorate, $divisions),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $directorate = Directorate::findOrFail($id);
        $staffForDirector = $this->staffForDirectorPicker();

        return view('directorates.edit', compact('directorate', 'staffForDirector'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $directorate = Directorate::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:directorates,name,' . $id,
            'code' => 'required|string|max:50|unique:directorates,code,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'nullable',
            'director_id' => 'nullable|integer|exists:staff,staff_id',
        ]);

        $directorate->name = $request->name;
        $directorate->code = $request->code;
        $directorate->description = $request->description;
        $directorate->is_active = $request->has('is_active') ? 1 : 0;
        $directorate->director_id = $request->filled('director_id') ? (int) $request->input('director_id') : null;
        $directorate->save();

        return redirect()->route('directorates.index')
            ->with('success', 'Directorate updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $directorate = Directorate::findOrFail($id);
        
        // Check if the directorate has any related divisions
        $divisionsCount = Division::where('directorate_id', $id)->count();
        
        if ($divisionsCount > 0) {
            return redirect()->route('directorates.index')
                ->with('error', 'Cannot delete this directorate because it has related divisions.');
        }
        
        $directorate->delete();
        return redirect()->route('directorates.index')
            ->with('success', 'Directorate deleted successfully.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Staff>
     */
    private function staffForDirectorPicker()
    {
        return Staff::query()
            ->whereNotIn('status', ['Expired', 'Separated'])
            ->orderBy('lname')
            ->orderBy('fname')
            ->get(['staff_id', 'fname', 'lname', 'title']);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Division>  $divisions
     * @return array<string, mixed>
     */
    private function buildDirectorateShowPageConfig(Directorate $directorate, $divisions): array
    {
        $director = $directorate->director;

        return [
            'directorate' => [
                'id' => $directorate->id,
                'name' => $directorate->name,
                'code' => $directorate->code ?? null,
                'description' => $directorate->description ?? null,
                'is_active' => (bool) $directorate->is_active,
                'created_at' => $directorate->created_at?->format('Y-m-d H:i'),
                'updated_at' => $directorate->updated_at?->format('Y-m-d H:i'),
            ],
            'director' => $director ? [
                'staff_id' => $director->staff_id,
                'name' => trim($director->lname.' '.$director->fname),
                'show_url' => route('staff.show', $director->staff_id),
            ] : null,
            'divisions' => $divisions->map(fn (Division $division) => [
                'id' => $division->id,
                'division_name' => $division->division_name,
                'short_name' => $division->division_short_name ?? null,
                'category' => $division->category ?? null,
                'is_active' => $division->is_active ?? null,
                'show_url' => route('divisions.show', $division->id),
            ])->values()->all(),
            'routes' => [
                'index' => route('directorates.index'),
                'edit' => route('directorates.edit', $directorate->id),
            ],
        ];
    }
}
