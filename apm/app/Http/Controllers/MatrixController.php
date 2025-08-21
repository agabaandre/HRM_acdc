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
use Illuminate\Support\Facades\Redirect;
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
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'));
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
                                $trailQ->where('staff_id', '=',$userStaffId);
                              });
                    });
                });
            }
            
            // Case 2: Non-division-specific approval - check workflow definition and approver
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($userStaffId) {
                        $workflowQ->where('is_division_specific','=', 0)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($userStaffId) {
                                      $approverQ->where('staff_id', $userStaffId);
                                  });
                    });
                });
            }

            $q->orWhere('division_id', $userDivisionId);
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

       //  dd(getFullSql($query));

        $matrices = $query->latest()->paginate(20);

        //dd($matrices->toArray());

        
       
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
        $myDivisionMatrices = $matrices->getCollection()->filter(function ($matrix) {
            return $matrix->division_id == user_session('division_id');
        });


        // Filter matrices based on CustomHelper functions for accurate counts
        $filteredActionableMatrices = $actionableMatrices->filter(function ($matrix) {
             //dd(can_take_action($matrix));
            return can_take_action($matrix)  || still_with_creator($matrix);
        });

        $filteredActionedMatrices = $matrices->filter(function ($matrix) {
            return done_approving($matrix) ;
        });

        // Get all matrices for users with permission ID 87
        $allMatrices = collect();
        if (in_array(87, user_session('permissions', []))) {
            $allMatrices = Matrix::with([
                'division',
                'staff',
                'focalPerson',
                'forwardWorkflow',
                'activities' => function ($q) {
                    $q->select('id', 'matrix_id', 'activity_title', 'total_participants', 'budget')
                      ->whereNotNull('matrix_id');
                }
            ])->latest()->paginate(20);
        }

      //  dd($filteredActionedMatrices->toArray());

    
        return view('matrices.index', [
            'matrices' => $matrices,
            'actionableMatrices' => $actionableMatrices,
            'actionedMatrices' => $filteredActionedMatrices,
            'filteredActionableMatrices' => $filteredActionableMatrices,
            'filteredActionedMatrices' => $filteredActionedMatrices,
            'myDivisionMatrices' => $myDivisionMatrices,
            'allMatrices' => $allMatrices,
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
        $existingMatrices = [];
        $nextAvailableQuarters = [];
    
        foreach ($divisions as $division) {
            $divisionStaff = Staff::active()->where('division_id', $division->id)->get();
            $staffByDivision[$division->id] = $divisionStaff->pluck('id')->toArray();
            $divisionFocalPersons[$division->id] = $division->focal_person;
            
            // Get existing matrices for this division
            $existingMatrices[$division->id] = Matrix::getExistingMatricesForDivision($division->id);
            
            // Get next available quarter for current year
            $nextAvailableQuarters[$division->id] = Matrix::getNextAvailableQuarter($division->id, date('Y'));
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
            'existingMatrices' => $existingMatrices,
            'nextAvailableQuarters' => $nextAvailableQuarters,
        ]);
    }
    public function store(Request $request)
    {
        $isAdmin = session('user.user_role') == 10;
        $userDivisionId = session('user.division_id');
        $userStaffId = session('user.auth_staff_id');

        // Validate form input
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'key_result_area.*.description' => 'required|string',
        ]);

        // Restrict input for non-admins
        if (! $isAdmin) {
            $validated['division_id'] = $userDivisionId;
            $validated['focal_person_id'] = $userStaffId;
        } else {
            // For admins, validate division_id and focal_person_id
            $validated['division_id'] = $request->input('division_id');
            $validated['focal_person_id'] = $request->input('focal_person_id');
        }

        // Check if a matrix already exists for this division, year, and quarter
        if (Matrix::existsForDivisionYearQuarter($validated['division_id'], $validated['year'], $validated['quarter'])) {
            return Redirect::back()
                ->withInput()
                ->withErrors([
                    'quarter' => 'A matrix already exists for ' . $validated['division_id'] . ' in ' . $validated['year'] . ' ' . $validated['quarter'] . '. Only one matrix per division per quarter is allowed.'
                ]);
        }

        // Store the matrix
        $matrix = Matrix::create([
            'division_id' => $validated['division_id'],
            'focal_person_id' => $validated['focal_person_id'],
            'year' => $validated['year'],
            'quarter' => $validated['quarter'],
            'key_result_area' => json_encode($validated['key_result_area']),
            'staff_id' => user_session('staff_id'),
            'forward_workflow_id' => null,
            'overall_status' => 'draft'
        ]);

        return Redirect::route('matrices.index')
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
            'year' => 'required|integer|min:2020|max:2030',
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

        // Check if a matrix already exists for this division, year, and quarter (excluding current matrix)
        if (Matrix::existsForDivisionYearQuarter($validated['division_id'], $validated['year'], $validated['quarter'], $matrix->id)) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'quarter' => 'A matrix already exists for this division in ' . $validated['year'] . ' ' . $validated['quarter'] . '. Only one matrix per division per quarter is allowed.'
                ]);
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

    /**
     * Display pending approvals for the current user.
     */
    public function pendingApprovals(Request $request): View
    {
        $userStaffId = user_session('staff_id');

        // Check if we have valid session data
        if (!$userStaffId) {
            return view('matrices.pending-approvals', [
                'pendingMatrices' => collect(),
                'approvedByMe' => collect(),
                'divisions' => collect(),
                'focalPersons' => collect(),
                'error' => 'No session data found. Please log in again.'
            ]);
        }

        // Copy the working logic from index method for pending approvals
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

        // Only show pending matrices (not draft)
        $query->where('overall_status', 'pending')
              ->where('forward_workflow_id', '!=', null)
              ->where('approval_level', '>', 0);

        // Apply the same filtering logic as index method
        $userDivisionId = user_session('division_id');
        
        $query->where(function($q) use ($userDivisionId, $userStaffId) {
            // Case 1: Division-specific approval - check if user's division matches matrix division
            if ($userDivisionId) {
                $q->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'));
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
                                $trailQ->where('staff_id', '=',$userStaffId);
                              });
                    });
                });
            }
            
            // Case 2: Non-division-specific approval - check workflow definition and approver
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($userStaffId) {
                        $workflowQ->where('is_division_specific','=', 0)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($userStaffId) {
                                      $approverQ->where('staff_id', $userStaffId);
                                  });
                    });
                });
            }

            $q->orWhere('division_id', $userDivisionId);
        });

        $pendingMatrices = $query->paginate(20);

        // Apply the same additional filtering as index method for consistency
        $pendingMatrices->getCollection()->transform(function ($matrix) {
            return can_take_action($matrix) ? $matrix : null;
        });
        $pendingMatrices->setCollection($pendingMatrices->getCollection()->filter());

        // Get matrices approved by current user
        $approvedByMe = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow'
        ])->whereHas('approvalTrails', function($q) use ($userStaffId) {
            $q->where('staff_id', $userStaffId)
              ->whereIn('action', ['approved', 'rejected', 'returned']);
        })->paginate(20);

        // Get divisions for filter
        $divisions = Division::orderBy('division_name')->get();
        
        // Get focal persons for filter - focal person info is stored in divisions table
        $focalPersons = Staff::whereIn('staff_id', function($query) {
            $query->select('focal_person')
                  ->from('divisions')
                  ->whereNotNull('focal_person');
        })->orderBy('fname')
          ->get();

        return view('matrices.pending-approvals', compact(
            'pendingMatrices',
            'approvedByMe',
            'divisions',
            'focalPersons'
        ));
    }

    /**
     * Show the approval status page for a matrix.
     */
    public function status(Matrix $matrix): View
    {
        $matrix->load(['staff', 'division', 'forwardWorkflow', 'approvalTrails.staff']);
        
        // Get approval level information
        $approvalLevels = $this->getApprovalLevels($matrix);
        
        return view('matrices.status', compact('matrix', 'approvalLevels'));
    }

    /**
     * Get detailed approval level information for the matrix.
     */
    private function getApprovalLevels(Matrix $matrix): array
    {
        if (!$matrix->forward_workflow_id) {
            return [];
        }

        $levels = \App\Models\WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
            ->where('is_enabled', 1)
            ->orderBy('approval_order', 'asc')
            ->get();

        $approvalLevels = [];
        foreach ($levels as $level) {
            $isCurrentLevel = $level->approval_order == $matrix->approval_level;
            $isCompleted = $matrix->approval_level > $level->approval_order;
            $isPending = $matrix->approval_level == $level->approval_order && $matrix->overall_status === 'pending';
            
            $approver = null;
            if ($level->is_division_specific && $matrix->division) {
                $staffId = $matrix->division->{$level->division_reference_column} ?? null;
                if ($staffId) {
                    $approver = \App\Models\Staff::where('staff_id', $staffId)->first();
                }
            } else {
                $approverRecord = \App\Models\Approver::where('workflow_dfn_id', $level->id)->first();
                if ($approverRecord) {
                    $approver = \App\Models\Staff::where('staff_id', $approverRecord->staff_id)->first();
                }
            }

            $approvalLevels[] = [
                'order' => $level->approval_order,
                'role' => $level->role,
                'approver' => $approver,
                'is_current' => $isCurrentLevel,
                'is_completed' => $isCompleted,
                'is_pending' => $isPending,
                'is_division_specific' => $level->is_division_specific,
                'division_reference' => $level->division_reference_column,
                'category' => $level->category,
            ];
        }

        return $approvalLevels;
    }

    /**
     * Export matrices to CSV
     */
    public function exportCsv(Request $request)
    {
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow'
        ]);

        // Apply filters if provided
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
            $query->where('division_id', $request->division);
        }

        $matrices = $query->latest()->get();

        $filename = 'matrices_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($matrices) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Title', 'Year', 'Quarter', 'Division', 'Focal Person', 
                'Status', 'Approval Level', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($matrices as $matrix) {
                fputcsv($file, [
                    $matrix->id,
                    $matrix->title ?? 'N/A',
                    $matrix->year,
                    $matrix->quarter,
                    $matrix->division ? $matrix->division->division_name : 'N/A',
                    $matrix->focalPerson ? ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 'N/A',
                    $matrix->overall_status ?? 'N/A',
                    $matrix->approval_level ?? 'N/A',
                    $matrix->created_at ? $matrix->created_at->format('Y-m-d') : 'N/A',
                    $matrix->updated_at ? $matrix->updated_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export division matrices to CSV
     */
    public function exportDivisionCsv(Request $request)
    {
        $userDivisionId = user_session('division_id');
        
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow'
        ])->where('division_id', $userDivisionId);

        // Apply filters if provided
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
    
        if ($request->filled('quarter')) {
            $query->where('quarter', $request->quarter);
        }
    
        if ($request->filled('focal_person')) {
            $query->where('focal_person_id', $request->focal_person);
        }

        $matrices = $query->latest()->get();

        $filename = 'division_matrices_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($matrices) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Title', 'Year', 'Quarter', 'Division', 'Focal Person', 
                'Status', 'Approval Level', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($matrices as $matrix) {
                fputcsv($file, [
                    $matrix->id,
                    $matrix->title ?? 'N/A',
                    $matrix->year,
                    $matrix->quarter,
                    $matrix->division ? $matrix->division->division_name : 'N/A',
                    $matrix->focalPerson ? ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 'N/A',
                    $matrix->overall_status ?? 'N/A',
                    $matrix->approval_level ?? 'N/A',
                    $matrix->created_at ? $matrix->created_at->format('Y-m-d') : 'N/A',
                    $matrix->updated_at ? $matrix->updated_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export pending approvals to CSV
     */
    public function exportPendingApprovalsCsv(Request $request)
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');

        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow'
        ])->where('overall_status', 'pending')
          ->where('forward_workflow_id', '!=', null)
          ->where('approval_level', '>', 0);

        // Apply the same filtering logic as pendingApprovals method
        $query->where(function($q) use ($userDivisionId, $userStaffId) {
            if ($userDivisionId) {
                $q->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

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
                                $trailQ->where('staff_id', '=',$userStaffId);
                              });
                    });
                });
            }
            
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($userStaffId) {
                        $workflowQ->where('is_division_specific','=', 0)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($userStaffId) {
                                      $approverQ->where('staff_id', $userStaffId);
                                  });
                    });
                });
            }

            $q->orWhere('division_id', $userDivisionId);
        });

        $matrices = $query->get();

        // Apply the same additional filtering
        $matrices = $matrices->filter(function ($matrix) {
            return can_take_action($matrix);
        });

        $filename = 'pending_approvals_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($matrices) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Title', 'Year', 'Quarter', 'Division', 'Focal Person', 
                'Status', 'Approval Level', 'Current Approver', 'Created Date'
            ]);

            // CSV Data
            foreach ($matrices as $matrix) {
                fputcsv($file, [
                    $matrix->id,
                    $matrix->title ?? 'N/A',
                    $matrix->year,
                    $matrix->quarter,
                    $matrix->division ? $matrix->division->division_name : 'N/A',
                    $matrix->focalPerson ? ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 'N/A',
                    $matrix->overall_status ?? 'N/A',
                    $matrix->approval_level ?? 'N/A',
                    $matrix->current_actor ? ($matrix->current_actor->fname . ' ' . $matrix->current_actor->lname) : 'N/A',
                    $matrix->created_at ? $matrix->created_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export approved by me matrices to CSV
     */
    public function exportApprovedByMeCsv(Request $request)
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');

        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow',
            'approvalTrails'
        ])->whereHas('approvalTrails', function($q) use ($userStaffId) {
            $q->where('staff_id', $userStaffId);
        });

        // Apply filters if provided
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
            $query->where('division_id', $request->division);
        }

        $matrices = $query->latest()->get();

        $filename = 'approved_by_me_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($matrices, $userStaffId) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Title', 'Year', 'Quarter', 'Division', 'Focal Person', 
                'Status', 'Your Action', 'Action Date', 'Created Date'
            ]);

            // CSV Data
            foreach ($matrices as $matrix) {
                $myApproval = $matrix->approvalTrails->where('staff_id', $userStaffId)->first();
                fputcsv($file, [
                    $matrix->id,
                    $matrix->title ?? 'N/A',
                    $matrix->year,
                    $matrix->quarter,
                    $matrix->division ? $matrix->division->division_name : 'N/A',
                    $matrix->focalPerson ? ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 'N/A',
                    $matrix->overall_status ?? 'N/A',
                    $myApproval ? ucfirst($myApproval->action ?? 'Unknown') : 'N/A',
                    $myApproval && $myApproval->created_at ? $myApproval->created_at->format('Y-m-d H:i') : 'N/A',
                    $matrix->created_at ? $matrix->created_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}