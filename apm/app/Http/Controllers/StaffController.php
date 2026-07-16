<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Division;
use App\Models\Directorate;
use App\Models\DutyStation;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StaffExport;
use App\Support\MemoShowUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Staff::with(['division', 'directorate', 'dutyStation'])
            ->whereNotIn('status', ['Expired', 'Separated']); // Exclude Expired and Separated staff

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('staff_id', 'like', "%{$search}%")
                  ->orWhere('fname', 'like', "%{$search}%")
                  ->orWhere('lname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('tel_1', 'like', "%{$search}%");
            });
        }

        // Division filter
        if ($request->has('division_id') && !empty($request->division_id)) {
            $query->where('division_id', $request->division_id);
        }

        // Directorate filter
        if ($request->has('directorate_id') && !empty($request->directorate_id)) {
            $query->where('directorate_id', $request->directorate_id);
        }

        // Duty Station filter
        if ($request->has('duty_station_id') && !empty($request->duty_station_id)) {
            $query->where('duty_station_id', $request->duty_station_id);
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $status = $request->status === 'active' ? 1 : 0;
            $query->where('active', $status);
        }

        $staffMembers = $query->latest()->paginate(10);
        $divisions = Division::orderBy('division_name')->get();
        $dutyStations = Staff::select('duty_station_name')
            ->whereNotNull('duty_station_name')
            ->where('duty_station_name', '!=', '')
            ->whereNotIn('status', ['Expired', 'Separated']) // Exclude Expired and Separated staff
            ->distinct()
            ->orderBy('duty_station_name')
            ->get();

        return view('staff.index', [
            'staffMembers' => $staffMembers,
            'divisions' => $divisions,
            'dutyStations' => $dutyStations,
            'pageConfig' => [
                'csrf' => csrf_token(),
                'showParticipantGroups' => (bool) user_session('division_id'),
                'showWhatsAppGroups' => in_array(89, user_session('permissions', []), true),
                'routes' => [
                    'ajax' => route('staff.ajax'),
                    'show' => url('staff'),
                    'participantGroups' => route('participant-groups.index'),
                    'whatsappGroups' => route('whatsapp-groups.index'),
                    'exportExcel' => route('staff.export', 'excel'),
                    'exportPdf' => route('staff.export', 'pdf'),
                ],
            ],
        ]);
    }


    /**
     * Export staff data
     */
    public function export($format): RedirectResponse|BinaryFileResponse
    {
        $filters = request()->all();
        
        if ($format === 'excel') {
            return Excel::download(new StaffExport($filters), 'staff_' . date('Y-m-d_H-i-s') . '.xlsx');
        } elseif ($format === 'pdf') {
            return Excel::download(new StaffExport($filters), 'staff_' . date('Y-m-d_H-i-s') . '.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        
        return redirect()->route('staff.index')->with('error', 'Invalid export format');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $divisions = Division::orderBy('division_name')->get();
        $directorates = Directorate::where('is_active', 1)->orderBy('name')->get();
        $dutyStations = DutyStation::where('is_active', 1)->orderBy('name')->get();
        $supervisors = Staff::active()->orderBy('fname')->get();

        return view('staff.create', compact('divisions', 'directorates', 'dutyStations', 'supervisors'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => 'required|string|max:20|unique:staff,staff_id',
            'fname' => 'required|string|max:100',
            'oname' => 'nullable|string|max:100',
            'lname' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:staff,email',
            'tel_1' => 'required|string|max:20',
            'gender' => 'required|string|in:Male,Female',
            'dob' => 'nullable|date',
            'division_id' => 'required|exists:divisions,id',
            'directorate_id' => 'required|exists:directorates,id',
            'duty_station_id' => 'required|exists:duty_stations,id',
            'title' => 'required|string|max:100',
            'designation' => 'nullable|string|max:100',
            'employment_status' => 'required|string|max:50',
            'hire_date' => 'nullable|date',
            'supervisor_id' => 'nullable|exists:staff,id',
            'remarks' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'access_level' => 'nullable|integer|in:1,2,3',
            'active' => 'nullable'
        ]);

        // Handle profile photo upload
        $profilePhotoPath = null;
        if ($request->hasFile('profile_photo')) {
            $profilePhotoPath = $request->file('profile_photo')->store('staff-photos', 'public');
        }

        $staff = new Staff();
        $staff->staff_id = $request->staff_id;
        $staff->fname = $request->fname;
        $staff->oname = $request->oname;
        $staff->lname = $request->lname;
        $staff->email = $request->email;
        $staff->tel_1 = $request->tel_1;
        $staff->gender = $request->gender;
        $staff->dob = $request->dob;
        $staff->division_id = $request->division_id;
        $staff->directorate_id = $request->directorate_id;
        $staff->duty_station_id = $request->duty_station_id;
        $staff->title = $request->title;
        $staff->designation = $request->designation;
        $staff->employment_status = $request->employment_status;
        $staff->hire_date = $request->hire_date;
        $staff->supervisor_id = $request->supervisor_id;
        $staff->remarks = $request->remarks;
        $staff->profile_photo = $profilePhotoPath;
        $staff->access_level = $request->access_level;
        $staff->active = $request->has('active') ? 1 : 0;
        $staff->save();

        return redirect()->route('staff.index')
            ->with('success', 'Staff member created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $staff)
    {
        $staffId = (int) $staff;
        $staff = Staff::with(['division', 'directorate', 'dutyStation', 'supervisor'])
            ->findOrFail($staffId);

        $activities = Activity::query()
            ->where(function ($query) use ($staffId) {
                $query->where('staff_id', $staffId)
                    ->orWhere('responsible_person_id', $staffId);
            })
            ->latest()
            ->take(5)
            ->get();

        return view('staff.show', [
            'pageConfig' => $this->buildStaffShowPageConfig($staff, $activities),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $staff)
    {
        $staffId = $staff;
        $staff = Staff::findOrFail($staffId);
        $divisions = Division::orderBy('division_name')->get();
        $directorates = Directorate::where('is_active', 1)->orderBy('name')->get();
        $dutyStations = DutyStation::where('is_active', 1)->orderBy('name')->get();
        // Exclude Expired and Separated staff from supervisor dropdown
        $supervisors = Staff::where('active', 1)
            ->where('id', '!=', $staffId)
            ->whereNotIn('status', ['Expired', 'Separated'])
            ->orderBy('fname')->get();

        return view('staff.edit', compact('staff', 'divisions', 'directorates', 'dutyStations', 'supervisors'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $staff): RedirectResponse
    {
        $staffId = $staff;
        $staff = Staff::findOrFail($staffId);

        $validated = $request->validate([
            'staff_id' => 'required|string|max:20|unique:staff,staff_id,'.$staffId,
            'fname' => 'required|string|max:100',
            'oname' => 'nullable|string|max:100',
            'lname' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:staff,email,'.$staffId,
            'tel_1' => 'required|string|max:20',
            'gender' => 'required|string|in:Male,Female',
            'dob' => 'nullable|date',
            'division_id' => 'required|exists:divisions,id',
            'directorate_id' => 'required|exists:directorates,id',
            'duty_station_id' => 'required|exists:duty_stations,id',
            'title' => 'required|string|max:100',
            'designation' => 'nullable|string|max:100',
            'employment_status' => 'required|string|max:50',
            'hire_date' => 'nullable|date',
            'supervisor_id' => 'nullable|exists:staff,id',
            'remarks' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'access_level' => 'nullable|integer|in:1,2,3',
            'active' => 'nullable'
        ]);

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete the old photo if it exists
            if ($staff->profile_photo) {
                Storage::disk('public')->delete($staff->profile_photo);
            }
            $profilePhotoPath = $request->file('profile_photo')->store('staff-photos', 'public');
            $staff->profile_photo = $profilePhotoPath;
        }

        $staff->staff_id = $request->staff_id;
        $staff->fname = $request->fname;
        $staff->oname = $request->oname;
        $staff->lname = $request->lname;
        $staff->email = $request->email;
        $staff->tel_1 = $request->tel_1;
        $staff->gender = $request->gender;
        $staff->dob = $request->dob;
        $staff->division_id = $request->division_id;
        $staff->directorate_id = $request->directorate_id;
        $staff->duty_station_id = $request->duty_station_id;
        $staff->title = $request->title;
        $staff->designation = $request->designation;
        $staff->employment_status = $request->employment_status;
        $staff->hire_date = $request->hire_date;
        $staff->supervisor_id = $request->supervisor_id;
        $staff->remarks = $request->remarks;
        $staff->access_level = $request->access_level;
        $staff->active = $request->has('active') ? 1 : 0;
        $staff->save();

        return redirect()->route('staff.index')
            ->with('success', 'Staff member updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $staff): RedirectResponse
    {
        $staffId = $staff;
        $staff = Staff::findOrFail($staffId);
        
        // Check if there are related activities or other dependencies
        $activitiesCount = Activity::where('staff_id', $staffId)->count();
        $subordinatesCount = Staff::where('supervisor_id', $staffId)->count();
        
        if ($activitiesCount > 0 || $subordinatesCount > 0) {
            return redirect()->route('staff.index')
                ->with('error', 'Cannot delete this staff record because it has related activities or subordinates.');
        }
        
        // Delete profile photo if it exists
        if ($staff->profile_photo) {
            Storage::disk('public')->delete($staff->profile_photo);
        }
        
        $staff->delete();
        return redirect()->route('staff.index')
            ->with('success', 'Staff record deleted successfully.');
    }

    /**
     * Get staff data for AJAX table (similar to division staff)
     */
    public function getStaffAjax(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $page = (int) $request->get('page', 1);
            $pageSize = (int) $request->get('pageSize', 25);
            
            // Calculate pagination
            $skip = ($page - 1) * $pageSize;
            
            // Get all staff first for summary statistics (exclude Expired and Separated)
            $allStaff = Staff::with(['division', 'dutyStation'])
                ->where('active', 1)
                ->whereNotIn('status', ['Expired', 'Separated'])
                ->get();
            
            // Build query for filtered staff (exclude Expired and Separated)
            $query = Staff::with(['division', 'dutyStation'])
                ->where('active', 1)
                ->whereNotIn('status', ['Expired', 'Separated']);
            
            // Apply search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('fname', 'like', "%{$search}%")
                      ->orWhere('lname', 'like', "%{$search}%")
                      ->orWhere('oname', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%")
                      ->orWhere('work_email', 'like', "%{$search}%")
                      ->orWhere('job_name', 'like', "%{$search}%")
                      ->orWhere('duty_station_name', 'like', "%{$search}%")
                      ->orWhereHas('division', function($q) use ($search) {
                          $q->where('division_name', 'like', "%{$search}%");
                      });
                });
            }
            
            // Get total count
            $totalRecords = $query->count();
            $totalPages = ceil($totalRecords / $pageSize);
            
            // Get paginated data
            $staffData = $query->skip($skip)->take($pageSize)->get();
            
            // Calculate summary statistics
            $summary = [
                'total_staff' => $allStaff->count(),
                'filtered_staff' => $totalRecords,
                'active_staff' => $allStaff->where('active', 1)->count(),
                'inactive_staff' => $allStaff->where('active', 0)->count()
            ];
            
            return response()->json([
                'data' => $staffData,
                'recordsTotal' => $totalRecords,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'summary' => $summary
            ]);
            
        } catch (\Exception $e) {
            Log::error('Staff AJAX error: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while loading staff data'], 500);
        }
    }

    /**
     * Get activities for a specific staff member in a matrix
     */
    public function getActivities(Request $request, $staffId)
    {
        Log::info('getActivities called with staffId: ' . $staffId);
        Log::info('Request data: ' . json_encode($request->all()));
        
        $matrixId = $request->query('matrix_id');
        
        if (!$matrixId) {
            return response()->json(['error' => 'Matrix ID is required'], 400);
        }

        // Get the staff member
        $staff = Staff::findOrFail($staffId);
        
        // Get the matrix to get quarter and year
        $matrix = \App\Models\Matrix::find($matrixId);
        $quarter_year = $matrix->quarter . "-" . $matrix->year;
        
        // Get activities where this staff is a participant
        $activities = Activity::with(['matrix', 'focalPerson', 'matrix.division', 'participantSchedules'])
            ->where('matrix_id', $matrixId)
            ->whereHas('participantSchedules', function($query) use ($staffId) {
                $query->where('participant_id', $staffId);
            })
            ->get();

        Log::info('Activities found: ' . $activities->count());
        Log::info('First activity division: ' . ($activities->first() ? json_encode($activities->first()->division) : 'No activities'));

        // Separate activities by division
        $myDivisionActivities = [];
        $otherDivisionsActivities = [];

        foreach ($activities as $activity) {
            // Calculate days based on staff's division_days and other_days arrays
            $division_days = isset($staff->division_days[$quarter_year]) ? $staff->division_days[$quarter_year] : 0;
            $other_days = isset($staff->other_days[$quarter_year]) ? $staff->other_days[$quarter_year] : 0;
            $total_days = $division_days + $other_days;
            
            // For now, we'll show total days. You can modify this logic based on your needs
            $days = $total_days;
            
            $activityData = [
                'activity_title' => $activity->activity_title,
                'focal_person' => $activity->focalPerson ? $activity->focalPerson->fname . ' ' . $activity->focalPerson->lname : 'N/A',
                'division_name' => $activity->matrix && $activity->matrix->division ? $activity->matrix->division->division_name : 'N/A',
                'days' => $days
            ];

            Log::info('Activity data: ' . json_encode($activityData));

            // Check if activity is in staff's division
            if ($activity->matrix && $activity->matrix->division_id == $staff->division_id) {
                $myDivisionActivities[] = $activityData;
            } else {
                // Other divisions: only include activities that are pending or approved (exclude draft, rejected)
                $status = $activity->overall_status ?? '';
                if (in_array($status, ['pending', 'approved'])) {
                    $otherDivisionsActivities[] = $activityData;
                }
            }
            
            Log::info('Activity ' . $activity->id . ' - Division: ' . ($activity->matrix && $activity->matrix->division ? $activity->matrix->division->division_name : 'N/A') . ', Days: ' . $days);
        }

        return response()->json([
            'my_division' => $myDivisionActivities,
            'other_divisions' => $otherDivisionsActivities
        ]);
    }

    private function buildStaffShowPageConfig(Staff $staff, $activities): array
    {
        $fullName = trim(collect([$staff->fname, $staff->oname, $staff->lname])->filter()->implode(' '));
        $supervisor = $staff->supervisor;
        $supervisorName = $supervisor
            ? trim(collect([$supervisor->title, $supervisor->fname, $supervisor->lname])->filter()->implode(' '))
            : null;

        return [
            'staff' => [
                'staff_id' => $staff->staff_id,
                'full_name' => $fullName,
                'display_name' => $staff->title ? trim($staff->title . ' ' . $fullName) : $fullName,
                'title' => $staff->title,
                'fname' => $staff->fname,
                'lname' => $staff->lname,
                'oname' => $staff->oname,
                'initials' => strtoupper(substr((string) $staff->fname, 0, 1) . substr((string) $staff->lname, 0, 1)),
                'work_email' => $staff->work_email,
                'private_email' => $staff->private_email,
                'tel_1' => $staff->tel_1,
                'whatsapp' => $staff->whatsapp,
                'gender' => $staff->gender,
                'division' => $staff->division?->division_name ?? $staff->division_name,
                'directorate' => $staff->directorate?->name,
                'duty_station' => $staff->dutyStation?->name ?? $staff->duty_station_name,
                'job_name' => $staff->job_name,
                'grade' => $staff->grade,
                'contract_type' => $staff->contract_type,
                'contracting_institution' => $staff->contracting_institution,
                'nationality' => $staff->nationality,
                'status' => $staff->status,
                'sap_no' => $staff->sap_no,
                'physical_location' => $staff->physical_location,
                'active' => (bool) $staff->active,
                'supervisor_name' => $supervisorName,
                'supervisor_url' => $supervisor ? route('staff.show', $supervisor->staff_id) : null,
                'photo_url' => $this->staffPhotoUrl($staff),
                'updated_at' => $staff->updated_at?->format('M d, Y g:i A'),
            ],
            'activities' => $activities->map(function (Activity $activity) {
                return [
                    'id' => $activity->id,
                    'title' => $activity->activity_title ?: ('Activity #' . $activity->id),
                    'status' => $activity->overall_status ?? 'unknown',
                    'date' => $activity->created_at?->format('M d, Y'),
                    'show_url' => MemoShowUrl::activityShowUrl((int) $activity->id),
                ];
            })->values()->all(),
            'routes' => [
                'index' => route('staff.index'),
            ],
        ];
    }

    private function staffPhotoUrl(Staff $staff): ?string
    {
        $photo = $staff->photo ?? $staff->profile_photo ?? null;
        if ($photo === null || $photo === '') {
            return null;
        }

        if (str_starts_with((string) $photo, 'staff-photos/')) {
            return asset('storage/' . $photo);
        }

        $basename = basename(str_replace('\\', '/', trim((string) $photo)));
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return null;
        }

        return route('staff-uploads.photo', ['f' => $basename]);
    }
}
