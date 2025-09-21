<?php
use App\Models\ActivityApprovalTrail;
use App\Models\Approver;
use App\Models\ApprovalTrail;
use App\Models\WorkflowDefinition;
use Carbon\Carbon;
use App\Models\Division;
use Illuminate\Support\Facades\DB;

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
            return $key == null ? $user : data_get($user, $key, $default);
        }
        

        function isfocal_person()
        {
            $user = session('user');
            $staff_id = $user['staff_id'] ?? null;
            $division_id = $user['division_id'] ?? null;

            if (!$staff_id || !$division_id) {
                return false;
            }

            $division = Division::find($division_id);
            //dd($division);
            if (!$division) {
                return false;
            }
            //dd($division->focal_person);

            $division_fp_id = $division->focal_person ?? null;

            return $staff_id == $division_fp_id;
        }
        
    }

    if (!function_exists('still_with_creator')) {
        /**
         * Determine if the activity/matrix is still with the creator or focal person for editing.
         * Returns true if:
         *   - The division head can edit (returned to division head), OR
         *   - The current user is the staff, focal person, or responsible staff (if activity given),
         *     and the matrix is in 'draft' or 'returned' status, and
         *     (the matrix has not been forwarded OR is in draft and with focal person)
         */
        function still_with_creator($matrix, $activity = null)
        {
            $user = session('user', []);
            $staffId = $user['staff_id'] ?? null;

            //dd($staffId);

            // If division head can edit, allow
            if (can_division_head_edit($matrix)) {
                //dd("here");
                return true;
            }

            // If matrix is in draft or returned
            if (!in_array($matrix->overall_status, ['draft', 'returned'])) {
                //=dd("here");
                return false;
            }

            // If matrix is in draft, allow staff, focal person, or responsible staff to edit
            if ($matrix->overall_status === 'draft') {
                    //dd("here");//  dd($activity);
                $allowedIds = [$matrix->staff_id, $matrix->focal_person_id];
   
                if ($activity && isset($activity->responsible_person_id)) {
                    $allowedIds[] = $activity->responsible_person_id;
                }
                //dd($activity->responsible_person_id);
                return in_array($staffId, $allowedIds);
            }

            // If matrix is returned and not forwarded, allow staff, focal person, or responsible staff to edit
            if ($matrix->overall_status === 'returned' && $matrix->forward_workflow_id === null) {
                $allowedIds = [$matrix->staff_id, $matrix->focal_person_id];
                if ($activity && isset($activity->responsible_person_id)) {
                    $allowedIds[] = $activity->responsible_person_id;
                }
                return in_array($staffId, $allowedIds);
            }

            return false;
        }
    }



    // if (!function_exists('still_with_creator')) {
    //     /**
    //      * Get a value from session('user') using dot notation
    //      */
    //     function still_with_creator($matrix,$activity=null)
    //     {
    //         //dd(session('user'));
    //         //dd((can_division_head_edit($matrix)));
    //        // dd( ((in_array(session('user')['staff_id'],[$matrix->staff_id,$matrix->focal_person_id,$activity?$activity->responsible_staff_id:null]) && ($matrix->forward_workflow_id==null)))) && in_array($matrix->overall_status,['draft','returned']);
    //         return  (can_division_head_edit($matrix) ||  ((in_array(session('user')['staff_id'],[$matrix->staff_id,$matrix->focal_person_id,$activity?$activity->responsible_staff_id:null]) && ($matrix->forward_workflow_id==null)))) && in_array($matrix->overall_status,['draft','returned']);
    //     }

        

    // }


    if (!function_exists('can_division_head_edit')) {
        function can_division_head_edit($matrix){
            $user = (Object) session('user', []);
            return ($matrix->division->division_head==$user->staff_id && $matrix->approval_level==1 && activities_approved_by_me($matrix) && in_array($matrix->overall_status,['returned']));
        }
     }

     if (!function_exists('can_print_memo')) {
        function can_print_memo($memo) {
            $user = (object) session('user', []);
            // Must be owner or responsible person
            $isOwner = isset($memo->staff_id, $user->staff_id) && $memo->staff_id == $user->staff_id;
            $isResponsible = isset($memo->responsible_person_id, $user->staff_id) && $memo->responsible_person_id == $user->staff_id;

            // Only allow if status is approved
            $isApproved = isset($memo->overall_status) && $memo->overall_status === 'approved';

            // If this is a matrix memo, check matrix approval and activity approval
            $isMatrixApproved = false;
            $isActivityApproved = false;
            if (isset($memo->matrix)) {
                $isMatrixApproved = isset($memo->matrix->overall_status) && $memo->matrix->overall_status === 'approved';
                if (isset($memo->activity)) {
                    $isActivityApproved = isset($memo->activity->overall_status) && $memo->activity->overall_status === 'approved';
                }
            }

            return ($isOwner || $isResponsible) && $isApproved || ($isOwner || $isResponsible) && $isMatrixApproved && $isActivityApproved;
        }
     }

     if (!function_exists('can_request_memo_action')) {
        /**
         * Helper to determine if a user can request a specific action (services or ARF) on a memo.
         * 
         * @param object $memo
         * @param string $type 'services' for intramural, 'arf' for extramural
         * @return bool
         */
        function can_request_memo_action($memo, $type) {
            $user = (object) session('user', []);
            // Must be owner or responsible person
            $isOwner = isset($memo->staff_id, $user->staff_id) && $memo->staff_id == $user->staff_id;
            $isResponsible = isset($memo->responsible_person_id, $user->staff_id) && $memo->responsible_person_id == $user->staff_id;
            //@dd($isOwner,$isResponsible,$memo);
            // Only allow if status is approved
            $isApproved = isset($memo->overall_status) && $memo->overall_status === 'approved';
            
            // If this is a matrix memo, check matrix approval and activity approval
            $isMatrixApproved = false;
            $isActivityApproved = false;
            if (isset($memo->matrix)) {
                $isMatrixApproved = isset($memo->matrix->overall_status) && $memo->matrix->overall_status === 'approved';
              
            }
            //dd($isMatrixApproved);

            // Fund type check
            $fundTypeId = isset($memo->fundType->id) ? $memo->fundType->id : null;

            $isTypeAllowed = false;
            //dd($type);
            if ($type === 'services') {
                
                $isTypeAllowed = $fundTypeId == 1; // intramural
            } elseif ($type === 'arf') {
                $isTypeAllowed = $fundTypeId == 2; // extramural
            }
            //dd($isTypeAllowed);
           // dd($type);

            return ($isOwner || $isResponsible) && ($isApproved || $isMatrixApproved)  && $isTypeAllowed;
        }
     }

     // For backward compatibility, keep the old function names as wrappers
     if (!function_exists('can_request_services')) {
        function can_request_services($memo) {
            return can_request_memo_action($memo, 'services');
        }
     }

     if (!function_exists('can_request_arf')) {
        function can_request_arf($memo) {
            return can_request_memo_action($memo, 'arf');
        }
     }

    
 

     if (!function_exists('can_edit_memo')) {
        function can_edit_memo($memo) {
            $user = (object) session('user', []);
            $session_division_id = isset($user->division_id) ? $user->division_id : null;

            // Must be owner or responsible person
            $isOwner = isset($memo->staff_id, $user->staff_id) && $memo->staff_id == $user->staff_id;
            $isResponsible = isset($memo->responsible_person_id, $user->staff_id) && $memo->responsible_person_id == $user->staff_id;

            // Only allow if status is draft or returned
            $isApproved = (isset($memo->overall_status) && ($memo->overall_status == 'draft' || $memo->overall_status == 'returned'));

            // If this is a matrix memo, check matrix approval and activity approval
            // Default to true only if not set, otherwise check actual status
            $isMatrixApproved = true;
            if (isset($memo->matrix)) {
                $isMatrixApproved = (isset($memo->matrix->overall_status) && ($memo->matrix->overall_status == 'draft' || $memo->matrix->overall_status == 'returned'));
            }

            $isActivityApproved = true;
            if (isset($memo->activity)) {
                $isActivityApproved = (isset($memo->activity->overall_status) && ($memo->activity->overall_status == 'draft' || $memo->activity->overall_status == 'returned'));
            }

            // Check if user is division head of the memo's division
            $isDivisionHead = false;
            
            if (isset($memo->division_id)) {
                // Get all division IDs where the user is the division head
                $divisionIds = Division::where('division_head', $user->staff_id)->pluck('id')->toArray();

                if (in_array($memo->division_id, $divisionIds)) {
                    $isDivisionHead = true;
                }
            }

            return (
                ($isOwner || $isResponsible) && $isApproved && $isMatrixApproved && $isActivityApproved
            ) || $isDivisionHead;
        }
     }
     

    
    if (!function_exists('done_approving')) {
        /**
         * Check if the user's last action at this approval level or higher was 'approved'
         * (caters for returns by considering the latest action)
         */
        function done_approving($matrix)
        {
            $user = session('user', []);
            $approval = ApprovalTrail::where('model_id', $matrix->id)
                ->where('model_type', 'App\Models\\' . ucfirst(class_basename($matrix)))
                ->where('approval_order', '>=', $matrix->approval_level)
                ->where('staff_id', $user['staff_id'])
                ->orderByDesc('id')
                ->first();

            return $approval && $approval->action === 'approved';
        }
    }

    if (!function_exists('can_approve_activity')) {

        
        function can_approve_activity($activity){

            if ($activity->is_single_memo==1)
               return false;
          
            if($activity->matrix->forward_workflow_id==null)
                return false;
          
            if($activity->fund_type_id==3)
                return true;

            if(!$activity->matrix->workflow_definition->allowed_funders||empty($activity->matrix->workflow_definition->allowed_funders))
                return true;

            return  in_array($activity->activity_budget[0]->fundcode->fund_type_id,json_decode(@$activity->matrix->workflow_definition->allowed_funders));
        }
    }

    if (!function_exists('done_approving_activty')) {
        /**
         *Check wether user approval activity
         */
        function done_approving_activty($activity)
        {
            $user = session('user', []);
            // Get the latest approval trail for this activity, matrix, user, and approval level
            $latest_approval = ActivityApprovalTrail::where('activity_id', $activity->id)
                ->where('matrix_id', $activity->matrix_id)
                ->where('approval_order', $activity->matrix->approval_level)
                ->where('staff_id', $user['staff_id'])
                ->where('action', 'passed')
                ->orderByDesc('id')
                ->first();

            return isset($latest_approval->action);
        }

    }

    if (!function_exists('get_approvable_activities')) {
        function get_approvable_activities($matrix){
            $approvable_activities = collect();
            foreach($matrix->activities as $activity) {
                if (can_approve_activity($activity)) {
                    $approvable_activities->push($activity);
                }
            }
            return $approvable_activities;
        }
    }

    if (!function_exists('activities_approved_by_me')) {
        function activities_approved_by_me($matrix){
            $user = session('user', []);
            
            // Get all activities that the user can approve (based on can_approve_activity logic)
            $approvable_activities = get_approvable_activities($matrix);
            $has_approved = false;

            // If no approvable activities exist, return false
            if ($approvable_activities->isEmpty())
                 $has_approved=false;

            // For each approvable activity, check if user has at least one 'passed' approval
            foreach ($approvable_activities as $activity) {
                $has_approved = ActivityApprovalTrail::where("staff_id", $user['staff_id'])
                    ->where("matrix_id", $matrix->id)
                    ->where("approval_order", $matrix->approval_level)
                    ->where("activity_id", $activity->id)
                    ->where("action", "passed")
                    ->exists();

                if(!$has_approved)
                break;
            }

            return $has_approved;
        }
    }


    if (!function_exists('can_take_action')) {
        /**
         * Get a value from session('user') using dot notation
         */
        function can_take_action($matrix)
        {
            $user = session('user', []);

            ///dd($user);
           // dd(done_approving($matrix));
          

           if (empty($user['staff_id']) || done_approving($matrix) || in_array($matrix->overall_status,['approved','draft'])) {
             
            return false;

            
           }

            $still_with_creator = still_with_creator($matrix);
            //dd($still_with_creator);

            if($still_with_creator || !$matrix->forward_workflow_id)
            {
               // dd('here');
            return false;
        
            }
           

            $today = Carbon::today();

            //Check that matrix is at users approval level by getting approver for that staff, at the level of approval the matrix is at
            $current_approval_point = WorkflowDefinition::where('approval_order', $matrix->approval_level)
            ->where('workflow_id',$matrix->forward_workflow_id);

            $workflow_dfns = Approver::where('staff_id',"=", $user['staff_id'])
            ->whereIn('workflow_dfn_id',$current_approval_point->pluck('id'))
            ->orWhere(function ($query) use ($today, $user,$current_approval_point) {
                    $query ->whereIn('workflow_dfn_id',$current_approval_point->pluck('id'))
                    ->where('oic_staff_id', "=", $user['staff_id'])
                    ->where('end_date', '>=', $today);
                })
            ->orderBy('id','desc')
            ->pluck('workflow_dfn_id');

           // dd($workflow_dfns);
           
            $division_specific_access=false;
            $is_at_my_approval_level =false;

            
           //if user is not defined in the approver table, $workflow_dfns will be empty
            if ($workflow_dfns->isEmpty()) {
                //dd("here");
                $division_specific_access = false;

                $current_approval_point = $current_approval_point->first();

                if(!$current_approval_point)
                 return false;
                
                if ($current_approval_point && $current_approval_point->is_division_specific) {
                    $division = $matrix->division;
                  
                    //staff holds current approval role in division
                    if ($division && $division->{$current_approval_point->division_reference_column} == user_session()['staff_id']) {
                        $division_specific_access = true;
                    }
                }
                // dd('here');
                //how to check approval levels against approver in approvers table???
                
            }else{
                // dd("here2");
                 //dd($current_approval_point);
               // $current_approval_point = $current_approval_point->where('approval_order',$workflow_dfns[0])->first();
                $current_approval_point = $current_approval_point->where('id',$workflow_dfns[0])->first();
               // dd(getFullSql(($current_approval_point)));
               //dd($current_approval_point);
                $next_definition = WorkflowDefinition::whereIn('workflow_id', $workflow_dfns->toArray())
                ->where('approval_order',(int) $matrix->approval_level)
                ->where('is_enabled',1)
                ->orderBy('approval_order')
                ->get();


                if ($next_definition->count() > 1) {

                    //if any of next_definition has fund_type, then do the if below
                    $has_fund_type = $next_definition->whereNotNull('fund_type')->count() > 0;
                    
                    if ($has_fund_type) {
                        if ($matrix->has_extramural && $matrix->approval_level !== $current_approval_point->approval_order) {
                            $current_approval_point = $next_definition->where('fund_type', 2)->first();
                        } else {
                            $current_approval_point = $next_definition->where('fund_type', 1)->first();
                        }
                    }else{

                        $has_category = $next_definition->whereNotNull('category')->count() > 0;

                        if($has_category){
                            $current_approval_point = $next_definition->where('category', $matrix->division->category)->first();
                        }else{
                            $current_approval_point = $next_definition->first();
                        }

                    }
                }

                $is_at_my_approval_level = ($current_approval_point) ? 
                    ($current_approval_point->workflow_id === $matrix->forward_workflow_id && $matrix->approval_level == $current_approval_point->approval_order) : 
                    false;
            }      

           /**TODO
            * Factor in approval conditions 
            */
            //dd('is_at_my_level'.$is_at_my_approval_level,' stil creator:'.$still_with_creator  );
 
            return ( ($is_at_my_approval_level || $still_with_creator || $division_specific_access) && $matrix->overall_status !== 'approved');
        }
        
    }


   
    
}

if (!function_exists('reduce_fund_code_balance')) {
    /**
     * Reduce the budget_balance in the fund_codes table by a given amount.
     *
     * @param int $fundCodeId
     * @param float $amount
     * @return bool
     */
    function reduce_fund_code_balance($fundCodeId, $amount)
    {
        if ($fundCodeId && $amount > 0) {
            return DB::table('fund_codes')
                ->where('id', $fundCodeId)
                ->decrement('budget_balance', $amount);
        }
        return false;
    }
}

if (!function_exists('isDivisionApprover')) {
    /**
     * Check if the current user is assigned as an approver for division-specific workflow definitions.
     *
     * @param int|null $staffId Optional staff ID, defaults to current user's staff ID
     * @return bool
     */
    function isDivisionApprover(?int $staffId = null): bool
    {
        $userStaffId = $staffId ?? user_session('staff_id');
        
        if (!$userStaffId) {
            return false;
        }
        
        return \App\Models\Approver::where('staff_id', $userStaffId)
            ->whereHas('workflowDefinition', function($q) {
                $q->where('is_division_specific', 1);
            })
            ->exists();
    }
}

if (!function_exists('allow_print_activity')) {
    /**
     * Check if an activity can be printed based on workflow approval status and matrix approval.
     *
     * @param \App\Models\Activity $activity The activity to check
     * @return bool
     */
    function allow_print_activity($activity): bool
    {
        // Check if the matrix exists and is approved
        if (!$activity->matrix || $activity->matrix->overall_status !== 'approved') {
            return false;
        }
        
        // Check if the activity has been passed by the final approver
        // Get the final approval order from the workflow definition
        $finalApprovalOrder = \App\Models\WorkflowDefinition::where('workflow_id', $activity->matrix->forward_workflow_id)
            ->where('is_enabled', 1)
            ->orderBy('approval_order', 'desc')
            ->value('approval_order');
        
        if (!$finalApprovalOrder) {
            return false;
        }
        
        // Check if there's a 'passed' approval trail entry for the final approval order
        $finalApprovalExists = \App\Models\ActivityApprovalTrail::where('activity_id', $activity->id)
            ->where('approval_order', $finalApprovalOrder)
            ->where('action', 'passed')
            ->exists();
        
        return $finalApprovalExists;
    }
}


function getFullSql($query) {
    $sql = $query->toSql();
    foreach ($query->getBindings() as $binding) {
        $value = is_numeric($binding) ? $binding : "'{$binding}'";
        $sql = preg_replace('/\?/', $value, $sql, 1);
    }
    return $sql;
}
/**
 * Generate short code from division name
 * Used to create division short names for document numbering
 */
function generateShortCodeFromDivision(string $name): string
{
    $ignore = ['of', 'and', 'for', 'the', 'in'];
    $words = preg_split('/\s+/', strtolower($name));
    $initials = array_map(function ($word) use ($ignore) {
        // Check if word is not empty before accessing first character
        if (empty($word) || in_array($word, $ignore)) {
            return '';
        }
        return strtoupper($word[0]);
    }, $words);

    return implode('', array_filter($initials));
}

/**
 * Get the assigned workflow ID for a model
 * 
 * @param string $modelName The model name (e.g., 'Matrix', 'Activity', etc.)
 * @return int|null The workflow ID or null if not assigned
 */
function getModelWorkflowId(string $modelName): ?int
{
    return \App\Models\WorkflowModel::getWorkflowIdForModel($modelName);
}

/**
 * Set the assigned workflow ID for a model
 * 
 * @param string $modelName The model name
 * @param int $workflowId The workflow ID to assign
 * @param string|null $description Optional description
 * @return \App\Models\WorkflowModel
 */
function setModelWorkflowId(string $modelName, int $workflowId, ?string $description = null): \App\Models\WorkflowModel
{
    return \App\Models\WorkflowModel::setWorkflowIdForModel($modelName, $workflowId, $description);
}

/**
 * Generate document number for any model
 * This is the main function to use for generating document numbers
 */
function generateDocumentNumber($model, string $documentType = null): string
{
    return \App\Services\DocumentNumberService::generateForAnyModel($model);
}

/**
 * Dispatch job to assign document number after model creation
 * This prevents race conditions and ensures unique numbering
 */
function assignDocumentNumber($model, string $documentType = null): void
{
    \App\Jobs\AssignDocumentNumberJob::dispatch($model, $documentType);
}

/**
 * Get next document number preview without incrementing counter
 */
function getNextDocumentNumberPreview(string $documentType, $division = null, int $year = null): string
{
    $divisionShortName = null;
    $divisionId = null;
    
    if (is_object($division)) {
        $divisionShortName = $division->division_short_name ?? null;
        $divisionId = $division->id ?? null;
    } elseif (is_string($division)) {
        $divisionShortName = $division;
    } elseif (is_numeric($division)) {
        $divisionId = $division;
    }
    
    return \App\Services\DocumentNumberService::getNextNumberPreview(
        $documentType, 
        $divisionShortName, 
        $divisionId, 
        $year
    );
}

/**
 * Display memo status with appropriate badge styling
 * 
 * Logic:
 * - Single Memos: Show overall_status
 * - Non-Travel/Special Memos: Show overall_status  
 * - Matrix Activities:
 *   - If user is approver: Show their specific approval action with approver name and level
 *   - If user is not approver: Show matrix overall_status
 * 
 * For matrix activities, when showing approval actions, includes:
 * - Action taken (passed, returned, etc.)
 * - Approver's full name
 * - Approval level/role from workflow definition
 * 
 * @param mixed $memo The memo/activity object
 * @param string $type The memo type ('single_memo', 'non_travel', 'special', 'matrix_activity')
 * @return string HTML badge element
 */
function display_memo_status($memo, $type)
{
    $user = session('user', []);
    $staffId = $user['staff_id'] ?? null;
    
    $statusText = '';
    $badgeClass = 'bg-secondary';
    
    // Determine memo type based on passed parameter
    $isMatrixActivity = $type === 'matrix_activity';
    $isNonTravel = $type === 'non_travel';
    $isSpecialMemo = $type === 'special';
    $isSingleMemo = $type === 'single_memo';
    
    if ($isSingleMemo) {
        // For single memos, show the overall status
        $statusText = ucwords($memo->overall_status ?? 'pending');
        $badgeClass = get_status_badge_class($memo->overall_status ?? 'pending');
        
    } elseif ($isNonTravel || $isSpecialMemo) {
        // For non-travel or special memos, show the overall status
        $statusText = ucwords($memo->overall_status ?? 'pending');
        $badgeClass = get_status_badge_class($memo->overall_status ?? 'pending');
        
    } elseif ($isMatrixActivity) {
        // For matrix activities
        if ($memo->matrix && can_approve_activity($memo)) {
            // User is an approver - show their specific action with name and level
            $latestApproval = ActivityApprovalTrail::where('activity_id', $memo->id)
                ->where('matrix_id', $memo->matrix_id)
                ->where('staff_id', $staffId)
                ->with(['staff', 'workflowDefinition'])
                ->orderByDesc('id')
                ->first();
                
            if ($latestApproval && $latestApproval->action) {
                $action = ucwords($latestApproval->action);
                $approverName = $latestApproval->staff ? 
                    $latestApproval->staff->fname . ' ' . $latestApproval->staff->lname : 'Unknown';
                
                // Get approval level from workflow definition
                $approvalLevel = 'Level ' . $latestApproval->approval_order;
                if ($memo->matrix && $memo->matrix->forward_workflow_id) {
                    $workflowDef = \App\Models\WorkflowDefinition::where('workflow_id', $memo->matrix->forward_workflow_id)
                        ->where('approval_order', $latestApproval->approval_order)
                        ->first();
                    if ($workflowDef) {
                        $approvalLevel = $workflowDef->role;
                    }
                }
                
                $statusText = "{$action} by {$approverName} ({$approvalLevel})";
                $badgeClass = get_approval_action_badge_class($latestApproval->action);
            } else {
                // Get current approver info for pending status
                $currentApprover = getCurrentApproverInfo($memo);
                if ($currentApprover) {
                    $statusText = "Pending - {$currentApprover['name']} ({$currentApprover['level']})";
                } else {
                    $statusText = 'Pending';
                }
                $badgeClass = 'bg-warning';
            }
        } else {
            // User is not an approver - show matrix overall status
            if ($memo->matrix && $memo->matrix->overall_status) {
                $statusText = ucwords($memo->matrix->overall_status);
                $badgeClass = get_status_badge_class($memo->matrix->overall_status);
            } else {
                $statusText = 'Pending';
                $badgeClass = 'bg-warning';
            }
        }
    } else {
        // Fallback
        $statusText = ucwords($memo->overall_status ?? 'pending');
        $badgeClass = get_status_badge_class($memo->overall_status ?? 'pending');
    }
    
    return "<span class=\"badge {$badgeClass}\">{$statusText}</span>";
}

/**
 * Get badge class for status
 */
function get_status_badge_class($status)
{
    switch (strtolower($status)) {
        case 'approved':
            return 'bg-success';
        case 'pending':
            return 'bg-warning';
        case 'returned':
            return 'bg-danger';
        case 'draft':
            return 'bg-secondary';
        case 'cancelled':
            return 'bg-dark';
        default:
            return 'bg-secondary';
    }
}

/**
 * Get badge class for approval action
 */
function get_approval_action_badge_class($action)
{
    switch (strtolower($action)) {
        case 'passed':
        case 'approved':
            return 'bg-success';
        case 'returned':
        case 'rejected':
            return 'bg-danger';
        case 'pending':
            return 'bg-warning';
        default:
            return 'bg-secondary';
    }
}

/**
 * Determine memo type automatically
 * 
 * @param mixed $memo The memo/activity object
 * @return string The memo type
 */
function get_memo_type($memo)
{
    // Check model class first
    $className = get_class($memo);
    
    switch ($className) {
        case 'App\Models\NonTravelMemo':
            return 'non_travel';
        case 'App\Models\SpecialMemo':
            return 'special';
        case 'App\Models\Activity':
            // Check if it's a single memo
            if (isset($memo->is_single_memo) && $memo->is_single_memo) {
                return 'single_memo';
            }
            return 'matrix_activity';
        default:
            // Check memo type field for other models
            if (isset($memo->memo_type)) {
                switch ($memo->memo_type) {
                    case 'non_travel':
                        return 'non_travel';
                    case 'special':
                        return 'special';
                    default:
                        return 'matrix_activity';
                }
            }
            return 'matrix_activity';
    }
}

/**
 * Get current approver information for pending status
 */
function getCurrentApproverInfo($memo)
{
    // Handle different memo types
    $forwardWorkflowId = null;
    $approvalLevel = null;
    
    if ($memo->matrix) {
        // Matrix activity
        $forwardWorkflowId = $memo->matrix->forward_workflow_id;
        $approvalLevel = $memo->matrix->approval_level;
    } elseif (isset($memo->forward_workflow_id)) {
        // Non-travel, special memo, or single memo
        $forwardWorkflowId = $memo->forward_workflow_id;
        $approvalLevel = $memo->approval_level;
    }
    
    if (!$forwardWorkflowId || !$approvalLevel) {
        return null;
    }

    // Get workflow definition for current approval level
    $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $forwardWorkflowId)
        ->where('approval_order', $approvalLevel)
        ->where('is_enabled', 1)
        ->with(['approvers.staff', 'approvers.oicStaff'])
        ->first();

    if (!$workflowDefinition) {
        return null;
    }

    // Get current approver (prefer staff, fallback to OIC)
    $currentApprover = $workflowDefinition->approvers->first();
    if (!$currentApprover) {
        return null;
    }

    $approverName = 'Unknown';
    if ($currentApprover->staff) {
        $approverName = $currentApprover->staff->fname . ' ' . $currentApprover->staff->lname;
    } elseif ($currentApprover->oicStaff) {
        $approverName = $currentApprover->oicStaff->fname . ' ' . $currentApprover->oicStaff->lname;
    }

    return [
        'name' => $approverName,
        'level' => $workflowDefinition->role
    ];
}

/**
 * Display memo status with automatic type detection
 * 
 * This is a convenience wrapper that automatically determines the memo type
 * and calls display_memo_status with the appropriate type parameter.
 * 
 * @param mixed $memo The memo/activity object
 * @return string HTML badge element
 */
function display_memo_status_auto($memo)
{
    $type = get_memo_type($memo);
    return display_memo_status($memo, $type);
}

/**
 * Get memo status text only (without HTML)
 * 
 * @param mixed $memo The memo/activity object
 * @param string $type The memo type
 * @return string Status text only
 */
function get_memo_status_text($memo, $type)
{
    $user = session('user', []);
    $staffId = $user['staff_id'] ?? null;
    
    $statusText = '';
    
    // Determine memo type based on passed parameter
    $isMatrixActivity = $type === 'matrix_activity';
    $isNonTravel = $type === 'non_travel';
    $isSpecialMemo = $type === 'special';
    $isSingleMemo = $type === 'single_memo';
    
    if ($isSingleMemo) {
        // For single memos, show the overall status
        $statusText = ucwords($memo->overall_status ?? 'pending');
        
    } elseif ($isNonTravel || $isSpecialMemo) {
        // For non-travel or special memos, show the overall status
        $statusText = ucwords($memo->overall_status ?? 'pending');
        
    } elseif ($isMatrixActivity) {
        // For matrix activities
        if ($memo->matrix && can_approve_activity($memo)) {
            // User is an approver - show their specific action with name and level
            $latestApproval = ActivityApprovalTrail::where('activity_id', $memo->id)
                ->where('matrix_id', $memo->matrix_id)
                ->where('staff_id', $staffId)
                ->with(['staff', 'workflowDefinition'])
                ->orderByDesc('id')
                ->first();
                
            if ($latestApproval && $latestApproval->action) {
                $action = ucwords($latestApproval->action);
                $approverName = $latestApproval->staff ? 
                    $latestApproval->staff->fname . ' ' . $latestApproval->staff->lname : 'Unknown';
                
                // Get approval level from workflow definition
                $approvalLevel = 'Level ' . $latestApproval->approval_order;
                if ($memo->matrix && $memo->matrix->forward_workflow_id) {
                    $workflowDef = \App\Models\WorkflowDefinition::where('workflow_id', $memo->matrix->forward_workflow_id)
                        ->where('approval_order', $latestApproval->approval_order)
                        ->first();
                    if ($workflowDef) {
                        $approvalLevel = $workflowDef->role;
                    }
                }
                
                $statusText = "{$action} by {$approverName} ({$approvalLevel})";
            } else {
                // Get current approver info for pending status
                $currentApprover = getCurrentApproverInfo($memo);
                if ($currentApprover) {
                    $statusText = "Pending - {$currentApprover['name']} ({$currentApprover['level']})";
                } else {
                    $statusText = 'Pending';
                }
            }
        } else {
            // User is not an approver - show matrix overall status
            if ($memo->matrix && $memo->matrix->overall_status) {
                $statusText = ucwords($memo->matrix->overall_status);
            } else {
                $statusText = 'Pending';
            }
        }
    } else {
        // Fallback
        $statusText = ucwords($memo->overall_status ?? 'pending');
    }
    
    return $statusText;
}

/**
 * Get memo status text with automatic type detection
 * 
 * @param mixed $memo The memo/activity object
 * @return string Status text only
 */
function get_memo_status_text_auto($memo)
{
    $type = get_memo_type($memo);
    return get_memo_status_text($memo, $type);
}
function isdivision_head($memo)
{
    $user = session('user', []);
    $staffId = $user['staff_id'] ?? null;
    return $memo->division->division_head == $staffId;
}
