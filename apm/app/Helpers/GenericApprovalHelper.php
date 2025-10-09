<?php

use App\Services\ApprovalService;
use App\Models\ApprovalTrail;
use App\Models\WorkflowDefinition;
use App\Models\Approver;
use App\Models\Staff;
use Carbon\Carbon;

if (!function_exists('can_take_action_generic')) {
    
    /**
     * Check if user can take action on any model.
     */
    function can_take_action_generic($model, $userId = null)
    {
        // Use the ApprovalService for consistent logic
        $approvalService = app(\App\Services\ApprovalService::class);
        //dd('here');
        return $approvalService->canTakeAction($model, $userId ?? user_session('staff_id'));
    }
}

if (!function_exists('done_approving_generic')) {
    /**
     * Check if user has already approved a model.
     */
    function done_approving_generic($model, $userId = null)
    {
        $userId = $userId ?? user_session('staff_id');
        
        $approval = ApprovalTrail::where('model_id', $model->id)
            ->where('model_type', get_class($model))
            ->where('action', 'approved')
            ->where('approval_order', $model->approval_level)
            ->where('staff_id', $userId)
            ->first();

        return $approval !== null;
    }
}

if (!function_exists('is_with_creator_generic')) {
    /**
     * Check if model is still with creator.
     */
    function is_with_creator_generic($model)
    {
         // Use the ApprovalService for consistent logic
         $approvalService = app(\App\Services\ApprovalService::class);
         return $approvalService->isWithCreator($model);
    }
}

if (!function_exists('can_division_head_edit_generic')) {
    /**
     * Check if model is still with creator.
     */
    function can_division_head_edit_generic($model)
    {
         // Use the ApprovalService for consistent logic
         $approvalService = app(\App\Services\ApprovalService::class);
         return $approvalService->canDivisionHeadEdit($model);
    }
}


if (!function_exists('get_approval_recipient_generic')) {
    /**
     * Get the notification recipient for any model.
     */
    function get_approval_recipient_generic($model)
    {
        if ($model->overall_status === 'approved') {
            return null;
        }

        if (!$model->forward_workflow_id || !$model->approval_level) {
            return Staff::where('staff_id', $model->staff_id)->first();
        }

        $today = Carbon::today();
        $current_approval_point = WorkflowDefinition::where('approval_order', $model->approval_level)
            ->where('workflow_id', $model->forward_workflow_id)
            ->first();

        if (!$current_approval_point) {
            return null;
        }

        // Check for division-specific approvers FIRST if this is a division-specific level
        if ($current_approval_point->is_division_specific && method_exists($model, 'division') && $model->division) {
            $division = $model->division;
            $staff_id = $division->{$current_approval_point->division_reference_column};
            if ($staff_id) {
                return Staff::where('staff_id', $staff_id)->first();
            }
        }

        // Check for regular approvers
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

        return null;
    }
}

if (!function_exists('get_next_approver_generic')) {
    /**
     * Get the next approver for any model.
     */
    // function get_next_approver_generic($model)
    // {
    //     if (!$model->forward_workflow_id || !$model->approval_level) {
    //         return null;
    //     }

    //     $current_definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
    //         ->where('is_enabled', 1)
    //         ->where('approval_order', $model->approval_level)
    //         ->first();
    //     $nextStepIncrement = 1;
    //     if (!$current_definition) {
    //         return null;
    //     }

    //     // Check if we need to trigger category check
    //     $go_to_category_check = false;
    //     if (method_exists($model, 'division') && $model->division) {
    //         $go_to_category_check = (!$model->has_extramural && !$model->has_intramural && ($model->approval_level != null && $current_definition->approval_order > $model->approval_level));
    //     }

    //     if (($current_definition && $current_definition->triggers_category_check && $model->division->category!='Other') || $go_to_category_check ) {
    //         if (method_exists($model, 'division') && $model->division) {
    //             $category_definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
    //                 ->where('is_enabled', 1)
    //                 ->where('category', $model->division->category)
    //                 ->orderBy('approval_order', 'asc')
    //                 ->first();

    //             return $category_definition;
    //         }
    //     }

      

    //     // Skip Directorate from HOD if no directorate
    //     if ($model->forward_workflow_id > 0 && $current_definition->approval_order == 1) {
    //         if (method_exists($model, 'division') && $model->division && !$model->division->director_id) {
    //             $nextStepIncrement = 2;
    //         }
    //     }

    //     if(($current_definition && $current_definition->triggers_category_check) && $model->division->category=='Other'){
    //         $nextStepIncrement = 2;
    //     }

    //     $next_approval_order = $model->approval_level + $nextStepIncrement;

    //     return WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
    //         ->where('is_enabled', 1)
    //         ->where('approval_order', $next_approval_order)
    //         ->first();
    // }
    function get_next_approver_generic($model)  
    {
        // Input validation
        if (!$model || !$model->division) {
            Log::error('Invalid matrix or division in get_next_approver');
            return null;
        }

        $division = $model->division;
        $approvalLevel = (int) ($matrix->approval_level ?? 0);
        
        // Determine funding types based on matrix properties
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
                Log::info("Funding status changed to external at level {$approvalLevel} for matrix {$model->id}", [
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
                Log::info("Funding status changed from external at level {$approvalLevel} for matrix {$model->id}", [
                    'previous_status' => 'external',
                    'current_status' => 'has_funding',
                    'has_intramural' => $hasIntra,
                    'has_extramural' => $hasExtra,
                    'approval_level' => $approvalLevel
                ]);
            }
        }
        
        // Debug logging (commented out due to permission issues)
        // Log::info("Matrix {$matrix->id} workflow check", [
        //     'approval_level' => $approvalLevel,
        //     'has_intramural' => $hasIntra,
        //     'has_extramural' => $hasExtra,
        //     'is_external' => $isExternal,
        //     'division_category' => $division->category ?? 'null',
        //     'division_director_id' => $division->director_id ?? 'null'
        // ]);
        
        // Debug output for testing (remove in production)
        // echo "DEBUG: Matrix {$matrix->id} - Level: {$approvalLevel}, HasIntra: " . ($hasIntra ? 'true' : 'false') . ", HasExtra: " . ($hasExtra ? 'true' : 'false') . ", IsExternal: " . ($isExternal ? 'true' : 'false') . ", Category: " . ($division->category ?? 'null') . PHP_EOL;

        // Ensure workflow ID is set
        if (!$model->forward_workflow_id) {
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Matrix');
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 1;
                Log::warning('No workflow assignment found for Matrix; using default workflow_id=1');
            }
            $model->forward_workflow_id = $assignedWorkflowId;
        }

        // Helper function to get workflow definition by order, fund type, and category
        $pick = function (int $order, ?int $fundType = null, ?string $category = null) use ($model) {
            $query = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where('approval_order', $order);

            if ($fundType !== null) $query->where('fund_type', $fundType); // 1=intramural, 2=extramural, 3=external
            if ($category !== null) $query->where('category', $category);

            return $query->first();
        };

        // Helper function to get first category-based approver
                $pickFirstCategoryNode = function (?string $category) use ($model, $pick, $approvalLevel) {
            $cat = $category ?: 'Other';
            
            // First try to get category-specific approver
            $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where('category', $cat)
                ->orderBy('approval_order', 'asc')
            ->first();

            // If we found a category-specific approver, check if we've already passed it
            if ($definition) {
                // If current approval level is greater than or equal to the category approver level,
                // we've already passed this approver, so go to the next available approval order
                if ($approvalLevel >= $definition->approval_order) {
                    // We've already passed the category approver, find the next available approval order
                    $nextDefinition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                        ->where('is_enabled', 1)
                        ->where('approval_order', '>', $approvalLevel)
                        ->orderBy('approval_order', 'asc')
                        ->first();
                    
                    if ($nextDefinition) {
                        return $nextDefinition;
                    }
                } else {
                    // We haven't reached the category approver yet, return it
                    return $definition;
                }
            }
            
            // If no category-specific approver found, find the next available approval order
            $nextDefinition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where('approval_order', '>', $approvalLevel)
                ->orderBy('approval_order', 'asc')
                ->first();
            
            if ($nextDefinition) {
                return $nextDefinition;
            }
            
            // Fallback: return null if no next approver found
            return null;
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
            // Check if division has directorate
            if (!$division->director_id) {
                // No directorate - skip to next available step (not category check yet)
                $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                    ->where('is_enabled', 1)
                    ->where('approval_order', '>', $approvalLevel)
                    ->orderBy('approval_order', 'asc')
                    ->first();
                return $definition;
            }
            // Has directorate - proceed to Director (next step)
            $definition = $pick(2);
            return $definition;
        }

        // STEP 2: Directorate Check
        // If at Director level (approval_order = 2), proceed to fund source check
        if ($approvalLevel == 2) {
            // For Gavi/CEPI/WB funding, determine fund type
            if ($hasIntra && !$hasExtra) {
                // Intramural: PIU Officer (3) -> Finance Officer (4) -> Director Finance (5)
                $definition = $pick(3, 1); // PIU Officer for intramural
                if ($definition) return $definition;
            }

            if ($hasExtra && !$hasIntra) {
                // Extramural: Grants Officer (3) -> Director Finance (5) (skips Finance Officer)
                $definition = $pick(3, 2); // Grants Officer for extramural
                if ($definition) return $definition;
            }

            // For external sources or mixed funding, go to next available step
            $definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where('approval_order', '>', $approvalLevel)
                ->orderBy('approval_order', 'asc')
                ->first();
            return $definition;
        }

        // STEP 3: Fund Source Split (PIU/Grants Officer)
        // After PIU/Grants Officer (approval_order = 3)
        if ($approvalLevel == 3) {
            // PIU/Grants Officer can remove activities - check for funding status changes
            $model->refresh();
            $currentHasIntra = (bool) ($model->has_intramural ?? false);
            $currentHasExtra = (bool) ($model->has_extramural ?? false);
            $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
            
            // If funding status changed to external, go to category check
            if ($currentIsExternal) {
                // Log::info("PIU/Grants Officer removed all activities - switching to external source for matrix {$matrix->id}");
                $definition = $pickFirstCategoryNode($division->category ?? null);
                return $definition;
            }
            
            // Update funding flags based on current status
            $hasIntra = $currentHasIntra;
            $hasExtra = $currentHasExtra;
            $isExternal = $currentIsExternal;
            
            if ($hasIntra && !$hasExtra) {
                // Intramural: go to Finance Officer (4)
                $definition = $pick(4, 1) ?? $pick(4, null);
                if ($definition) return $definition;
            }
            
            // For extramural or mixed cases, go to Director Finance (5)
            $definition = $pick(5);
            if ($definition) return $definition;
        }

        // STEP 4: Finance Officer (Intramural only)
        // After Finance Officer (approval_order = 4), check if intramural activities were removed
        if ($approvalLevel == 4) {
            // Finance Officer can remove activities - check for funding status changes
            $model->refresh();
            $currentHasIntra = (bool) ($model->has_intramural ?? false);
            $currentHasExtra = (bool) ($model->has_extramural ?? false);
            $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
            
            // If funding status changed to external, go to category check
            if ($currentIsExternal) {
                // Log::info("Finance Officer removed all activities - switching to external source for matrix {$matrix->id}");
                $definition = $pickFirstCategoryNode($division->category ?? null);
                return $definition;
            }
            
            // Update funding flags based on current status
            $hasIntra = $currentHasIntra;
            $hasExtra = $currentHasExtra;
            $isExternal = $currentIsExternal;
            
            // If intramural activities still exist, go to Director Finance (5)
            $definition = $pick(5);
            if ($definition) return $definition;
        }

        // STEP 5: Director Finance
        // After Director Finance (approval_order = 5), go to division category check
        if ($approvalLevel == 5) {
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

        // STEP 6: Division Category Check
        // Check if we should trigger category check based on current definition
        $shouldCategoryCheck = ($current_definition && $current_definition->triggers_category_check) 
            || ($isExternal && $approvalLevel >= 2);

        if ($shouldCategoryCheck) {
            // Log::info("Triggering category check for matrix {$matrix->id}", [
            //     'category' => $division->category ?? 'null',
            //     'approval_level' => $approvalLevel,
            //     'is_external' => $isExternal
            // ]);
            $definition = $pickFirstCategoryNode($division->category ?? null);
            // Log::info("Category check result for matrix {$matrix->id}", [
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

        return $definition; // null if end (e.g., after Registry)
    }

}

if (!function_exists('save_approval_trail_generic')) {
    /**
     * Save approval trail for any model.
     */
    function save_approval_trail_generic($model, $comment, $action, $approvalOrder = null)
    {
        $trail = new ApprovalTrail();
        $trail->model_id = $model->id;
        $trail->model_type = get_class($model);
        $trail->remarks = $comment;
        $trail->action = $action;
        $trail->approval_order = $approvalOrder ?? $model->approval_level ?? 1;
        $trail->staff_id = user_session('staff_id');

        // For activities, also save matrix_id
        if (method_exists($model, 'matrix_id') && $model->matrix_id) {
            $trail->matrix_id = $model->matrix_id;
        }

        $trail->save();

        return $trail;
    }
}

if (!function_exists('get_approval_trails_generic')) {
    /**
     * Get approval trails for any model.
     */
    function get_approval_trails_generic($model)
    {
        return ApprovalTrail::where('model_id', $model->id)
            ->where('model_type', get_class($model))
            ->orderBy('id', 'asc')
            ->get();
    }
}

// Legacy functions for backward compatibility
if (!function_exists('can_take_action')) {
    function can_take_action($matrix)
    {
        return can_take_action_generic($matrix);
    }
}

if (!function_exists('done_approving')) {
    function done_approving($matrix)
    {
        return done_approving_generic($matrix);
    }
}

if (!function_exists('still_with_creator')) {
    function still_with_creator($matrix)
    {
        return is_with_creator_generic($matrix);
    }
}

if (!function_exists('get_matrix_notification_recipient')) {
    function get_matrix_notification_recipient($matrix)
    {
        return get_approval_recipient_generic($matrix);
    }
} 
