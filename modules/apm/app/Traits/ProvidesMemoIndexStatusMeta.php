<?php

namespace App\Traits;

use App\Models\Division;
use App\Models\Staff;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowModel;

/**
 * Level, workflow role, and current approver for memo/CR index tables (pending + returned).
 */
trait ProvidesMemoIndexStatusMeta
{
    /**
     * E.g. "Activity", "NonTravelMemo", "SpecialMemo". Return "" when workflow is resolved only via overrides.
     */
    protected function memoIndexWorkflowModelName(): string
    {
        return '';
    }

    /**
     * Effective workflow_id for resolving the role at the current approval level.
     */
    protected function memoIndexResolvedWorkflowId(): ?int
    {
        if ($this->forward_workflow_id) {
            return (int) $this->forward_workflow_id;
        }
        if (in_array($this->overall_status, ['returned', 'draft'], true)) {
            $name = $this->memoIndexWorkflowModelName();
            if ($name === '') {
                return null;
            }

            return WorkflowModel::getWorkflowIdForModel($name) ?: null;
        }

        return null;
    }

    protected function memoIndexDivisionContext(): ?Division
    {
        return $this->division ?? null;
    }

    public function memoIndexStatusMeta(): array
    {
        $level = $this->approval_level;
        $workflowId = $this->memoIndexResolvedWorkflowId();
        $division = $this->memoIndexDivisionContext();
        $divisionCategory = $division?->category;

        $role = 'N/A';
        if ($workflowId && $level !== null && $level !== '') {
            // Avoid repeated DB hits across rows in index tables.
            static $definitionsCache = [];
            $cacheKey = implode(':', [
                (int) $workflowId,
                (int) $level,
                (string) ($divisionCategory ?? ''),
            ]);

            if (!array_key_exists($cacheKey, $definitionsCache)) {
                $definitions = WorkflowDefinition::where('workflow_id', $workflowId)
                    ->where('approval_order', (int) $level)
                    ->where('is_enabled', 1)
                    ->get();

                $definition = null;
                if ($definitions->count() > 1 && ($definitions->first()->category ?? null)) {
                    $definition = $division
                        ? $definitions->where('category', $divisionCategory)->first()
                        : $definitions->first();
                } else {
                    $definition = $definitions->first();
                }

                $definitionsCache[$cacheKey] = $definition?->role ?? 'N/A';
            }

            $role = $definitionsCache[$cacheKey];
        }

        $actor = $this->current_actor;

        if (! $actor && in_array($this->overall_status, ['returned', 'draft'], true) && (int) $level === 1) {
            if ($division) {
                $headId = function_exists('effective_division_head_staff_id')
                    ? effective_division_head_staff_id($division)
                    : null;
                if ($headId) {
                    $actor = Staff::select('lname', 'fname', 'staff_id', 'job_name', 'division_name')
                        ->where('staff_id', (int) $headId)
                        ->first();
                }
            }
        }

        $actorName = $actor
            ? trim(($actor->fname ?? '') . ' ' . ($actor->lname ?? ''))
            : 'N/A';

        return [
            'level' => $level ?? 'N/A',
            'role' => $role,
            'actor_name' => $actorName,
        ];
    }
}
