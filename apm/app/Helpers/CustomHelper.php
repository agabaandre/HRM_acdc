<?php
use App\Models\ActivityApprovalTrail;
use App\Models\Approver;
use App\Models\MatrixApprovalTrail;
use App\Models\WorkflowDefinition;
use App\Models\Staff;
use App\Models\Division;
use Carbon\Carbon;


if (!function_exists('user_session')) {
    /**
     * Get a value from the session('user') array using dot notation.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    if (!function_exists('user_session')) {
        /**
         * Get a value from session('user') using dot notation
         */
        function user_session(?string $key = null, mixed $default = null): mixed
        {
            $user = session('user', []);
            return $key === null ? $user : data_get($user, $key, $default);
        }
        function isfocal_person()
        {
            $user = session('user'); // get the full user array
        
            $staff_id = $user['staff_id'] ?? null;
            $division_fp_id = $user['focal_person'] ?? null;
        
            return $staff_id === $division_fp_id;
        }
        
    }

    if (!function_exists('still_with_creator')) {
        /**
         * Get a value from session('user') using dot notation
         */
        function still_with_creator($matrix)
        {

            $user = (Object) session('user', []);
            //$creator = $matrix->staff;
            //$creator->division_id == $user['division_id'] &&
            return ( ((($user->staff_id == $matrix->staff_id || $matrix->focal_person_id == $user->staff_id) && ($matrix->forward_workflow_id==null)) || ($matrix->division->division_head==$user->staff_id && $matrix->forward_workflow_id==1))  && in_array($matrix->overall_status,['draft','returned']));
        }

    }

    
    if (!function_exists('done_approving')) {
        /**
         * Get a value from session('user') using dot notation
         */
        function done_approving($matrix)
        {
         
            $user = session('user', []);
            $my_appoval =  MatrixApprovalTrail::where('matrix_id',$matrix->id)
            ->where('action','approved')
            ->where('staff_id',$user['staff_id'])->pluck('id');
            

            return count($my_appoval)>0;
        }

    }

    if (!function_exists('done_approving_activty')) {
        /**
         *Check wether user approval activity
         */
        function done_approving_activty($activity)
        {
         
            $user = session('user', []);
            $my_appoval =  ActivityApprovalTrail::where('activity_id',$activity->id)
            ->where('action','passed')
            ->where('staff_id',$user['staff_id'])->pluck('id');

            return count($my_appoval)>0;
        }

    }

    if (!function_exists('activities_approved_by_me')) {
        function activities_approved_by_me($matrix){
            $user = session('user', []);
            
            // Get all unique activity_ids for this matrix
            $unique_activities = ActivityApprovalTrail::where("matrix_id", $matrix->id)
                ->where("staff_id", $user['staff_id'])
                ->distinct()
                ->pluck('activity_id');

            // If no activities exist, return false
            if ($unique_activities->isEmpty()) {
                return false;
            }

            // For each unique activity, check if user has at least one 'passed' approval
            foreach ($unique_activities as $activity_id) {
                $has_approved = ActivityApprovalTrail::where("staff_id", $user['staff_id'])
                    ->where("matrix_id", $matrix->id)
                    ->where("activity_id", $activity_id)
                    ->where("action", "passed")
                    ->exists();

                if (!$has_approved)
                    return false;
            }

            return true;
        }
    }


    if (!function_exists('can_take_action')) {
        /**
         * Get a value from session('user') using dot notation
         */
        function can_take_action($matrix)
        {
            $user = session('user', []);

           // dd($user);

           if (empty($user['staff_id']) || done_approving($matrix)) {
               return false;
           }

            $still_with_creator = still_with_creator($matrix);

            if($still_with_creator)
            return false;

            $today = Carbon::today();

            //Check that matrix is at users approval level by getting approver for that staff, at the level of approval the matrix is at
            $current_approval_point = WorkflowDefinition::where('approval_order', $matrix->approval_level)
            ->where('workflow_id',$matrix->forward_workflow_id)
            ->first();
           
          
            $workflow_dfns = Approver::where('staff_id', $user['staff_id'])
            ->where('workflow_dfn_id', $current_approval_point->id)
            ->orWhere(function ($query) use ($today, $user,$current_approval_point) {
                    $query ->where('workflow_dfn_id',$current_approval_point->id)
                    ->where('oic_staff_id', $user['staff_id'])
                    ->where('end_date', '>=', $today);
                })
            ->orderBy('id','desc')
            ->pluck('workflow_dfn_id');

          
            $division_specific_access=false;
            $is_at_my_approval_level =false;

          
           //if user is not defined in the approver table, $workflow_dfns will be empty
            if ($workflow_dfns->isEmpty()) {

            
            //$possible_approval_point = WorkflowDefinition where workflow_id = $matrix->forward_workflow_id and approval_order=$matrix->approval_level{
            // if($current_approval_point->division_specific)
            // get from staff where id is $matrix->staff_id and get their division_id
            // $division = got to Division::where('directorate_id' is get the division_d of the staff_id that created the matrix .ie (matrix->staff_id)
            // The get $division->{$possible_approval_point->divsion_reference_column} and that value must be equal to user_session()['staff_id'], if it is, return true
           // }
            
                $division_specific_access = false;
                
                if ($current_approval_point && $current_approval_point->is_division_specific) {
                    $division = $matrix->division;
                  
                    //staff holds current approval role in division
                    if ($division && $division->{$current_approval_point->division_reference_column} == user_session()['staff_id']) {
                        $division_specific_access = true;
                    }
                }

                //how to check approval levels against approver in approvers table???
                
            }else{

                $next_definition = WorkflowDefinition::whereIn('workflow_id', $workflow_dfns->toArray())
                ->where('approval_order',(int) $matrix->approval_level)
                ->where('is_enabled',1)
                ->orderBy('approval_order')
                ->get();

                if ($next_definition->count() > 1) {

                    if ($matrix->has_extramural && $matrix->approval_level !== $current_approval_point->first()->approval_order) {
                        $current_approval_point =  $next_definition->where('fund_type', 2);
                    } 
                    else 
                        $current_approval_point = $next_definition->where('fund_type', 1);
                }

                $is_at_my_approval_level = ($current_approval_point)?($current_approval_point->workflow_id === $matrix->forward_workflow_id && $matrix->approval_level =  $current_approval_point->approval_order):false;
            }      

           /**TODO
            * Factor in approval conditions 
            */
 
            return ( ($is_at_my_approval_level || $still_with_creator || $division_specific_access) && $matrix->overall_status !== 'approved');
        }
        
    }


   
    
}
