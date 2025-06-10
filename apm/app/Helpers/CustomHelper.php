<?php
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

            $user = session('user', []);
            $creator = Staff::where('staff_id',$matrix->staff_id)->first();
            return ($creator->division_id == $user['division_id'] && $matrix->forward_workflow_id==null && $matrix->overall_status !== 'approved');
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


    if (!function_exists('can_take_action')) {
        /**
         * Get a value from session('user') using dot notation
         */
        function can_take_action($matrix)
        {
            $user = session('user', []);

            if (empty($user['staff_id']) || done_approving($matrix)) {
                return false;
            }

            $still_with_creator = still_with_creator($matrix);

            if($still_with_creator)
            return false;

            $today = Carbon::today();

            //Check that matrix is at users approval level by getting approver for that staff, at the level of approval the matrix is at


            $workflow_dfns = Approver::where('staff_id', $user['staff_id'])
            ->where('workflow_dfn_id',$matrix->forward_workflow_id)
            ->orWhere(function ($query) use ($today, $user,$matrix) {
                    $query ->where('workflow_dfn_id',$matrix->forward_workflow_id)
                    ->where('oic_staff_id', $user['staff_id'])
                    ->where('end_date', '>=', $today);
                })
                ->pluck('workflow_dfn_id');


            $division_specific_access=false;

           //if user is not defined in the approver table, $workflow_dfns will be empty
            if ($workflow_dfns->isEmpty()) {

            //$possible_approval_point = WorkflowDefinition where workflow_id = $matrix->forward_workflow_id and approval_order=$matrix->approval_level{
            // if($current_approval_point->division_specific)
            // get from staff where id is $matrix->staff_id and get their division_id
            // $division = got to Division::where('directorate_id' is get the division_d of the staff_id that created the matrix .ie (matrix->staff_id)
            // The get $division->{$possible_approval_point->divsion_reference_column} and that value must be equal to user_session()['staff_id'], if it is, return true
           // }
           
                $possible_approval_point = WorkflowDefinition::where('approval_order', $matrix->approval_level)
                    ->first();
                
                $division_specific_access = false;

                if ($possible_approval_point && $possible_approval_point->division_specific) {
                   
                    $staff    = Staff::where('staff_id',$matrix->staff_id)->first();
                    $division = Division::where('directorate_id', $staff->division_id)->first();
                    
                    if ($division && $division->{$possible_approval_point->division_reference_column} == user_session()['staff_id']) {
                        $division_specific_access = true;
                    }
                }
                //how to check approval levels against approver in approvers table???
                
            }

            $current_approval_point = WorkflowDefinition::whereIn('id', $workflow_dfns)
                ->orderBy('approval_order')
                ->first();

                dd($current_approval_point);

           /**TODO
            * Factor in approval conditions 
            */

            $is_at_my_approval_level = ($current_approval_point)?($current_approval_point->workflow_id === $matrix->forward_workflow_id && $matrix->approval_level =  $current_approval_point->approval_order):false;

            return ( ($is_at_my_approval_level  || $still_with_creator || $division_specific_access) && $matrix->overall_status !== 'approved');
        }
        
    }


   
    
}
