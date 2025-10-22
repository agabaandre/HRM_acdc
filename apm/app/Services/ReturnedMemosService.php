<?php

namespace App\Services;

use App\Models\Matrix;
use App\Models\Activity;
use App\Models\SpecialMemo;
use App\Models\NonTravelMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use App\Models\ChangeRequest;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowModel;
use App\Models\Approver;
use App\Models\Staff;
use App\Models\Division;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReturnedMemosService
{
    protected $currentStaffId;
    protected $currentDivisionId;
    protected $userPermissions;
    protected $sessionData;

    public function __construct(?array $sessionData = null)
    {
        $this->sessionData = $sessionData ?? [
            'staff_id' => user_session('staff_id'),
            'division_id' => user_session('division_id'),
            'permissions' => user_session('permissions', []),
            'name' => user_session('name'),
            'email' => user_session('email'),
            'base_url' => user_session('base_url')
        ];
        
        $this->currentStaffId = $this->sessionData['staff_id'];
        $this->currentDivisionId = $this->sessionData['division_id'];
        $this->userPermissions = $this->sessionData['permissions'];
    }

    /**
     * Get all returned memo items for the current user
     */
    public function getReturnedMemos(): array
    {
        $returnedItems = collect();

        // Get returned matrices
        $returnedItems = $returnedItems->merge($this->getReturnedMatrices());

        // Get returned special memos
        $returnedItems = $returnedItems->merge($this->getReturnedSpecialMemos());

        // Get returned non-travel memos
        $returnedItems = $returnedItems->merge($this->getReturnedNonTravelMemos());

        // Get returned single memos (activities with is_single_memo = true)
        $returnedItems = $returnedItems->merge($this->getReturnedSingleMemos());

        // Get returned service requests
        $returnedItems = $returnedItems->merge($this->getReturnedServiceRequests());

        // Get returned ARF requests
        $returnedItems = $returnedItems->merge($this->getReturnedARFRequests());

        // Get returned change requests
        $returnedItems = $returnedItems->merge($this->getReturnedChangeRequests());

        // Group by category and sort by date received
        return $this->groupByCategory($returnedItems);
    }

    /**
     * Get returned matrices
     */
    protected function getReturnedMatrices(): Collection
    {
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow',
            'activities' => function ($q) {
                $q->select('id', 'matrix_id', 'activity_title', 'total_participants', 'budget_breakdown')
                  ->whereNotNull('matrix_id');
            }
        ]);

        // Only show returned matrices (status = 'returned' and staff_id matches OR division staff)
        $query->where('overall_status', 'returned')
              ->where(function($q) {
                  // First check if user is the owner (staff_id) - regardless of division
                  $q->where('staff_id', $this->currentStaffId)
                    // OR if user is division staff (HOD, focal person, admin assistant)
                    ->orWhereHas('division', function($divisionQuery) {
                        $divisionQuery->where('division_head', $this->currentStaffId)
                                     ->orWhere('focal_person', $this->currentStaffId)
                                     ->orWhere('admin_assistant', $this->currentStaffId);
                    });
              });

        return $query->orderBy('updated_at', 'desc')->get()->map(function ($matrix) {
            return [
                'id' => $matrix->id,
                'type' => 'Matrix',
                'title' => $matrix->activity_title ?? 'Untitled Matrix',
                'document_number' => $matrix->document_number ?? 'N/A',
                'division' => $matrix->division->division_name ?? 'N/A',
                'submitted_by' => $matrix->staff->fname . ' ' . $matrix->staff->lname,
                'date_received' => $matrix->updated_at,
                'status' => $matrix->overall_status,
                'view_url' => route('matrices.show', $matrix),
                'edit_url' => can_edit_memo($matrix, $this->getCurrentUser()) ? route('matrices.edit', $matrix) : null,
                'delete_url' => can_delete_memo($matrix, $this->getCurrentUser()) ? route('matrices.destroy', $matrix) : null,
                'can_edit' => can_edit_memo($matrix, $this->getCurrentUser()),
                'can_delete' => can_delete_memo($matrix, $this->getCurrentUser()),
                'workflow_role' => $this->getWorkflowRole($matrix, 'Matrix'),
                'approval_level' => $matrix->approval_level ?? 0,
                'model' => $matrix
            ];
        });
    }

    /**
     * Get returned special memos
     */
    protected function getReturnedSpecialMemos(): Collection
    {
        $query = SpecialMemo::with(['division', 'staff', 'forwardWorkflow']);

        // Only show returned special memos (status = 'returned' and responsible_person_id matches OR division staff)
        $query->where('overall_status', 'returned')
              ->where(function($q) {
                  // First check if user is the responsible person (regardless of division)
                  $q->where('responsible_person_id', $this->currentStaffId)
                    // OR if user is division staff (HOD, focal person, admin assistant)
                    ->orWhereHas('division', function($divisionQuery) {
                        $divisionQuery->where('division_head', $this->currentStaffId)
                                     ->orWhere('focal_person', $this->currentStaffId)
                                     ->orWhere('admin_assistant', $this->currentStaffId);
                    });
              });

        return $query->orderBy('updated_at', 'desc')->get()->map(function ($memo) {
            return [
                'id' => $memo->id,
                'type' => 'Special Memo',
                'title' => $memo->activity_title ?? 'Untitled Special Memo',
                'document_number' => $memo->document_number ?? 'N/A',
                'division' => $memo->division->division_name ?? 'N/A',
                'submitted_by' => $this->formatSubmittedBy($memo),
                'date_received' => $memo->updated_at,
                'status' => $memo->overall_status,
                'view_url' => route('special-memo.show', $memo),
                'edit_url' => can_edit_memo($memo, $this->getCurrentUser()) ? route('special-memo.edit', $memo) : null,
                'delete_url' => can_delete_memo($memo, $this->getCurrentUser()) ? route('special-memo.destroy', $memo) : null,
                'can_edit' => can_edit_memo($memo, $this->getCurrentUser()),
                'can_delete' => can_delete_memo($memo, $this->getCurrentUser()),
                'workflow_role' => $this->getWorkflowRole($memo, 'Special Memo'),
                'approval_level' => $memo->approval_level ?? 0,
                'model' => $memo
            ];
        });
    }

    /**
     * Get returned non-travel memos
     */
    protected function getReturnedNonTravelMemos(): Collection
    {
        $query = NonTravelMemo::with(['division', 'staff', 'forwardWorkflow']);

        // Only show returned non-travel memos (status = 'returned' and staff_id matches OR division staff)
        $query->where('overall_status', 'returned')
              ->where(function($q) {
                  // First check if user is the owner (staff_id) - regardless of division
                  $q->where('staff_id', $this->currentStaffId)
                    // OR if user is division staff (HOD, focal person, admin assistant)
                    ->orWhereHas('division', function($divisionQuery) {
                        $divisionQuery->where('division_head', $this->currentStaffId)
                                     ->orWhere('focal_person', $this->currentStaffId)
                                     ->orWhere('admin_assistant', $this->currentStaffId);
                    });
              });

        return $query->orderBy('updated_at', 'desc')->get()->map(function ($memo) {
            return [
                'id' => $memo->id,
                'type' => 'Non-Travel Memo',
                'title' => $memo->activity_title ?? 'Untitled Non-Travel Memo',
                'document_number' => $memo->document_number ?? 'N/A',
                'division' => $memo->division->division_name ?? 'N/A',
                'submitted_by' => $this->formatSubmittedBy($memo),
                'date_received' => $memo->updated_at,
                'status' => $memo->overall_status,
                'view_url' => route('non-travel.show', $memo),
                'edit_url' => can_edit_memo($memo, $this->getCurrentUser()) ? route('non-travel.edit', $memo) : null,
                'delete_url' => can_delete_memo($memo, $this->getCurrentUser()) ? route('non-travel.destroy', $memo) : null,
                'can_edit' => can_edit_memo($memo, $this->getCurrentUser()),
                'can_delete' => can_delete_memo($memo, $this->getCurrentUser()),
                'workflow_role' => $this->getWorkflowRole($memo, 'Non-Travel Memo'),
                'approval_level' => $memo->approval_level ?? 0,
                'model' => $memo
            ];
        });
    }

    /**
     * Get returned single memos (includes draft status since returned single memos become draft for immediate editing)
     */
    protected function getReturnedSingleMemos(): Collection
    {
        $query = Activity::with(['division', 'staff', 'forwardWorkflow'])
            ->where('is_single_memo', true);

        // Show returned single memos (status = 'returned' or 'draft' for single memos) and staff_id OR responsible_person_id matches OR division staff
        $query->whereIn('overall_status', ['returned', 'draft'])
              ->where(function($q) {
                  // First check if user is the owner (staff_id) - regardless of division
                  $q->where('staff_id', $this->currentStaffId)
                    // OR if user is the responsible person (responsible_person_id) - regardless of division
                    ->orWhere('responsible_person_id', $this->currentStaffId)
                    // OR if user is division staff (HOD, focal person, admin assistant)
                    ->orWhereHas('division', function($divisionQuery) {
                        $divisionQuery->where('division_head', $this->currentStaffId)
                                     ->orWhere('focal_person', $this->currentStaffId)
                                     ->orWhere('admin_assistant', $this->currentStaffId);
                    });
              });

        return $query->orderBy('updated_at', 'desc')->get()->map(function ($memo) {
            return [
                'id' => $memo->id,
                'type' => 'Single Memo',
                'title' => $memo->activity_title ?? 'Untitled Single Memo',
                'document_number' => $memo->document_number ?? 'N/A',
                'division' => $memo->division->division_name ?? 'N/A',
                'submitted_by' => $this->formatSubmittedBy($memo),
                'date_received' => $memo->updated_at,
                'status' => $memo->overall_status,
                'view_url' => route('activities.single-memos.show', $memo),
                'edit_url' => can_edit_memo($memo, $this->getCurrentUser()) ? route('activities.single-memos.edit', ['matrix' => $memo->matrix_id ?? 0, 'activity' => $memo]) : null,
                'delete_url' => can_delete_memo($memo, $this->getCurrentUser()) ? route('activities.single-memos.destroy', $memo) : null,
                'can_edit' => can_edit_memo($memo, $this->getCurrentUser()),
                'can_delete' => can_delete_memo($memo, $this->getCurrentUser()),
                'workflow_role' => $this->getWorkflowRole($memo, 'Single Memo'),
                'approval_level' => $memo->approval_level ?? 0,
                'model' => $memo
            ];
        });
    }

    /**
     * Get returned service requests
     */
    protected function getReturnedServiceRequests(): Collection
    {
        $query = ServiceRequest::with(['division', 'staff', 'forwardWorkflow']);

        // Only show returned service requests (status = 'returned' and staff_id matches OR division staff)
        $query->where('overall_status', 'returned')
              ->where(function($q) {
                  // First check if user is the owner (staff_id) - regardless of division
                  $q->where('staff_id', $this->currentStaffId)
                    // OR if user is division staff (HOD, focal person, admin assistant)
                    ->orWhereHas('division', function($divisionQuery) {
                        $divisionQuery->where('division_head', $this->currentStaffId)
                                     ->orWhere('focal_person', $this->currentStaffId)
                                     ->orWhere('admin_assistant', $this->currentStaffId);
                    });
              });

        return $query->orderBy('updated_at', 'desc')->get()->map(function ($request) {
            return [
                'id' => $request->id,
                'type' => 'Service Request',
                'title' => $request->service_title ?? 'Untitled Service Request',
                'document_number' => $request->document_number ?? 'N/A',
                'division' => $request->division->division_name ?? 'N/A',
                'submitted_by' => $this->formatSubmittedBy($request),
                'date_received' => $request->updated_at,
                'status' => $request->overall_status,
                'view_url' => route('service-requests.show', $request),
                'edit_url' => null, // Service requests only have delete
                'delete_url' => can_delete_memo($request, $this->getCurrentUser()) ? route('service-requests.destroy', $request) : null,
                'can_edit' => false, // Service requests only have delete
                'can_delete' => can_delete_memo($request, $this->getCurrentUser()),
                'workflow_role' => $this->getWorkflowRole($request, 'Service Request'),
                'approval_level' => $request->approval_level ?? 0,
                'model' => $request
            ];
        });
    }

    /**
     * Get returned ARF requests
     */
    protected function getReturnedARFRequests(): Collection
    {
        $query = RequestARF::with(['division', 'staff', 'forwardWorkflow']);

        // Only show returned ARF requests (status = 'returned' and staff_id matches OR division staff)
        $query->where('overall_status', 'returned')
              ->where(function($q) {
                  // First check if user is the owner (staff_id) - regardless of division
                  $q->where('staff_id', $this->currentStaffId)
                    // OR if user is division staff (HOD, focal person, admin assistant)
                    ->orWhereHas('division', function($divisionQuery) {
                        $divisionQuery->where('division_head', $this->currentStaffId)
                                     ->orWhere('focal_person', $this->currentStaffId)
                                     ->orWhere('admin_assistant', $this->currentStaffId);
                    });
              });

        return $query->orderBy('updated_at', 'desc')->get()->map(function ($request) {
            return [
                'id' => $request->id,
                'type' => 'ARF',
                'title' => $request->activity_title ?? 'Untitled ARF Request',
                'document_number' => $request->arf_number ?? 'N/A',
                'division' => $request->division->division_name ?? 'N/A',
                'submitted_by' => $this->formatSubmittedBy($request),
                'date_received' => $request->updated_at,
                'status' => $request->overall_status,
                'view_url' => route('request-arf.show', $request),
                'edit_url' => null, // ARF requests only have delete
                'delete_url' => can_delete_memo($request, $this->getCurrentUser()) ? route('request-arf.destroy', $request) : null,
                'can_edit' => false, // ARF requests only have delete
                'can_delete' => can_delete_memo($request, $this->getCurrentUser()),
                'workflow_role' => $this->getWorkflowRole($request, 'ARF'),
                'approval_level' => $request->approval_level ?? 0,
                'model' => $request
            ];
        });
    }

    /**
     * Get returned change requests
     */
    protected function getReturnedChangeRequests(): Collection
    {
        $query = ChangeRequest::with(['division', 'staff', 'forwardWorkflow']);

        // Only show returned change requests (status = 'returned' and responsible_person_id matches OR division staff)
        $query->where('overall_status', 'returned')
              ->where(function($q) {
                  // First check if user is the responsible person (regardless of division)
                  $q->where('responsible_person_id', $this->currentStaffId)
                    // OR if user is division staff (HOD, focal person, admin assistant)
                    ->orWhereHas('division', function($divisionQuery) {
                        $divisionQuery->where('division_head', $this->currentStaffId)
                                     ->orWhere('focal_person', $this->currentStaffId)
                                     ->orWhere('admin_assistant', $this->currentStaffId);
                    });
              });

        return $query->orderBy('updated_at', 'desc')->get()->map(function ($request) {
            return [
                'id' => $request->id,
                'type' => 'Change Request',
                'title' => $request->change_title ?? 'Untitled Change Request',
                'document_number' => $request->document_number ?? 'N/A',
                'division' => $request->division->division_name ?? 'N/A',
                'submitted_by' => $this->formatSubmittedBy($request),
                'date_received' => $request->updated_at,
                'status' => $request->overall_status,
                'view_url' => route('change-requests.show', $request),
                'edit_url' => can_edit_memo($request, $this->getCurrentUser()) ? route('change-requests.edit', $request) : null,
                'delete_url' => can_delete_memo($request, $this->getCurrentUser()) ? route('change-requests.destroy', $request) : null,
                'can_edit' => can_edit_memo($request, $this->getCurrentUser()),
                'can_delete' => can_delete_memo($request, $this->getCurrentUser()),
                'workflow_role' => $this->getWorkflowRole($request, 'Change Request'),
                'approval_level' => $request->approval_level ?? 0,
                'model' => $request
            ];
        });
    }

    /**
     * Group items by category
     */
    protected function groupByCategory(Collection $items): array
    {
        $grouped = $items->groupBy('type')->toArray();
        
        // Sort each category by date received (most recent first)
        foreach ($grouped as $category => $items) {
            usort($grouped[$category], function ($a, $b) {
                return $b['date_received'] <=> $a['date_received'];
            });
        }
        
        return $grouped;
    }

    /**
     * Get summary statistics
     */
    public function getSummaryStats(): array
    {
        $returnedMemos = $this->getReturnedMemos();
        
        $totalReturned = 0;
        $byCategory = [];
        
        foreach ($returnedMemos as $category => $items) {
            $count = count($items);
            $totalReturned += $count;
            $byCategory[$category] = $count;
        }
        
        return [
            'total_returned' => $totalReturned,
            'by_category' => $byCategory
        ];
    }

    /**
     * Get filter options for the returned memos page
     */
    public function getFilterOptions()
    {
        $returnedMemos = $this->getReturnedMemos();
        
        // Get categories with counts
        $categories = [];
        foreach ($returnedMemos as $category => $items) {
            $categories[] = [
                'value' => strtolower(str_replace([' ', '-'], ['_', '_'], $category)),
                'label' => $category,
                'count' => count($items)
            ];
        }
        
        // Get divisions
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        
        return [
            'categories' => $categories,
            'divisions' => $divisions
        ];
    }

    /**
     * Format submitted by information to include both submitter and responsible person where applicable
     */
    protected function formatSubmittedBy($memo): string
    {
        $submitter = $memo->staff->fname . ' ' . $memo->staff->lname;
        
        // For memos that have a responsible person different from the submitter
        if (isset($memo->responsible_person_id) && $memo->responsible_person_id && $memo->responsible_person_id != $memo->staff_id) {
            $responsiblePerson = \App\Models\Staff::find($memo->responsible_person_id);
            if ($responsiblePerson) {
                $responsibleName = $responsiblePerson->fname . ' ' . $responsiblePerson->lname;
                return $submitter . '<br><small class="text-muted">Responsible: ' . $responsibleName . '</small>';
            }
        }
        
        return $submitter;
    }

    /**
     * Get workflow role for an item
     */
    protected function getWorkflowRole($model, string $type): string
    {
        if (!$model->forwardWorkflow) {
            return 'Creator';
        }

        $workflowDefinition = WorkflowDefinition::where('workflow_id', $model->forwardWorkflow->id)
            ->where('approval_order', $model->approval_level ?? 0)
            ->first();

        return $workflowDefinition ? $workflowDefinition->role : 'Creator';
    }

    /**
     * Get current user object for permission checks
     */
    private function getCurrentUser()
    {
        return (object) [
            'staff_id' => $this->currentStaffId,
            'division_id' => $this->currentDivisionId,
            'permissions' => $this->userPermissions
        ];
    }
}
