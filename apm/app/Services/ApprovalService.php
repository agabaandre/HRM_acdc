<?php

namespace App\Services;

use App\Models\ApprovalTrail;
use App\Models\WorkflowDefinition;
use App\Models\Approver;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
   
    
    public function canTakeAction(Model $model, int $userId):bool
    {
           $user = session('user', []);
           

           //dd($user);
           //dd(done_approving($model));
          // dd($this->hasUserApproved($model, $user['staff_id']));

          if (empty($user['staff_id']) || $this->hasUserApproved($model, $user['staff_id']) || in_array($model->overall_status,['approved','draft'])) {
              return false;
          }

          // Allow HODs to take action when memo is returned to them
          if ($model->overall_status === 'returned' && $this->isHOD($model, $userId)) {
              return true;
          }

          // For other cases with 'returned' status, don't allow action
          if ($model->overall_status === 'returned') {
              return false;
          }

           $still_with_creator = $this->isWithCreator($model);
           //dd($still_with_creator);

           if($still_with_creator || !$model->forward_workflow_id)
           return false;
          

           $today = Carbon::today();

           //Check that matrix is at users approval level by getting approver for that staff, at the level of approval the matrix is at
           $current_approval_point = WorkflowDefinition::where('approval_order', $model->approval_level)
           ->where('workflow_id',$model->forward_workflow_id);

         
           $workflow_dfns = Approver::where('staff_id',"=", $user['staff_id'])
           ->whereIn('workflow_dfn_id',$current_approval_point->pluck('id'))
           ->orWhere(function ($query) use ($today, $user,$current_approval_point) {
                   $query ->whereIn('workflow_dfn_id',$current_approval_point->pluck('id'))
                   ->where('oic_staff_id', "=", $user['staff_id'])
                   ->where('end_date', '>=', $today);
               })
           ->orderBy('id','desc')
           ->pluck('workflow_dfn_id');

           //dd($workflow_dfns);

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
                   $division = $model->division;
                 
                   //staff holds current approval role in division
                   if ($division && $division->{$current_approval_point->division_reference_column} == user_session()['staff_id']) {
                       $division_specific_access = true;
                   }
               }

               //how to check approval levels against approver in approvers table???
               
           }else{
               // dd("here2");
                //dd($current_approval_point);
              // $current_approval_point = $current_approval_point->where('approval_order',$workflow_dfns[0])->first();
               $current_approval_point = $current_approval_point->where('id',$workflow_dfns[0])->first();
              // dd(getFullSql(($current_approval_point)));
              //dd($current_approval_point);
               $next_definition = WorkflowDefinition::whereIn('workflow_id', $workflow_dfns->toArray())
               ->where('approval_order',(int) $model->approval_level)
               ->where('is_enabled',1)
               ->orderBy('approval_order')
               ->get();


               if ($next_definition->count() > 1) {

                   //if any of next_definition has fund_type, then do the if below
                   $has_fund_type = $next_definition->whereNotNull('fund_type')->count() > 0;
                   
                   if ($has_fund_type) {
                       if ($model->has_extramural && $model->approval_level !== $current_approval_point->approval_order) {
                           $current_approval_point = $next_definition->where('fund_type', 2)->first();
                       } else {
                           $current_approval_point = $next_definition->where('fund_type', 1)->first();
                       }
                   }else{

                       $has_category = $next_definition->whereNotNull('category')->count() > 0;

                       if($has_category){
                           $current_approval_point = $next_definition->where('category', $model->division->category)->first();
                       }else{
                           $current_approval_point = $next_definition->first();
                       }

                   }
               }

               $is_at_my_approval_level = ($current_approval_point) ? 
                   ($current_approval_point->workflow_id === $model->forward_workflow_id && $model->approval_level == $current_approval_point->approval_order) : 
                   false;
           }      

          /**TODO
           * Factor in approval conditions 
           */

           return ( ($is_at_my_approval_level || $still_with_creator || $division_specific_access) && $model->overall_status !== 'approved');
       }
       
   

    /**
     * Check if a user has already approved a model.
     */

    public function hasUserApproved(Model $model, int $userId): bool
    {
        // this maps to done apparoving
        //dd($this->isWithCreator($model));
        // if($this->isWithCreator($model) && $model->forward_workflow_id==null)
        //     return false;
        $approval = ApprovalTrail::where('model_id', $model->id)
           ->select(DB::raw('MAX(id) as id'))
            ->where('model_type', get_class($model))
            //->where('action', 'approved')
            ->where('approval_order', $model->approval_level)
            ->where('staff_id', $userId)
            ->where('is_archived', 0) // Only consider non-archived trails
            ->first();
      //  dd($approval);


        return $approval !== null && $approval->action === 'approved';
    }

    /**
     * Get the notification recipient for a model.
     */
    public function getNotificationRecipient(Model $model)
    {
        if ($model->overall_status === 'approved') {
            return null;
        }

        $today = Carbon::today();
        $current_approval_point = WorkflowDefinition::where('approval_order', $model->approval_level)
            ->where('workflow_id', $model->forward_workflow_id)
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
        if ($current_approval_point->is_division_specific && method_exists($model, 'division') && $model->division) {
            $division = $model->division;
            $staff_id = $division->{$current_approval_point->division_reference_column};
            if ($staff_id) {
                return Staff::where('staff_id', $staff_id)->first();
            }
        }

        return null;
    }

    /**
     * Process approval action for any model.
     */
    public function processApproval(Model $model, string $action, ?string $comment = null, ?int $userId = null, ?array $additionalData = null): void
    {
        $userId = $userId ?? user_session('staff_id');
       // dd($model->approval_level,$model->forward_workflow_id);

        // Save approval trail
        $trail = new ApprovalTrail();
        $trail->model_id = $model->id;
        $trail->model_type = get_class($model);
        $trail->remarks = $comment ?? '';
        $trail->forward_workflow_id = $model->forward_workflow_id;
        $trail->action = $action;
        $trail->approval_order = $model->approval_level ?? 1;
        $trail->staff_id = $userId;
        $trail->is_archived = 0; // Explicitly set as non-archived

        // For activities, also save matrix_id
        if (method_exists($model, 'matrix_id') && $model->matrix_id) {
            $trail->matrix_id = $model->matrix_id;
        }

        $trail->save();

        // Update model status
        if ($action === 'cancelled') {
            // Cancelled action - only HOD can cancel
            $model->forward_workflow_id = NULL;
            $model->approval_level = 0;
            $model->overall_status = 'cancelled';
            
            // Archive approval trails to restart approval process
            archive_approval_trails($model);
        } elseif ($action !== 'approved') {
            // Check if HOD (level 1) is returning - if so, go to level 0 (focal person)
            if ($model->approval_level == 1) {
                // HOD returning: go to level 0 (focal person/creator)
                $model->forward_workflow_id = NULL;
                $model->approval_level = 0;
                $model->overall_status = 'draft';
            } else {
                // Other approvers returning: go to level 1 (HOD)
                $model->forward_workflow_id = NULL;
                $model->approval_level = 1;
                $model->overall_status = 'returned';
            }
            
            // Archive approval trails to restart approval process
            archive_approval_trails($model);
        } else {
            $next_approver = $this->getNextApprover($model);
            //dd($next_approver);

            if ($next_approver) {
                $model->forward_workflow_id = $next_approver->workflow_id;
                $model->approval_level = $next_approver->approval_order;
                $model->next_approval_level = $next_approver->approval_order;
                $model->overall_status = 'pending';
                
                // If this is a matrix, update all activities' overall_status to 'pending'
                if (get_class($model) === 'App\Models\Matrix') {
                    $model->activities()->where('is_single_memo', 0)->update(['overall_status' => 'pending']);
                }
            } else {
                $model->overall_status = 'approved';
                
                // If this is a matrix, update all activities' overall_status to 'approved'
                if (get_class($model) === 'App\Models\Matrix') {
                    $model->activities()->where('is_single_memo', 0)->update(['overall_status' => 'approved']);
                }
            }
        }

        // Handle additional data (like available_budget)
        if ($additionalData && is_array($additionalData)) {
            foreach ($additionalData as $key => $value) {
                if (in_array($key, $model->getFillable()) && $value !== null) {
                    $model->$key = $value;
                }
            }
        }

        $model->update();
    }

   


//     public function getNextApprover($model){

//         $division   = $model->division;
//         //dd($division);

//     $current_definition = WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
//        ->where('is_enabled',1)
//        ->where('approval_order',$model->approval_level)
//        ->first();
//       //dd($current_definition);

//     // Check if model has extramural/intramural properties (for matrices/activities)
//     $has_extramural = property_exists($model, 'has_extramural') ? $model->has_extramural : false;
//     $has_intramural = property_exists($model, 'has_intramural') ? $model->has_intramural : true; // Default to true for service requests

//     $go_to_category_check_for_external = (!$has_extramural && !$has_intramural && ($model->approval_level!=null && $current_definition->approval_order > $model->approval_level));
// //dd($go_to_category_check_for_external);
//     //if it's time to trigger categroy check, just check and continue
//     if(($current_definition && $current_definition->triggers_category_check) || $go_to_category_check_for_external){

//         $category_definition = WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
//                     ->where('is_enabled',1)
//                     ->where('category',$division->category)
//                     ->orderBy('approval_order','asc')
//                     ->first();

//         return $category_definition;
//     }
//    // dd($current_definition);

//     $nextStepIncrement = 1;

//     //Skip Directorate from HOD if no directorate
//     if($model->forward_workflow_id==1 && $current_definition->approval_order==1 && !$division->director_id)
//         $nextStepIncrement = 2;
//     else if($model->forward_workflow_id>0 && $current_definition->approval_order==1){
//         $nextStepIncrement = 1;
//     }

//      if(!$model->forward_workflow_id)// null
//         $model->forward_workflow_id = 1;

//     //dd($model->forward_workflow_id);
//     $next_definition = WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
//        ->where('is_enabled',1)
//        ->where('approval_order',$model->approval_level +$nextStepIncrement)->get();
//     //dd($next_definition);
        
//     //if matrix has_extramural is true and matrix->approval_level !==definition_approval_order, 
//     // get from $definition where fund_type=2, else where fund_type=2
//     //if one, just return the one available
//     if ($next_definition->count() > 1) {

//         if ($has_extramural && $model->approval_level !== $next_definition->first()->approval_order) {
//             return $next_definition->where('fund_type', 2);
//         } 
//         else {
//             return $next_definition->where('fund_type', 1);
//         }
//     }

//     $definition = ($next_definition->count()>0)?$next_definition[0]:null;
//     //dd($definition);
//     //intramural only, skip extra mural role
//     if($definition  && !$has_extramural &&  $definition->fund_type==2){
//       return WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
//         ->where('is_enabled',1)
//         ->where('approval_order',$definition->approval_order+1)->first();
//     }

//     //only extramural, skip by intramural roles
//     if($definition  && !$has_intramural &&  $definition->fund_type==1){
//         return WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
//           ->where('is_enabled',1)
//           ->where('approval_order', $definition->approval_order+2)->first();
//     }
// //dd($definition);
   
//    return $definition;
//     }


public function getNextApprover($model)  
{
    // Input validation
    if (!$model || !$model->division) {
        Log::error('Invalid model or division in getNextApprover');
        return null;
    }

    $division = $model->division;
    $approvalLevel = (int) ($model->approval_level ?? 0);
    
    // Determine funding types based on model properties
    // Note: These values can change dynamically as activities are processed
    $hasIntra = (bool) ($model->has_intramural ?? false);
    //dd($hasIntra);
    $hasExtra = (bool) ($model->has_extramural ?? false);
    $isExternal = (!$hasIntra && !$hasExtra); // External source if neither intra nor extra
    
    // Dynamic funding status check: Re-evaluate funding status at each level
    // This handles cases where activities are removed during the workflow
    if ($approvalLevel >= 3) {
        // Refresh the matrix to get the latest funding status
        $model->refresh();
        
        // Recalculate funding status to detect changes
        $currentHasIntra = (bool) ($model->has_intramural ?? false);
        $currentHasExtra = (bool) ($model->has_extramural ?? false);
        $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
        
        // If funding status changed to external, update the flags
        if ($currentIsExternal && !$isExternal) {
            $isExternal = true;
            $hasIntra = false;
            $hasExtra = false;
            
            // Log the change for debugging
            Log::info("Funding status changed to external at level {$approvalLevel} for model {$model->id}", [
                'previous_status' => 'had_funding',
                'current_status' => 'external',
                'approval_level' => $approvalLevel
            ]);
        }
        
        // If funding status changed from external to having funding, update the flags
        if (!$currentIsExternal && $isExternal) {
            $isExternal = false;
            $hasIntra = $currentHasIntra;
            $hasExtra = $currentHasExtra;
            
            // Log the change for debugging
            Log::info("Funding status changed from external at level {$approvalLevel} for model {$model->id}", [
                'previous_status' => 'external',
                'current_status' => 'has_funding',
                'has_intramural' => $hasIntra,
                'has_extramural' => $hasExtra,
                'approval_level' => $approvalLevel
            ]);
        }
    }
    
    // Debug logging (commented out due to permission issues)
    // Log::info("Model {$model->id} workflow check", [
    //     'approval_level' => $approvalLevel,
    //     'has_intramural' => $hasIntra,
    //     'has_extramural' => $hasExtra,
    //     'is_external' => $isExternal,
    //     'division_category' => $division->category ?? 'null',
    //     'division_director_id' => $division->director_id ?? 'null'
    // ]);
    
    // Debug output for testing (remove in production)
    // echo "DEBUG: Model {$model->id} - Level: {$approvalLevel}, HasIntra: " . ($hasIntra ? 'true' : 'false') . ", HasExtra: " . ($hasExtra ? 'true' : 'false') . ", IsExternal: " . ($isExternal ? 'true' : 'false') . ", Category: " . ($division->category ?? 'null') . PHP_EOL;

    // Ensure workflow ID is set - get from WorkflowModel or use default
    if (!$model->forward_workflow_id) {
        $modelClass = get_class($model);
        $modelName = class_basename($modelClass); // Extract just the model name (e.g., "SpecialMemo")
        $assignedWorkflowId = \App\Models\WorkflowModel::getWorkflowIdForModel($modelName);
        if (!$assignedWorkflowId) {
            $assignedWorkflowId = 1;
            Log::warning('No workflow assignment found for ' . $modelClass . '; using default workflow_id=1');
        }
        $model->forward_workflow_id = $assignedWorkflowId;
    }

    // Helper function to get workflow definition by order, fund type, and category
    $pick = function (int $order, ?int $fundType = null, ?string $category = null) use ($model) {
        $query = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where('approval_order', $order);

        if ($fundType !== null) $query->where('fund_type', $fundType); // 1=intramural, 2=extramural, 3=external
        if ($category !== null) $query->where('category', $category);

        return $query->first();
    };

    // Helper function to get first category-based approver
            $pickFirstCategoryNode = function (?string $category) use ($model, $pick, $approvalLevel) {
        $cat = $category ?: 'Other';
        
        // Simple and elegant: Find workflow definition that matches the division category
        // This approach is scalable and doesn't require hardcoded logic for each category
        $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where('category', $cat)
            ->where('approval_order', '>', $approvalLevel) // Only look for next level, not current
            ->orderBy('approval_order', 'asc')
            ->first();
        
        // If no category-specific approver found, find the next available approval order
        if (!$definition) {
            $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where('approval_order', '>', $approvalLevel)
                ->orderBy('approval_order', 'asc')
                ->first();
        }
        
        return $definition;
    };

    // Get current workflow definition
    $current_definition = $approvalLevel > 0
        ? WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where('approval_order', $approvalLevel)
            ->first()
        : null;

    // STEP 1: HOD Review Logic
    // If at HOD level (approval_order = 1), check if we should skip directorate
    if ($approvalLevel == 1) {
        // Special case: If division category is 'Other', go directly to DDG who doubles as Head of Other (order 9)
        // This takes priority over all funding type checks
        if ($division->category === 'Other') {
            $definition = $pickFirstCategoryNode($division->category);
            if ($definition) return $definition;
        }
        
        // For external source, first check if division has director
        if ($isExternal) {
            // Check if division has directorate (null or 0 means no director)
            if ($division->director_id == null || $division->director_id == 0) {
                // No director - go directly to division category check
                $definition = $pickFirstCategoryNode($division->category ?? null);
                if ($definition) return $definition;
            } else {
                // Has director - proceed to Director step (order 2)
                $directorStep = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                    ->where('is_enabled', 1)
                    ->where('approval_order', 2)
                    ->first();
                    
                if ($directorStep) {
                    return $directorStep;
                } else {
                    // No Director step in workflow - go to division category check
                    $definition = $pickFirstCategoryNode($division->category ?? null);
                    if ($definition) return $definition;
                    
                    // If no category-specific approver found, go to next available step
                    $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                        ->where('is_enabled', 1)
                        ->where('approval_order', '>', $approvalLevel)
                        ->orderBy('approval_order', 'asc')
                        ->first();
                    if ($definition) return $definition;
                    
                    // If no next step found due to activity changes, check division category again
                    $definition = $pickFirstCategoryNode($division->category ?? null);
                    if ($definition) return $definition;
                }
            }
        }
        
        // For non-external sources, check if division has directorate (null or 0 means no director)
        if ($division->director_id == null || $division->director_id == 0) {
            // No directorate - skip to next available step after Director (order 2)
            // But first check fund types to route correctly
            if ($hasIntra && !$hasExtra) {
                // Intramural: skip Director, go to PIU Officer (4)
                $definition = $pick(4, 1);
                if ($definition) return $definition;
            }
            
            if ($hasExtra && !$hasIntra) {
                // Extramural: skip Director, go to Grants Officer (3)
                $definition = $pick(3, 2);
                if ($definition) return $definition;
            }
            
            if ($hasIntra && $hasExtra) {
                // Mixed funding: skip Director, start with Grants Officer (3)
                $definition = $pick(3, 2);
                if ($definition) return $definition;
            }
            
            // Fallback - go to next available step after Director
            $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where('approval_order', '>', 2) // Skip Director step (order 2)
                ->orderBy('approval_order', 'asc')
                ->first();
            if ($definition) return $definition;
            
            // If no next step found due to activity changes, check division category
            $definition = $pickFirstCategoryNode($division->category ?? null);
            if ($definition) return $definition;
        }
        
        // Has directorate - check if there's a Director step (order 2)
        $directorStep = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where('approval_order', 2)
            ->first();
            
        if ($directorStep) {
            // This workflow has a Director step - proceed to it
            return $directorStep;
        } else {
            // This workflow doesn't have a Director step - go to next available step
            $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where('approval_order', '>', $approvalLevel)
                ->orderBy('approval_order', 'asc')
                ->first();
            if ($definition) return $definition;
            
            // If no next step found due to activity changes, check division category
            $definition = $pickFirstCategoryNode($division->category ?? null);
            if ($definition) return $definition;
        }
    }

    // STEP 2: Directorate Check
    // If at Director level (approval_order = 2), perform all funding type checks like HOD level
    if ($approvalLevel == 2) {
        // Perform the same funding type checks as HOD level, but without director existence check
        
        // Debug output (remove in production)
        // echo "DEBUG Director Level: hasIntra=" . ($hasIntra ? 'true' : 'false') . ", hasExtra=" . ($hasExtra ? 'true' : 'false') . ", isExternal=" . ($isExternal ? 'true' : 'false') . PHP_EOL;
        
        // For external source, go directly to division category check
        if ($isExternal) {
            $definition = $pickFirstCategoryNode($division->category ?? null);
            if ($definition) return $definition;
        }
        
        // For intramural only
        if ($hasIntra && !$hasExtra) {
            // Intramural: PIU Officer (4) -> Finance Officer (5) -> Director Finance (6)
            $definition = $pick(4, 1); // PIU Officer for intramural
            if ($definition) return $definition;
        }

        // For extramural only
        if ($hasExtra && !$hasIntra) {
            // Extramural: Grants Officer (3) -> Director Finance (6) (skips Finance Officer)
            $definition = $pick(3, 2); // Grants Officer for extramural
            if ($definition) return $definition;
        }

        // For mixed funding
        if ($hasIntra && $hasExtra) {
            // Mixed funding: Both Grants and PIU Officer need to review
            // Start with Grants Officer (3) for extramural activities first
            $definition = $pick(3, 2); // Grants Officer for extramural
            if ($definition) return $definition;
        }

        // Fallback - go to next available step
        $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where('approval_order', '>', $approvalLevel)
            ->orderBy('approval_order', 'asc')
            ->first();
        if ($definition) return $definition;
        
        // If no next step found due to activity changes, check division category
        $definition = $pickFirstCategoryNode($division->category ?? null);
        if ($definition) return $definition;
    }

    // STEP 3: Fund Source Split (Grants Officer)
    // After Grants Officer (approval_order = 3)
    if ($approvalLevel == 3) {
        // PIU/Grants Officer can remove activities - check for funding status changes
        $model->refresh();
        $currentHasIntra = (bool) ($model->has_intramural ?? false);
        $currentHasExtra = (bool) ($model->has_extramural ?? false);
        $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
        
        // If funding status changed to external, go to category check
        if ($currentIsExternal) {
            // Log::info("PIU/Grants Officer removed all activities - switching to external source for model {$model->id}");
            $definition = $pickFirstCategoryNode($division->category ?? null);
            return $definition;
        }
        
        // Update funding flags based on current status
        $hasIntra = $currentHasIntra;
        $hasExtra = $currentHasExtra;
        $isExternal = $currentIsExternal;
        
        // Check if we have mixed funding (both intramural and extramural)
        if ($hasIntra && $hasExtra) {
            // Mixed funding: Go to PIU Officer (4) for intramural activities
            $definition = $pick(4, 1); // PIU Officer for intramural
            if ($definition) return $definition;
        }
        
        if ($hasIntra && !$hasExtra) {
            // Intramural: go to Finance Officer (5)
            $definition = $pick(5, 1); // Finance Officer for intramural
            if ($definition) return $definition;
        }
        
        // For extramural only, go to Director Finance (6)
        $definition = $pick(6);
        if ($definition) return $definition;
        
        // If no specific approver found due to activity changes, check division category
        $definition = $pickFirstCategoryNode($division->category ?? null);
        if ($definition) return $definition;
    }

    // STEP 4: PIU Officer (Intramural only)
    // After PIU Officer (approval_order = 4), check if intramural activities were removed
    if ($approvalLevel == 4) {
        // PIU Officer can remove activities - check for funding status changes
        $model->refresh();
        $currentHasIntra = (bool) ($model->has_intramural ?? false);
        $currentHasExtra = (bool) ($model->has_extramural ?? false);
        $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
        
        // If funding status changed to external, go to category check
        if ($currentIsExternal) {
            // Log::info("PIU Officer removed all activities - switching to external source for model {$model->id}");
            $definition = $pickFirstCategoryNode($division->category ?? null);
            return $definition;
        }
        
        // Update funding flags based on current status
        $hasIntra = $currentHasIntra;
        $hasExtra = $currentHasExtra;
        $isExternal = $currentIsExternal;
        
        // Go to Finance Officer (5) for intramural activities
        $definition = $pick(5, 1); // Finance Officer for intramural
        if ($definition) return $definition;
        
        // If no Finance Officer found due to activity changes, check division category
        $definition = $pickFirstCategoryNode($division->category ?? null);
        if ($definition) return $definition;
    }

    // STEP 5: Finance Officer (Intramural only)
    // After Finance Officer (approval_order = 5), check if intramural activities were removed
    if ($approvalLevel == 5) {
        // Finance Officer can remove activities - check for funding status changes
        $model->refresh();
        $currentHasIntra = (bool) ($model->has_intramural ?? false);
        $currentHasExtra = (bool) ($model->has_extramural ?? false);
        $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
        
        // If funding status changed to external, go to category check
        if ($currentIsExternal) {
            // Log::info("Finance Officer removed all activities - switching to external source for model {$model->id}");
            $definition = $pickFirstCategoryNode($division->category ?? null);
            return $definition;
        }
        
        // Update funding flags based on current status
        $hasIntra = $currentHasIntra;
        $hasExtra = $currentHasExtra;
        $isExternal = $currentIsExternal;
        
        // If intramural activities still exist, go to Director Finance (6)
        $definition = $pick(6);
        if ($definition) return $definition;
        
        // If no Director Finance found due to activity changes, check division category
        $definition = $pickFirstCategoryNode($division->category ?? null);
        if ($definition) return $definition;
    }

    // STEP 6: Director Finance
    // After Director Finance (approval_order = 6), go to division category check
    if ($approvalLevel == 6) {
        // Director Finance can remove activities - check for funding status changes
        $model->refresh();
        $currentHasIntra = (bool) ($model->has_intramural ?? false);
        $currentHasExtra = (bool) ($model->has_extramural ?? false);
        $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
        
        // Update funding flags based on current status
        $hasIntra = $currentHasIntra;
        $hasExtra = $currentHasExtra;
        $isExternal = $currentIsExternal;
        
        // Go to division category check
        $definition = $pickFirstCategoryNode($division->category ?? null);
        return $definition;
    }

    // STEP 6: Division Category Check (Last option before Director Finance level)
    // Check if we should trigger category check based on current definition
    // This should be the last check before Director Finance level (6)
    $shouldCategoryCheck = ($current_definition && $current_definition->triggers_category_check) 
        || ($isExternal && $approvalLevel >= 2);

    if ($shouldCategoryCheck) {
        // Log::info("Triggering category check for model {$model->id}", [
        //     'category' => $division->category ?? 'null',
        //     'approval_level' => $approvalLevel,
        //     'is_external' => $isExternal
        // ]);
        $definition = $pickFirstCategoryNode($division->category ?? null);
        // Log::info("Category check result for model {$model->id}", [
        //     'found_definition' => $definition ? $definition->role : 'null',
        //     'approval_order' => $definition ? $definition->approval_order : 'null'
        // ]);
        return $definition;
    }

    // Additional check: If external source and at any level after Director, go to category check
    if ($isExternal && $approvalLevel > 2) {
        $definition = $pickFirstCategoryNode($division->category ?? null);
        return $definition;
    }

    // Special case: If at Finance Officer level (4) and no intramural activities remain,
    // treat as external source and go to category check
    if ($approvalLevel == 4 && !$hasIntra && !$hasExtra) {
        $definition = $pickFirstCategoryNode($division->category ?? null);
        return $definition;
    }

    // STEP 7-11: Final Approval Chain (Head Operations/Programs -> DDG -> COP -> DG -> Registry)
    // At level 7, use category-based routing to find the correct approver
    if ($approvalLevel == 7) {
        $definition = $pickFirstCategoryNode($division->category ?? null);
        if ($definition) {
            return $definition;
        }
    }
    
    // Find the next available approval order
    $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
        ->where('is_enabled', 1)
        ->where('approval_order', '>', $approvalLevel)
        ->orderBy('approval_order', 'asc')
        ->first();

    if ($definition) {
        // External: skip finance/PIU/Grants nodes (fund_type 1/2) -> jump to category
        if ($isExternal && in_array((int)$definition->fund_type, [1, 2])) {
            $definition = $pickFirstCategoryNode($division->category ?? null);
            return $definition;
        }

        // Intramural only -> skip extramural row
        if ($hasIntra && !$hasExtra && (int)$definition->fund_type == 2) {
            // Find next approver after skipping extramural
            $nextDefinition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where('approval_order', '>', $definition->approval_order)
                ->orderBy('approval_order', 'asc')
                ->first();
            
            if ($nextDefinition) {
                return $nextDefinition;
            } else {
                return $pickFirstCategoryNode($division->category ?? null);
            }
        }

        // Extramural only -> skip intramural row
        if ($hasExtra && !$hasIntra && (int)$definition->fund_type == 1) {
            // Find next approver after skipping intramural
              $nextDefinition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where('approval_order', '>', $definition->approval_order)
                ->orderBy('approval_order', 'asc')
                ->first();
            
            if ($nextDefinition) {
                return $nextDefinition;
            } else {
                return $pickFirstCategoryNode($division->category ?? null);
            }
        }
    }

    // Generic fallback for any workflow that doesn't match the specific patterns above
    // This handles simple sequential workflows like ARF (workflow_id=2) or Service Requests (workflow_id=3)
    $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
        ->where('is_enabled', 1)
        ->where('approval_order', '>', $approvalLevel)
        ->orderBy('approval_order', 'asc')
        ->first();

    return $definition; // null if end (e.g., after Registry)
}
    /**
     * Get approval trails for a model.
     */
    public function getApprovalTrails(Model $model)
    {
        return ApprovalTrail::where('model_id', $model->id)
            ->where('model_type', get_class($model))
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Check if model is still with creator.
     */
        function isWithCreator($model,$has_activity=false)
        {
            return  ($this->canDivisionHeadEdit($model,$has_activity) ||  ((in_array(session('user')['staff_id'],
            [$model->staff_id,$model->focal_person_id])
             && ($model->forward_workflow_id==null)))) && in_array($model->overall_status,['draft','returned']);
        }



        function canDivisionHeadEdit($model,$has_activity=false){
            $user = (Object) session('user', []);
           // dd(activities_approved_by_me($model));
            //dd($has_activity && activities_approved_by_me($model));
            
            return ($model->division->division_head==$user->staff_id && $model->approval_level==1 && in_array($model->overall_status,['returned']));
        }

     
    /**
     * Check if an approval level should be automatically skipped.
     */
    private function shouldSkipApprovalLevel(Model $model, $definition): bool
    {
        // Skip Director level (approval_order = 2) if division has no director
        if ($definition->approval_order == 2) {
            if (method_exists($model, 'division') && $model->division) {
                return empty($model->division->director_id);
            }
        }

        // Add more automatic skipping rules here as needed
        return false;
    }

    /**
     * Check if an approval level has approvers assigned or is division-specific.
     * This method handles complex approval routing conditions.
     */
    private function hasApproversForLevel($definition, Model $model): bool
    {
        // Check if there are approvers assigned to this workflow definition
        $hasApprovers = Approver::where('workflow_dfn_id', $definition->id)->exists();

        // If no approvers assigned, check if it's division-specific
        if (!$hasApprovers && $definition->is_division_specific) {
            return true; // Division-specific levels don't need explicit approvers
        }

        // If still no approvers, check if this level has complex routing conditions
        if (!$hasApprovers) {
            // Check if this level has fund type routing
            if ($definition->fund_type) {
                // Fund type levels need to be evaluated based on model's fund type
                return $this->matchesFundTypeRouting($definition, $model);
            }

            // Check if this level has category routing
            if ($definition->category) {
                // Category levels need to be evaluated based on division category
                if (method_exists($model, 'division') && $model->division) {
                    return (string)$model->division->category == (string)$definition->category;
                }
            }

            // Check if this level triggers category check
            if ($definition->triggers_category_check) {
                // These levels have special routing logic
                return true;
            }
        }

        return $hasApprovers;
    }

    /**
     * Check if a workflow definition matches the model's fund type routing.
     */
    private function matchesFundTypeRouting($definition, Model $model): bool
    {
        // Get the model's fund type from budget codes
        $modelFundType = $this->getModelFundType($model);

        if ($modelFundType === null) {
            // If we can't determine fund type, allow the level
            return true;
        }

        // Check if the definition's fund type matches the model's fund type
        return (int)$definition->fund_type == (int)$modelFundType;
    }

    /**
     * Get the fund type from the model's budget codes.
     */
    private function getModelFundType(Model $model): ?int
    {
        // Check if the model has budget_id property
        if (!property_exists($model, 'budget_id') || empty($model->budget_id)) {
            return null;
        }

        try {
            // Decode budget codes
            $budgetCodes = json_decode($model->budget_id, true);
            if (!is_array($budgetCodes) || empty($budgetCodes)) {
                return null;
            }

            // Get the first budget code's fund type
            $firstCodeId = $budgetCodes[0];
            $fundCode = DB::table('fund_codes')->where('id', $firstCodeId)->first();

            return $fundCode ? $fundCode->fund_type_id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if the user is the Head of Division for the given model
     */
    private function isHOD(Model $model, int $userId): bool
    {
        // Use the existing isdivision_head helper function
        return isdivision_head($model);
    }
}
