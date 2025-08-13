<?php

namespace App\Http\Controllers;

use App\Models\Approver;
use App\Models\Division;
use App\Models\ApprovalTrail;
use App\Models\WorkflowDefinition;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\Matrix;
use App\Models\Location;
use App\Models\Staff;
use App\Models\FundCode;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\View as ViewFacade;

class MatrixController extends Controller
{
    /**
     * Display a listing of matrices.
     */
    public function index(Request $request): View
    {
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow',
            'activities' => function ($q) {
                $q->select('id', 'matrix_id', 'activity_title', 'total_participants', 'budget')
                  ->whereNotNull('matrix_id');
            }
        ]);


        // Replace the complex whereHas query with proper division-specific and workflow logic
        $userDivisionId = user_session('division_id');
        $userStaffId    = user_session('staff_id');
        
        $query->where(function($q) use ($userDivisionId, $userStaffId) {
            // Case 1: Division-specific approval - check if user's division matches matrix division
            if ($userDivisionId) {
                $q->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \DB::raw('matrices.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column - check if user's staff_id matches the value in the division_reference_column
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId, $userDivisionId) {

                    $divisionsTable = (new Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = matrices.division_id 
                        WHERE wd.workflow_id = matrices.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = matrices.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=matrices.division_id AND d.id=?)
                        )
                    )", [$userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userDivisionId])
                    ->orWhere(function($subQ2) use ($userStaffId) {
                        $subQ2->where('approval_level', $userStaffId)
                              ->orWhereHas('approvalTrails', function($trailQ) use ($userStaffId) {
                                $trailQ->where('staff_id', $userStaffId);
                              });
                    });
                });
            }
            
            // Case 2: Non-division-specific approval - check workflow definition and approver
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($userStaffId) {
                        $workflowQ->where('is_division_specific', 0)
                                  ->where('approval_order', \DB::raw('matrices.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($userStaffId) {
                                      $approverQ->where('staff_id', $userStaffId);
                                  });
                    });
                });
            }
        });

       
        
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
    
        if ($request->filled('quarter')) {
            $query->where('quarter', $request->quarter);
        }
    
        if ($request->filled('focal_person')) {
            $query->where('focal_person_id', $request->focal_person);
        }
    
        if ($request->filled('division')) {
            $query->where('id', $request->division);
        }
    
        $matrices = $query->latest()->paginate(10);

        //dd($matrices);
    
        $matrices->getCollection()->transform(function ($matrix) {
            $matrix->total_activities = $matrix->activities->count();
            $matrix->total_participants = $matrix->activities->sum('total_participants');
            $matrix->total_budget = $matrix->activities->sum(function ($activity) {
                return is_array($activity->budget) && isset($activity->budget['total'])
                    ? $activity->budget['total']
                    : 0;
            });
            return $matrix;
        });

        // Separate matrices into actionable and actioned lists
        $actionableMatrices = $matrices->getCollection()->filter(function ($matrix) {
            return in_array($matrix->overall_status, ['draft', 'pending', 'returned']);
        });

        $actionedMatrices = $matrices->getCollection()->filter(function ($matrix) {
            return !in_array($matrix->overall_status, ['draft', 'pending', 'returned']);
        });
    
        return view('matrices.index', [
            'matrices' => $matrices,
            'actionableMatrices' => $actionableMatrices,
            'actionedMatrices' => $actionedMatrices,
            'title' => user_session('division_name'),
            'module' => 'Quarterly Matrix',
            'divisions' => \App\Models\Division::all(),
            'focalPersons' => \App\Models\Staff::active()->get(),
        ]);
    }
    
    

    /**
     * Show the form for creating a new matrix.
     */
    public function create(): View
    {
        $divisions = Division::all();
        $staff = Staff::active()->get();
        $focalPersons = $staff;
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $years = range(date('Y'), date('Y') + 5);
    
        $staffByDivision = [];
        $divisionFocalPersons = [];
    
        foreach ($divisions as $division) {
            $divisionStaff = Staff::active()->where('division_id', $division->id)->get();
            $staffByDivision[$division->id] = $divisionStaff->pluck('id')->toArray();
            $divisionFocalPersons[$division->id] = $division->focal_person;
        }
    
        // Save division name in session for breadcrumb use
        session()->put('division_name', user_session('division_name'));
    
        return view('matrices.create', [
            'divisions' => $divisions,
            'title' => user_session('division_name'),
            'module' => 'Quarterly Matrix',
            'staff' => $staff,
            'quarters' => $quarters,
            'years' => $years,
            'focalPersons' => $focalPersons,
            'staffByDivision' => $staffByDivision,
            'divisionFocalPersons' => $divisionFocalPersons,
        ]);
    }
    public function store(Request $request)
{
    $isAdmin = session('user.user_role') == 10;
    $userDivisionId = session('user.division_id');
    $userStaffId = session('user.auth_staff_id');

    // Validate form input
    $validated = $request->validate([
        'year' => 'required|integer',
        'quarter' => 'required|in:Q1,Q2,Q3,Q4',
        'key_result_area.*.description' => 'required|string',
    ]);

    // Restrict input for non-admins
    if (! $isAdmin) {
        $validated['division_id'] = $userDivisionId;
        $validated['focal_person_id'] = $userStaffId;
    }

    // Store the matrix
   $matrix = Matrix::create([
        'division_id' => $validated['division_id'],
        'focal_person_id' => $validated['focal_person_id'],
        'year' => $validated['year'],
        'quarter' => $validated['quarter'],
        'key_result_area' => json_encode($validated['key_result_area']),
        'staff_id' => user_session('staff_id'),
        'forward_workflow_id' => null, // You had this twice. Only one is needed.
        'overall_status'=>'draft'
    ]);

    return redirect()->route('matrices.index')
                     ->with([
                         'msg' => 'Matrix created successfully.',
                         'type' => 'success'
                     ]);
}
    

    /**
     * Display the specified matrix.
     */


     public function show(Matrix $matrix): View
     {
         // Load primary relationships
         $matrix->load(['division', 'staff','participant_schedules','participant_schedules.staff']);
     
         // Paginate related activities and eager load direct relationships
         $activities = $matrix->activities()->with(['requestType', 'fundType'])->latest()->paginate(10);
     
         // Prepare additional decoded & related data per activity
         foreach ($activities as $activity) {
             // Decode JSON arrays
             $locationIds = is_array($activity->location_id)
                 ? $activity->location_id
                 : json_decode($activity->location_id ?? '[]', true);
     
             $internalRaw = is_string($activity->internal_participants)
                 ? json_decode($activity->internal_participants ?? '[]', true)
                 : ($activity->internal_participants ?? []);
     
             $internalParticipantIds = collect($internalRaw)->pluck('staff_id')->toArray();
     
             // Attach related models
             $activity->locations = Location::whereIn('id', $locationIds ?: [])->get();
             $activity->internalParticipants = Staff::whereIn('staff_id', $internalParticipantIds ?: [])->get();
         }
         //dd($matrix);
     
         return view('matrices.show', compact('matrix', 'activities'));
     }
    
    

    /**
     * Show the form for editing the specified matrix.
     */
    public function edit(Matrix $matrix): View
    {
        $divisions = Division::all();
        $staff = Staff::active()->get();
        $focalPersons = Staff::active()->get();
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $years = range(date('Y'), date('Y') + 5);
    
        // Prepare staff and focal person mapping per division
        $staffByDivision = [];
        $divisionFocalPersons = [];
    
        foreach ($divisions as $division) {
            $divisionStaff = $staff->where('division_id', $division->id);
            $staffByDivision[$division->id] = $divisionStaff->pluck('id')->toArray();
            $divisionFocalPersons[$division->id] = $division->focal_person;
        }
    
        // Ensure key_result_area is an array
        if (is_string($matrix->key_result_area)) {
            $decoded = json_decode($matrix->key_result_area, true);
            $matrix->key_result_area = is_array($decoded) ? $decoded : [];
        }
    
        return view('matrices.edit', compact(
            'matrix',
            'divisions',
            'staff',
            'quarters',
            'years',
            'focalPersons',
            'staffByDivision',
            'divisionFocalPersons'
        ));
    }
    
    

    /**
     * Update the specified matrix.
     */
    public function update(Request $request, Matrix $matrix): RedirectResponse
    {
        $isAdmin = session('user.user_role') == 10;
        $userDivisionId = session('user.division_id');
        $userStaffId = session('user.auth_staff_id');
    
        // Validate basic fields
        $validated = $request->validate([
            'year' => 'required|integer',
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'key_result_area' => 'required|array',
            'key_result_area.*.description' => 'required|string',
        ]);
    
        // For admins, allow editing focal person and division
        if ($isAdmin) {
            $validated += $request->validate([
                'division_id' => 'required|exists:divisions,id',
                'focal_person_id' => 'required|exists:staff,staff_id',
            ]);
        } else {
            $validated['division_id'] = $userDivisionId;
            $validated['focal_person_id'] = $userStaffId;
        }

        $this->updateMatrix($matrix,$request,$validated);

        return redirect()->route('matrices.index')->with([
            'msg' => 'Matrix updated successfully.',
            'type' => 'success'
        ]);
        
    }

    public function updateMatrix($matrix,$request,$validated=null){

        $last_workflow_id=null;
        $last_approval_order=$matrix->approval_level;
        $overall_status = $matrix->overall_status;

        $last_approval_trail = ApprovalTrail::where('model_id',$matrix->id)->where('model_type', Matrix::class)->whereNotIn('action',['approved','submitted'])->orderByDesc('id')->first();

        if($request->action == 'approvals'){

            if($last_approval_trail){
                $workflow_defn       = WorkflowDefinition::where('approval_order', $last_approval_trail->approval_order)->first();
                $last_workflow_id    = $workflow_defn->workflow_id;
                $last_approval_order = $last_approval_trail->approval_order;
            }
            else
                $last_approval_order=1;

            $overall_status = 'pending';
            $this->saveMatrixTrail($matrix,'Submitted for approval','submitted');
        }

        $update_data = [
            'staff_id'            => $matrix->staff_id ?? user_session('staff_id'),
            'forward_workflow_id' => ($request->action == 'approvals' && $last_workflow_id==null)?1:$last_workflow_id,
            'approval_level' => $last_approval_order ?? 1,
            'overall_status' => $overall_status
        ];

        if($validated){
            $update_data['division_id'] = $validated['division_id'];
            $update_data['focal_person_id'] = $validated['focal_person_id'];
            $update_data['year']    = $validated['year'];
            $update_data['quarter'] = $validated['quarter'];
            $update_data['key_result_area'] = json_encode($validated['key_result_area']);
        }

        // Update matrix
        $matrix->update($update_data);
        send_matrix_email_notification($matrix, 'approval');
    }

    public function request_approval( Matrix $matrix){

        $this->updateMatrix($matrix,(Object)['action'=>'approvals'],null);
        //notify and save notification
        send_matrix_email_notification($matrix, 'approval');
        
        return redirect()->route('matrices.index')->with([
            'msg' => 'Matrix updated successfully.',
            'type' => 'success'
        ]);
    }
    
    
    /**
     * Remove the specified matrix.
     */
    public function destroy(Matrix $matrix): RedirectResponse
    {
        $matrix->delete();

        return redirect()
            ->route('matrices.index')
            ->with('success', 'Matrix deleted successfully.');
    }

    public function update_status(Request $request, Matrix $matrix): RedirectResponse
    {
        $request->validate(['action' => 'required']);
        $this->saveMatrixTrail($matrix,$request->comment  ?? ($request->action=='approved')?'approved':'',$request->action);
        
        $notification_type =null;

        if($request->action !=='approved'){

            $matrix->forward_workflow_id = (intval($matrix->approval_level)==1)?null:1;
            $matrix->approval_level = ($matrix->approval_level==1)?0:1;
            $matrix->overall_status ='returned';
            //notify and save notification
            $notification_type = 'returned';
        }else{
            //move to next
            $next_approval_point = $this->get_next_approver($matrix);
           
           if($next_approval_point){

            $matrix->forward_workflow_id = $next_approval_point->workflow_id;
            $matrix->approval_level = $next_approval_point->approval_order;
            $matrix->next_approval_level = $next_approval_point->approval_order;
            $matrix->overall_status = 'pending';

            //notify and save notification
            $notification_type = 'approval';
           }
           else{
            //no more approval levels
            $matrix->overall_status = 'approved';
            $notification_type = 'approved';
           }
        }
        
        $matrix->update();

        //notify and save notification
        send_matrix_email_notification($matrix, $notification_type);
        $message = "Matrix Updated successfully";

        return redirect()
        ->route('matrices.show', [$matrix])
        ->with('success', $message);

    }

    private function saveMatrixTrail($matrix,$comment,$action){

        $matrixTrail = new ApprovalTrail();
        $matrixTrail->remarks  = $comment;
        $matrixTrail->action   = $action;
        $matrixTrail->model_id   = $matrix->id;
        $matrixTrail->model_type = Matrix::class;
        $matrixTrail->matrix_id   = $matrix->id; // For backward compatibility
        $matrixTrail->approval_order   = $matrix->approval_level ?? 1;
        $matrixTrail->staff_id = user_session('staff_id');
        $matrixTrail->save();

        mark_matrix_notifications_read(user_session('staff_id'), $matrix->id);
    }

    private function get_next_approver($matrix){

        $division   = $matrix->division;

        $current_definition = WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
           ->where('is_enabled',1)
           ->where('approval_order',$matrix->approval_level)
           ->first();

        $go_to_category_check_for_external =(!$matrix->has_extramural && !$matrix->has_extramural && ($matrix->approval_level!=null && $current_definition->approval_order > $matrix->approval_level));

        //if it's time to trigger categroy check, just check and continue
        if(($current_definition && $current_definition->triggers_category_check) || $go_to_category_check_for_external){

            $category_definition = WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
                        ->where('is_enabled',1)
                        ->where('category',$division->category)
                        ->orderBy('approval_order','asc')
                        ->first();

            return $category_definition;
        }

        $nextStepIncrement = 1;

        //Skip Directorate from HOD if no directorate
        if($matrix->forward_workflow_id>0 && $current_definition->approval_order==1 && !$division->director_id)
            $nextStepIncrement = 2;

         if(!$matrix->forward_workflow_id)// null
            $matrix->forward_workflow_id = 1;
   
        $next_definition = WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
           ->where('is_enabled',1)
           ->where('approval_order',$matrix->approval_level +$nextStepIncrement)->get();
            
        //if matrix has_extramural is true and matrix->approval_level !==definition_approval_order, 
        // get from $definition where fund_type=2, else where fund_type=2
        //if one, just return the one available
        if ($next_definition->count() > 1) {

            if ($matrix->has_extramural && $matrix->approval_level !== $next_definition->first()->approval_order) {
                return $next_definition->where('fund_type', 2);
            } 
            else {
                return $next_definition->where('fund_type', 1);
            }
        }

        $definition = ($next_definition->count()>0)?$next_definition[0]:null;
        //intramural only, skip extra mural role
        if($definition  && !$matrix->has_extramural &&  $definition->fund_type==2){
          return WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
            ->where('is_enabled',1)
            ->where('approval_order',$definition->approval_order+1)->first();
        }

        //only extramural, skip by intramural roles
        if($definition  && !$matrix->has_intramural &&  $definition->fund_type==1){
            return WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
              ->where('is_enabled',1)
              ->where('approval_order', $definition->approval_order+2)->first();
        }

       
        return  $definition;

    }
}