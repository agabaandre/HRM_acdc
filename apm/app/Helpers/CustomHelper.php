<?php
use App\Models\ActivityApprovalTrail;
use App\Models\Approver;
use App\Models\MatrixApprovalTrail;
use App\Models\WorkflowDefinition;
use App\Models\Staff;
use App\Models\Matrix;
use Carbon\Carbon;
use App\Mail\MatrixNotification;
use App\Models\Notification;


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
            return  can_division_head_edit($matrix) || ( ((($user->staff_id == $matrix->staff_id || $matrix->focal_person_id == $user->staff_id) && ($matrix->forward_workflow_id==null)))  && in_array($matrix->overall_status,['draft','returned']));
        }

    }


    if (!function_exists('can_division_head_edit')) {
        function can_division_head_edit($matrix){
            $user = (Object) session('user', []);
            return ($matrix->division->division_head==$user->staff_id && $matrix->approval_level==1 &&  in_array($matrix->overall_status,['draft','returned']));
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
            ->where('approval_order',$matrix->approval_level)
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

            if($still_with_creator || !$matrix->forward_workflow_id)
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

if (!function_exists('get_matrix_notification_recipient')) {
    /**
     * Get the staff member who should be notified for matrix approval
     * 
     * @param Matrix $matrix
     * @return Staff|null
     */
    function get_matrix_notification_recipient($matrix)
    {
        if ($matrix->overall_status === 'approved') {
            return null;
        }

        $today = Carbon::today();
        $current_approval_point = WorkflowDefinition::where('approval_order', $matrix->approval_level)
            ->where('workflow_id', $matrix->forward_workflow_id)
            ->first();

        if (!$current_approval_point) {
            return null;
        }

        // Check for regular approvers first
        $approver = Approver::where('workflow_dfn_id', $current_approval_point->id)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->first();

        if ($approver) {
            return Staff::where('staff_id', $approver->staff_id)->first();
        }

        // Check for OIC approvers
        $oic_approver = Approver::where('workflow_dfn_id', $current_approval_point->id)
            ->where('oic_staff_id', '!=', null)
            ->where('end_date', '>=', $today)
            ->first();

        if ($oic_approver) {
            return Staff::where('staff_id', $oic_approver->oic_staff_id)->first();
        }

        // Check for division-specific approvers
        if ($current_approval_point->is_division_specific) {
            $division = $matrix->division;
            if ($division && $division->{$current_approval_point->division_reference_column}) {
                return Staff::where('staff_id', $division->{$current_approval_point->division_reference_column})->first();
            }
        }

        return null;
    }
}

if (!function_exists('send_matrix_notification')) {
    /**
     * Send a notification to the appropriate staff member for matrix approval
     * This will create a database notification and send an email
     * 
     * @param Matrix $matrix
     * @param string $type The type of notification (e.g., 'matrix_approval', 'matrix_returned', etc.)
     * @return Notification|null
     */
    function send_matrix_notification(Matrix $matrix, $type = 'matrix_approval')
    {
        $recipient = get_matrix_notification_recipient($matrix);

        if (!$recipient) {
            return null;
        }

        // Generate appropriate message based on type
        $message = '';
        switch($type) {
            case 'matrix_approval':
                $message = sprintf(
                    'Matrix #%d requires your approval. Created by %s %s.',
                    $matrix->id,
                    $matrix->staff->fname,
                    $matrix->staff->lname
                );
                break;
            case 'matrix_returned':
                $message = sprintf(
                    'Matrix #%d has been returned for revision by %s %s.',
                    $matrix->id,
                    $matrix->staff->fname,
                    $matrix->staff->lname
                );
                break;
            default:
                $message = sprintf(
                    'Matrix #%d requires your attention.',
                    $matrix->id
                );
        }

        // Create notification record
        $notification = Notification::create([
            'staff_id' => $recipient->staff_id,
            'matrix_id' => $matrix->id,
            'message' => $message,
            'type' => $type,
            'is_read' => false
        ]);

        return $notification;
    }
}

if (!function_exists('send_matrix_email_notification')) {
    /**
     * Send an email notification for matrix approval
     * 
     * @param Matrix $matrix
     * @param string $type The type of notification
     * @return bool
     */
    function send_matrix_email_notification(Matrix $matrix, $type = 'matrix_approval')
    {
        $recipient = get_matrix_notification_recipient($matrix);
        
        dd($recipient);
        
        
        if (!$recipient || !$recipient->work_email) {
            return false;
        }

        try {
            // Generate message based on type
            $message = '';
            switch($type) {
                case 'matrix_approval':
                    $message = sprintf(
                        'Matrix #%d requires your approval. Created by %s %s.',
                        $matrix->id,
                        $matrix->staff->fname,
                        $matrix->staff->lname
                    );
                    break;
                case 'matrix_returned':
                    $message = sprintf(
                        'Matrix #%d has been returned for revision by %s %s.',
                        $matrix->id,
                        $matrix->staff->fname,
                        $matrix->staff->lname
                    );
                    break;
                default:
                    $message = sprintf(
                        'Matrix #%d requires your attention.',
                        $matrix->id
                    );
            }

              // Create notification record
             Notification::create([
                    'staff_id' => $recipient->staff_id,
                    'matrix_id' => $matrix->id,
                    'message' => $message,
                    'type' => $type,
                    'is_read' => false
             ]);

            // Send email
            \Mail::to($recipient->work_email)
                ->send(new MatrixNotification( $matrix,$recipient, $type, $message));
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send matrix notification email: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('mark_matrix_notifications_read')) {
    /**
     * Mark all notifications as read for a staff member on a specific matrix
     * 
     * @param int $staff_id The staff ID
     * @param int $matrix_id The matrix ID
     * @return int Number of notifications marked as read
     */
    function mark_matrix_notifications_read($staff_id, $matrix_id)
    {
        return Notification::where('staff_id', $staff_id)
            ->where('matrix_id', $matrix_id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }
}

if (!function_exists('get_staff_unread_notifications_count')) {
    /**
     * Get the count of unread notifications for a staff member
     * 
     * @param int $staff_id The staff ID
     * @param string|null $type Optional notification type to filter by
     * @return int Number of unread notifications
     */
    function get_staff_unread_notifications_count( $type = null)
    {
        $user = session('user', []);
        $staff_id = $user['staff_id'];
        $query = Notification::where('staff_id', $staff_id)
            ->where('is_read', false);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->count();
    }
}
