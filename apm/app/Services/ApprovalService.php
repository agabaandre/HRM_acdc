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

          if (empty($user['staff_id']) || $this->hasUserApproved($model, $user['staff_id']) || in_array($model->overall_status,['approved','draft','returned'])) {
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
    public function processApproval(Model $model, string $action, string $comment = null, int $userId = null): void
    {
        $userId = $userId ?? user_session('staff_id');
       // dd($model->approval_level);

        // Save approval trail
        $trail = new ApprovalTrail();
        $trail->model_id = $model->id;
        $trail->model_type = get_class($model);
        $trail->remarks = $comment ?? '';
        $trail->forward_workflow_id = $model->forward_workflow_id;
        $trail->action = $action;
        $trail->approval_order = $model->approval_level ?? 1;
        $trail->staff_id = $userId;

        // For activities, also save matrix_id
        if (method_exists($model, 'matrix_id') && $model->matrix_id) {
            $trail->matrix_id = $model->matrix_id;
        }

        $trail->save();

        // Update model status
        if ($action !== 'approved') {
            $model->forward_workflow_id = intval($model->approval_level)==1?NULL:$model->forward_workflow_id;
            $model->approval_level = intval($model->approval_level)==1?0:1;
            //dd($model->approval_level);
            $model->overall_status = 'returned';
        } else {
            $next_approver = $this->getNextApprover($model);

            if ($next_approver) {
                $model->forward_workflow_id = $next_approver->workflow_id;
                $model->approval_level = $next_approver->approval_order;
                $model->next_approval_level = $next_approver->approval_order;
                $model->overall_status = 'pending';
            } else {
                $model->overall_status = 'approved';
            }
        }

        $model->update();
    }

   


    public function getNextApprover($model){

        $division   = $model->division;

    $current_definition = WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
       ->where('is_enabled',1)
       ->where('approval_order',$model->approval_level)
       ->first();

    $go_to_category_check_for_external =(!$model->has_extramural && !$model->has_extramural && ($model->approval_level!=null && $current_definition->approval_order > $model->approval_level));

    //if it's time to trigger categroy check, just check and continue
    if(($current_definition && $current_definition->triggers_category_check) || $go_to_category_check_for_external){

        $category_definition = WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
                    ->where('is_enabled',1)
                    ->where('category',$division->category)
                    ->orderBy('approval_order','asc')
                    ->first();

        return $category_definition;
    }

    $nextStepIncrement = 1;

    //Skip Directorate from HOD if no directorate
    if($model->forward_workflow_id>0 && $current_definition->approval_order==1 && !$division->director_id)
        $nextStepIncrement = 2;

     if(!$model->forward_workflow_id)// null
        $model->forward_workflow_id = 1;

    $next_definition = WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
       ->where('is_enabled',1)
       ->where('approval_order',$model->approval_level +$nextStepIncrement)->get();
        
    //if matrix has_extramural is true and matrix->approval_level !==definition_approval_order, 
    // get from $definition where fund_type=2, else where fund_type=2
    //if one, just return the one available
    if ($next_definition->count() > 1) {

        if ($model->has_extramural && $model->approval_level !== $next_definition->first()->approval_order) {
            return $next_definition->where('fund_type', 2);
        } 
        else {
            return $next_definition->where('fund_type', 1);
        }
    }

    $definition = ($next_definition->count()>0)?$next_definition[0]:null;
    //dd($definition);
    //intramural only, skip extra mural role
    if($definition  && !$model->has_extramural &&  $definition->fund_type==2){
      return WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
        ->where('is_enabled',1)
        ->where('approval_order',$definition->approval_order+1)->first();
    }

    //only extramural, skip by intramural roles
    if($definition  && !$model->has_intramural &&  $definition->fund_type==1){
        return WorkflowDefinition::where('workflow_id',$model->forward_workflow_id)
          ->where('is_enabled',1)
          ->where('approval_order', $definition->approval_order+2)->first();
    }

   
   return $definition;
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
}
