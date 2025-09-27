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
            if ($matrix->overall_status == 'draft') {
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

    if (!function_exists('is_finance_officer')) {
        /**
         * Check if the current user is a finance officer for the matrix's division
         */
        function is_finance_officer($matrix) {
            $user = session('user', []);
            $currentUserId = $user['staff_id'] ?? null;
            
            if (!$currentUserId || !$matrix->division) {
                return false;
            }
            
            // Check if user is the finance officer for the matrix's division
            return $matrix->division->finance_officer == $currentUserId;
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

            return ($isOwner || $isResponsible) && $isApproved;
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
            
            // Check if this is a single memo
            $isSingleMemo = isset($memo->is_single_memo) && $memo->is_single_memo;
            
            // Must be owner or responsible person
            $isOwner = isset($memo->staff_id, $user->staff_id) && $memo->staff_id == $user->staff_id;
            $isResponsible = isset($memo->responsible_person_id, $user->staff_id) && $memo->responsible_person_id == $user->staff_id;
            
            // Check if user is authorized
            $isAuthorized = $isOwner || $isResponsible;
            if (!$isAuthorized) {
                return false;
            }
            
            // Check approval status
            $isApproved = false;
            if ($isSingleMemo) {
                // For single memos: only check the memo's own status
                $isApproved = isset($memo->overall_status) && $memo->overall_status === 'approved';
            } else {
                // For matrix activities: check both memo and matrix status
                $memoApproved = isset($memo->overall_status) && $memo->overall_status === 'approved';
                $matrixApproved = isset($memo->matrix) && isset($memo->matrix->overall_status) && $memo->matrix->overall_status === 'approved';
                $isApproved = $memoApproved || $matrixApproved;
            }
            
            if (!$isApproved) {
                return false;
            }

            // Fund type check
            $fundTypeId = isset($memo->fundType->id) ? $memo->fundType->id : null;

            $isTypeAllowed = false;
            if ($type === 'services') {
                $isTypeAllowed = $fundTypeId == 1; // intramural
            } elseif ($type === 'arf') {
                $isTypeAllowed = $fundTypeId == 2; // extramural
            }

            return $isTypeAllowed;
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

            // Check if this is a single memo (Activity model with is_single_memo = true)
            $isSingleMemo = isset($memo->is_single_memo) && $memo->is_single_memo;

            // Check if user is authorized to edit
            $isOwner = isset($memo->staff_id, $user->staff_id) && $memo->staff_id == $user->staff_id;
            $isResponsible = isset($memo->responsible_person_id, $user->staff_id) && $memo->responsible_person_id == $user->staff_id;
            
            // Check if user is focal person of the memo's division
            $isFocalperson = false;
            if (isset($memo->division_id)) {
                $division = Division::find($memo->division_id);
                if ($division && isset($division->focal_person, $user->staff_id)) {
                    $isFocalperson = $division->focal_person == $user->staff_id;
                }
            }
            
            // Check if user is division head of the memo's division
            $isDivisionHead = false;
            if (isset($memo->division_id)) {
                $divisionIds = Division::where('division_head', $user->staff_id)->pluck('id')->toArray();
                if (in_array($memo->division_id, $divisionIds)) {
                    $isDivisionHead = true; 
                }
            }

            // User must be authorized
            $isAuthorized = $isOwner || $isResponsible || $isFocalperson || $isDivisionHead;
            if (!$isAuthorized) {
                return false;
            }

            // Check memo status conditions
            $memoStatus = $memo->overall_status ?? null;
            $approvalLevel = $memo->approval_level ?? 0;

            // Always allow editing if status is 'draft' or 'returned'
            if ($memoStatus === 'draft' || $memoStatus === 'returned') {
                return true;
            }

            // For single memos: only consider the memo's own status, not matrix status
            if ($isSingleMemo) {
                // For 'pending' status: only allow editing if at level 1 (HOD level)
                if ($memoStatus === 'pending' && $approvalLevel == 1) {
                    return true;
                }

                // For 'pending' status at other levels: only allow if user is HOD and memo is returned to them
                if ($memoStatus === 'pending' && $approvalLevel > 1) {
                    // Only HOD can edit pending memos at higher levels if they are the current approver
                    return $isDivisionHead && $approvalLevel == 1;
                }

                // Don't allow editing if status is 'approved' or 'cancelled'
                if (in_array($memoStatus, ['approved', 'cancelled'])) {
                    return false;
                }

                // Default: don't allow editing
                return false;
            }

            // For matrix activities: consider both memo and matrix status
            $matrixStatus = isset($memo->matrix) ? $memo->matrix->overall_status : null;

            // Allow editing if matrix status is 'draft' or 'returned' (for matrix activities)
            if ($matrixStatus === 'draft' || $matrixStatus === 'returned') {
                return true;
            }

            // For 'pending' status: only allow editing if at level 1 (HOD level)
            if ($memoStatus === 'pending' && $approvalLevel == 1) {
                return true;
            }

            // For 'pending' status at other levels: only allow if user is HOD and memo is returned to them
            if ($memoStatus === 'pending' && $approvalLevel > 1) {
                // Only HOD can edit pending memos at higher levels if they are the current approver
                return $isDivisionHead && $approvalLevel == 1;
            }

            // Don't allow editing if status is 'approved' or 'cancelled'
            if (in_array($memoStatus, ['approved', 'cancelled'])) {
                return false;
            }

            // Default: don't allow editing
            return false;
        }
     }
     

     if (!function_exists('can_submit_for_approval')) {
        function can_submit_for_approval($memo) {
            $user = (object) session('user', []);
            $session_division_id = isset($user->division_id) ? $user->division_id : null;

            // Must be owner or responsible person
            $isOwner = isset($memo->staff_id, $user->staff_id) && $memo->staff_id == $user->staff_id;
            $isResponsible = isset($memo->responsible_person_id, $user->staff_id) && $memo->responsible_person_id == $user->staff_id;
          //  dd($);.
            // $isFocalperson = isset($memo->matrix, $memo->matrix->division->focal_person, $user->staff_id) && $memo->matrix->division->focal_person == $user->staff_id;
           // dd($isFocalperson);
            // Only allow if status is draft or returned
            $isMemoApproved = (isset($memo->overall_status) && ($memo->overall_status == 'draft' || $memo->overall_status == 'returned')) || 
                             (isset($memo->matrix, $memo->matrix->overall_status) && ($memo->matrix->overall_status == 'returned' || $memo->matrix->overall_status == 'draft'));

            // Check if user is division head of the memo's division
            $isDivisionHead = false;
            
            if (isset($memo->division_id)) {
                // Get all division IDs where the user is the division head
                $divisionIds = Division::where('division_head', $user->staff_id)->pluck('id')->toArray();

                if (in_array($memo->division_id, $divisionIds)) {
                    $isDivisionHead = true; 
                }
            }

            //dd($isOwner,$isResponsible,$isFocalperson,$isDivisionHead,$isApproved);

         return ( ($isOwner || $isResponsible || $isDivisionHead) && $isMemoApproved);
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
            
            // Check if user has approved at the current approval level
            $currentLevelApproval = ApprovalTrail::where('model_id', $matrix->id)
                ->where('model_type', 'App\Models\\' . ucfirst(class_basename($matrix)))
                ->where('approval_order', $matrix->approval_level)
                ->where('staff_id', $user['staff_id'])
                ->where('is_archived', 0)
                ->orderByDesc('id')
                ->first();
                
            return $currentLevelApproval && $currentLevelApproval->action === 'approved';
        }
    }

    if (!function_exists('matrix_has_been_returned')) {
        /**
         * Check if the matrix has been returned back to the focal person by HOD
         * This happens when the matrix status is 'returned' and approval_level is 0
         */
        function matrix_has_been_returned($matrix)
        {
            // Check if matrix status is 'returned' and approval level is 0 (back to focal person)
            if ($matrix->overall_status === 'returned' && $matrix->approval_level == 0) {
                return true;
            }
            
            // Additional check: Look for recent 'returned' action in approval trail
            $user = session('user', []);
            if (isset($user['staff_id'])) {
                $recentApproval = ApprovalTrail::where('model_id', $matrix->id)
                    ->where('model_type', 'App\Models\\' . ucfirst(class_basename($matrix)))
                    ->where('action', 'returned')
                    ->orderByDesc('id')
                    ->first();
                    
                return $recentApproval !== null;
            }
            
            return false;
        }
    }

    if (!function_exists('archive_approval_trails')) {
        /**
         * Archive approval trails when a matrix or memo is returned to restart approval process
         * For matrices: Only archive when approval_order = 0 (draft/returned state)
         * For memos: Archive when returned (any approval level)
         */
        function archive_approval_trails($model)
        {
            try {
                $modelType = get_class($model);
                $modelId = $model->id;
                
                // For matrices, only archive when approval_order = 0 (draft/returned state)
                if ($modelType === 'App\Models\Matrix') {
                    // Only archive if matrix is at approval_order 0 (draft or returned state)
                    if ($model->approval_level != 0) {
                        \Log::info("Skipping archiving for matrix - not at approval_order 0", [
                            'matrix_id' => $modelId,
                            'approval_level' => $model->approval_level,
                            'overall_status' => $model->overall_status
                        ]);
                        return 0;
                    }
                    
                    // Archive approval trails for the matrix
                    $archivedCount = ApprovalTrail::where('model_id', $modelId)
                        ->where('model_type', $modelType)
                        ->where('is_archived', 0)
                        ->update(['is_archived' => 1]);
                    
                    // Also archive activity approval trails
                    $activityArchivedCount = ActivityApprovalTrail::where('matrix_id', $modelId)
                        ->where('is_archived', 0)
                        ->update(['is_archived' => 1]);
                    
                    \Log::info("Archived approval trails for matrix return to draft/returned state", [
                        'matrix_id' => $modelId,
                        'approval_level' => $model->approval_level,
                        'overall_status' => $model->overall_status,
                        'approval_trails_archived' => $archivedCount,
                        'activity_approval_trails_archived' => $activityArchivedCount
                    ]);
                    
                    return $archivedCount;
                } else {
                    // For memos, archive when returned (any approval level)
                    $archivedCount = ApprovalTrail::where('model_id', $modelId)
                        ->where('model_type', $modelType)
                        ->where('is_archived', 0)
                        ->update(['is_archived' => 1]);
                    
                    \Log::info("Archived approval trails for memo return", [
                        'model_type' => $modelType,
                        'model_id' => $modelId,
                        'approval_level' => $model->approval_level ?? 'N/A',
                        'overall_status' => $model->overall_status ?? 'N/A',
                        'approval_trails_archived' => $archivedCount
                    ]);
                    
                    return $archivedCount;
                }
                
            } catch (\Exception $e) {
                \Log::error("Failed to archive approval trails", [
                    'model_type' => $modelType ?? 'unknown',
                    'model_id' => $modelId ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                return 0;
            }
        }
    }

    if (!function_exists('can_approve_activity')) {

        
        function can_approve_activity($activity){

            if ($activity->is_single_memo==1)
               return false;
          
            if($activity->matrix->forward_workflow_id==null)
                return false;
          
            // if($activity->fund_type_id==3)
            //     return true;

            if(!$activity->matrix->workflow_definition->allowed_funders||empty($activity->matrix->workflow_definition->allowed_funders))
                return true;

            // Check if activity has budget data before accessing it
            if(empty($activity->activity_budget) || !isset($activity->activity_budget[0]) || !$activity->activity_budget[0]->fundcode)
                return true;

            return  in_array($activity->activity_budget[0]->fundcode->fund_type_id, $activity->matrix->workflow_definition->allowed_funders ?? []);
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
                ->where('is_archived', 0) // Only consider non-archived trails
                ->orderByDesc('id')
                ->first();

            return isset($latest_approval->action);
        }

    }



    if (!function_exists('has_user_returned_activity_as_single_memo')) {
        /**
         * Check if a user has returned any activity as a single memo in a matrix
         * This helps determine if the user can proceed even if their approvable stack is empty
         * 
         * @param object $matrix The matrix to check
         * @param int|null $userId Optional user ID, defaults to current user
         * @return bool
         */
        function has_user_returned_activity_as_single_memo($matrix, $userId = null) {
            $userId = $userId ?? user_session('staff_id');
            
            if (!$userId) {
                return false;
            }
            
            // Check if user has any 'convert_to_single_memo' actions in activity approval trails for this matrix
            return ActivityApprovalTrail::where('matrix_id', $matrix->id)
                ->where('staff_id', $userId)
                ->where('action', 'convert_to_single_memo')
                ->where('is_archived', 0) // Only consider non-archived trails
                ->exists();
        }
    }

    if (!function_exists('can_user_proceed_with_empty_approvable_stack')) {
        /**
         * Check if a user can proceed even when their approvable stack is empty
         * This considers if the user has returned any activities as single memos
         * 
         * @param object $matrix The matrix to check
         * @param int|null $userId Optional user ID, defaults to current user
         * @return bool
         */
        function can_user_proceed_with_empty_approvable_stack($matrix, $userId = null) {
            $userId = $userId ?? user_session('staff_id');
            
            if (!$userId) {
                return false;
            }
        
            // Check if user has returned any activities as single memos
            $hasReturnedActivities = has_user_returned_activity_as_single_memo($matrix, $userId);
            
            // User can proceed if they have converted activities to single memos and their approvable stack is empty
            return $hasReturnedActivities && get_approvable_activities($matrix)->count() == 0;
        }
    }

    if (!function_exists('get_approvable_activities')) {
        function get_approvable_activities($matrix){
            
            $approvable_activities = collect();
            $currentUserId = user_session('staff_id');
            
            // Simple cache key for this matrix and user
            $cacheKey = "approvable_activities_{$matrix->id}_{$currentUserId}_{$matrix->approval_level}";
            
            // Check cache first (cache for 5 minutes)
            if (\Cache::has($cacheKey)) {
                return \Cache::get($cacheKey);
            }
            
            // Get the current user's workflow definition for this matrix
            $userWorkflowDefinition = null;
            
            // Check if user is logged in
            if (!$currentUserId) {
                return $approvable_activities; // Return empty collection if not logged in
            }
            
            // Get workflow definition based on matrix's current approval level
            $currentApprovalLevel = $matrix->approval_level;
        //    / dd($currentApprovalLevel);
            
            // Find workflow definition for current approval level
            $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
                ->where('approval_order', $currentApprovalLevel)
                ->first();
            //dd($workflowDefinition);
            if (!$workflowDefinition) {
                return $approvable_activities; // Return empty if no workflow definition found
            }
           //dd($workflowDefinition);
            //dd($currentUserId);
            // Check if user is an approver for this workflow definition
            $isApprover = \App\Models\Approver::where('workflow_dfn_id', $workflowDefinition->id)
                ->where('staff_id', $currentUserId)
                ->where(function($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                })
                ->exists();

        // getFullSql(($isApprover));

           // dd($isApprover);
                
            // Check if user is an OIC approver
            $isOicApprover = \App\Models\Approver::where('workflow_dfn_id', $workflowDefinition->id)
                ->where('oic_staff_id', $currentUserId)
                ->where(function($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                })
                ->exists();
           //dd($isOicApprover);
            // Check for division-level approvers (Finance Officer, Head of Division, Director)
            $isDivisionLevelApprover = false;
            
            // Get matrix's division (not user's division)
            $matrixDivision = $matrix->division;
            
            if ($matrixDivision) {
                // Check if user is Finance Officer for this matrix's division
                $isFinanceOfficer = $matrixDivision->finance_officer == $currentUserId;
                
                // Check if user is Head of Division for this matrix's division
                $isHeadOfDivision = $matrixDivision->division_head == $currentUserId;
                
                // Check if user is Finance Officer OIC for this matrix's division
                $isFinanceOfficerOic = $matrixDivision->finance_officer_oic_id == $currentUserId;
                
                // Check if user is Director for this matrix's division
                $isDirector = $matrixDivision->director_id == $currentUserId;
                
                // Check if user is Director OIC for this matrix's division
                $isDirectorOic = $matrixDivision->director_oic_id == $currentUserId;
                
                // Check if user is Head OIC for this matrix's division
                $isHeadOic = $matrixDivision->head_oic_id == $currentUserId;
                
                $isDivisionLevelApprover = $isFinanceOfficer || $isHeadOfDivision || $isFinanceOfficerOic || $isDirector || $isDirectorOic || $isHeadOic;
            }
            
            
            // Additional check: Check if user is an OIC for any approver in the current workflow definition
            // This covers cases where someone is an OIC but not specifically assigned to division fields
            $isWorkflowOic = \App\Models\Approver::where('workflow_dfn_id', $workflowDefinition->id)
                ->where('oic_staff_id', $currentUserId)
                ->where(function($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                })
                ->exists();
            
            // If user is not an approver for this level, return empty collection
            if (!$isApprover && !$isOicApprover && !$isDivisionLevelApprover && !$isWorkflowOic) {
               // dd($isApprover,$isOicApprover);
                return $approvable_activities;
            }
            
            // Filter activities based on allowed_funders if specified
            foreach($matrix->activities as $activity) {
                $canApprove = true;
                
                // Check if activity has budget data and allowed_funders is specified
                if ($workflowDefinition->allowed_funders && !empty($workflowDefinition->allowed_funders)) {
                    // Check if activity has budget data
                    if (empty($activity->activity_budget) || !isset($activity->activity_budget[0]) || !$activity->activity_budget[0]->fundcode) {
                        // For external source activities (no budget data), only allow if fund type 3 is in allowed_funders
                        $canApprove = in_array(3, $workflowDefinition->allowed_funders);
                    } else {
                        // Check if activity's fund type is in allowed_funders
                        $activityFundTypeId = $activity->activity_budget[0]->fundcode->fund_type_id;
                        $canApprove = in_array($activityFundTypeId, $workflowDefinition->allowed_funders);
                    }
                }
                
                // Additional check using existing can_approve_activity function
                if ($canApprove) {
                    $canApprove = can_approve_activity($activity);
                }
                
                if ($canApprove) {
                    $approvable_activities->push($activity);
                }
            }
            
            // Cache the result for 5 minutes
            \Cache::put($cacheKey, $approvable_activities, 300);
            //dd($approvable_activities);
            return $approvable_activities;
        }
    }

    if (!function_exists('activities_approved_by_me')) {
        function activities_approved_by_me($matrix){
            $user = session('user', []);
            
            // Get all activities that the user can approve (based on can_approve_activity logic)
            $approvable_activities = get_approvable_activities($matrix);
            //dd($approvable_activities);
            $has_approved = false;

            // If no approvable activities exist, return false
            if ($approvable_activities->isEmpty())
                 $has_approved=false;
            if(can_user_proceed_with_empty_approvable_stack($matrix))
                 $has_approved=true;

            // For each approvable activity, check if user has at least one 'passed' approval
            foreach ($approvable_activities as $activity) {
                $has_approved = ActivityApprovalTrail::where("staff_id", $user['staff_id'])
                    ->where("matrix_id", $matrix->id)
                    ->where("approval_order", $matrix->approval_level)
                    ->where("activity_id", $activity->id)
                    ->where("action", "passed")
                    ->where("is_archived", 0) // Only consider non-archived trails
                    ->exists();

                if(!$has_approved)
                break;
            }

           // dd($has_approved);

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
                // User is in approvers table, but check if this is a division-specific role
                $current_approval_point = $current_approval_point->first();
                
                if ($current_approval_point && $current_approval_point->is_division_specific) {
                    // For division-specific roles, only allow the actual division person
                    $division = $matrix->division;
                    if ($division && $division->{$current_approval_point->division_reference_column} == user_session()['staff_id']) {
                        $division_specific_access = true;
                    } else {
                        // User is in approvers table but not the actual division person
                        return false;
                    }
                } else {
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
            ->where('is_archived', 0) // Only consider non-archived trails
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
        // For single memos, show the overall status or current actor if not approved
        $overallStatus = $memo->overall_status ?? 'pending';
        if ($overallStatus !== 'approved') {
            $currentApprover = getCurrentApproverInfo($memo);
            if ($currentApprover) {
                $statusText = "Pending - {$currentApprover['name']} ({$currentApprover['level']})";
                $badgeClass = 'bg-warning';
            } else {
                $statusText = ucwords($overallStatus);
                $badgeClass = get_status_badge_class($overallStatus);
            }
        } else {
            $statusText = ucwords($overallStatus);
            $badgeClass = get_status_badge_class($overallStatus);
        }
        
    } elseif ($isNonTravel || $isSpecialMemo) {
        // For non-travel or special memos, show the overall status or current actor if not approved
        $overallStatus = $memo->overall_status ?? 'pending';
        if ($overallStatus !== 'approved') {
            $currentApprover = getCurrentApproverInfo($memo);
            if ($currentApprover) {
                $statusText = "Pending - {$currentApprover['name']} ({$currentApprover['level']})";
                $badgeClass = 'bg-warning';
            } else {
                $statusText = ucwords($overallStatus);
                $badgeClass = get_status_badge_class($overallStatus);
            }
        } else {
            $statusText = ucwords($overallStatus);
            $badgeClass = get_status_badge_class($overallStatus);
        }
        
    } elseif ($isMatrixActivity) {
        // For matrix activities
        // Check if matrix is approved - if so, show activity's overall_status instead of trail status
        if ($memo->matrix && $memo->matrix->overall_status === 'approved') {
            // When matrix is approved, show the activity's overall_status
            $statusText = ucwords($memo->overall_status ?? 'pending');
            $badgeClass = get_status_badge_class($memo->overall_status ?? 'pending');
        } elseif ($memo->matrix && can_approve_activity($memo)) {
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
    
    // Check if this is a single memo (Activity with its own approval workflow)
    if (get_class($memo) === 'App\Models\Activity') {
        // Single memo - always use its own properties, ignore matrix relation
        $forwardWorkflowId = $memo->forward_workflow_id ?? null;
        $approvalLevel = $memo->approval_level ?? null;
    } elseif ($memo->matrix) {
        // Matrix activity
        $forwardWorkflowId = $memo->matrix->forward_workflow_id;
        $approvalLevel = $memo->matrix->approval_level;
    } else {
        // Non-travel, special memo
        $forwardWorkflowId = $memo->forward_workflow_id ?? null;
        $approvalLevel = $memo->approval_level ?? null;
    }
    
    if (!$forwardWorkflowId || !$approvalLevel) {
        return null;
    }

    // Get workflow definition for current approval level
    // If there are multiple definitions at the same level (like level 7), use category-based routing
    $workflowDefinition = null;
    
    // Check if there are multiple definitions at this level
    $definitionsAtLevel = \App\Models\WorkflowDefinition::where('workflow_id', $forwardWorkflowId)
        ->where('approval_order', $approvalLevel)
        ->where('is_enabled', 1)
        ->count();
    
    if ($definitionsAtLevel > 1) {
        // Multiple definitions at this level - use category-based routing
        $division = null;
        if ($memo->matrix) {
            $division = $memo->matrix->division;
        } elseif (isset($memo->division)) {
            $division = $memo->division;
        }
        
        if ($division && $division->category) {
            // Find the definition that matches the division category
            $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $forwardWorkflowId)
                ->where('approval_order', $approvalLevel)
                ->where('is_enabled', 1)
                ->where('category', $division->category)
                ->with(['approvers.staff', 'approvers.oicStaff'])
                ->first();
            
            // If no definition found at current level for this category, 
            // look for the next available level for this category
            if (!$workflowDefinition) {
                $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $forwardWorkflowId)
                    ->where('approval_order', '>', $approvalLevel)
                    ->where('is_enabled', 1)
                    ->where('category', $division->category)
                    ->with(['approvers.staff', 'approvers.oicStaff'])
                    ->orderBy('approval_order', 'asc')
                    ->first();
            }
        }
    }
    
    // If no category-specific definition found, use the first available one
    if (!$workflowDefinition) {
        $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $forwardWorkflowId)
            ->where('approval_order', $approvalLevel)
            ->where('is_enabled', 1)
            ->with(['approvers.staff', 'approvers.oicStaff'])
            ->first();
    }

    if (!$workflowDefinition) {
        return null;
    }

    $approverName = 'Unknown';
    
    // Check if this is a division-specific workflow definition
    if ($workflowDefinition->is_division_specific) {
        // For division-specific approvers, get from divisions table
        $division = null;
        if ($memo->matrix) {
            $division = $memo->matrix->division;
        } elseif (isset($memo->division)) {
            $division = $memo->division;
        }
        
        if ($division) {
            // Map workflow roles to division fields
            $roleToFieldMap = [
                'Finance Officer' => 'finance_officer',
                'Head of Division' => 'division_head',
                'Director' => 'director_id'
            ];
            
            $approverStaffId = null;
            $roleName = $workflowDefinition->role;
            
            if (isset($roleToFieldMap[$roleName])) {
                $fieldName = $roleToFieldMap[$roleName];
                $approverStaffId = $division->{$fieldName};
            }
            
            // Get approver staff details
            if ($approverStaffId) {
                $approverStaff = \App\Models\Staff::find($approverStaffId);
                if ($approverStaff) {
                    $approverName = $approverStaff->fname . ' ' . $approverStaff->lname;
                }
            }
        }
    } else {
        // For non-division-specific approvers, get from approvers table
        $currentApprover = $workflowDefinition->approvers->first();
        
        if ($currentApprover) {
            if ($currentApprover->staff) {
                $approverName = $currentApprover->staff->fname . ' ' . $currentApprover->staff->lname;
            } elseif ($currentApprover->oicStaff) {
                $approverName = $currentApprover->oicStaff->fname . ' ' . $currentApprover->oicStaff->lname;
            }
        }
    }

    // Return null if no approver found
    if ($approverName === 'Unknown') {
        return null;
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
        // Check if matrix is approved - if so, show activity's overall_status instead of trail status
        if ($memo->matrix && $memo->matrix->overall_status === 'approved') {
            // When matrix is approved, show the activity's overall_status
            $statusText = ucwords($memo->overall_status ?? 'pending');
        } elseif ($memo->matrix && can_approve_activity($memo)) {
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
