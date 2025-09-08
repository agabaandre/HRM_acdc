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

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Staff::with(['division', 'directorate', 'dutyStation']);

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
        $divisions = Division::where('is_active', 1)->orderBy('division_name')->get();
        $directorates = Directorate::where('is_active', 1)->orderBy('name')->get();
        $dutyStations = DutyStation::where('status', 1)->orderBy('name')->get();

        return view('staff.index', compact('staffMembers', 'divisions', 'directorates', 'dutyStations'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $divisions = Division::where('is_active', 1)->orderBy('division_name')->get();
        $directorates = Directorate::where('is_active', 1)->orderBy('name')->get();
        $dutyStations = DutyStation::where('status', 1)->orderBy('name')->get();
        $supervisors = Staff::active()->orderBy('fname')->get();

        return view('staff.create', compact('divisions', 'directorates', 'dutyStations', 'supervisors'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
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
    public function show(string $id)
    {
        $staff = Staff::with(['division', 'directorate', 'dutyStation', 'supervisor'])->findOrFail($id);
        $activities = Activity::where('staff_id', $id)->latest()->take(5)->get();

        return view('staff.show', compact('staff', 'activities'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $staff = Staff::findOrFail($id);
        $divisions = Division::where('is_active', 1)->orderBy('division_name')->get();
        $directorates = Directorate::where('is_active', 1)->orderBy('name')->get();
        $dutyStations = DutyStation::where('status', 1)->orderBy('name')->get();
        $supervisors = Staff::where('active', 1)->where('id', '!=', $id)->orderBy('fname')->get();

        return view('staff.edit', compact('staff', 'divisions', 'directorates', 'dutyStations', 'supervisors'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $staff = Staff::findOrFail($id);

        $validated = $request->validate([
            'staff_id' => 'required|string|max:20|unique:staff,staff_id,' . $id,
            'fname' => 'required|string|max:100',
            'oname' => 'nullable|string|max:100',
            'lname' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:staff,email,' . $id,
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
    public function destroy(string $id)
    {
        $staff = Staff::findOrFail($id);
        
        // Check if there are related activities or other dependencies
        $activitiesCount = Activity::where('staff_id', $id)->count();
        $subordinatesCount = Staff::where('supervisor_id', $id)->count();
        
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
                $otherDivisionsActivities[] = $activityData;
            }
            
            Log::info('Activity ' . $activity->id . ' - Division: ' . ($activity->matrix && $activity->matrix->division ? $activity->matrix->division->division_name : 'N/A') . ', Days: ' . $days);
        }

        return response()->json([
            'my_division' => $myDivisionActivities,
            'other_divisions' => $otherDivisionsActivities
        ]);
    }
}
