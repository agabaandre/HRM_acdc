<?php

namespace App\Helpers;

class PrintHelper
{
    /**
     * Safely get staff email from approver data
     */
    public static function getStaffEmail($approver)
    {
        if (isset($approver['staff']) && isset($approver['staff']['work_email'])) {
            return $approver['staff']['work_email'];
        } elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['work_email'])) {
            return $approver['oic_staff']['work_email'];
        }
        return null;
    }
    
    /**
     * Safely get staff ID from approver data
     */
    public static function getStaffId($approver)
    {
        if (isset($approver['staff']) && isset($approver['staff']['id'])) {
            return $approver['staff']['id'];
        } elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['id'])) {
            return $approver['oic_staff']['id'];
        }
        return null;
    }
    
    /**
     * Generate verification hash for signatures
     */
    public static function generateVerificationHash($itemId, $staffId, $approvalDateTime = null)
    {
        if (!$itemId || !$staffId) return 'N/A';
        $dateTimeToUse = $approvalDateTime ? $approvalDateTime : date('Y-m-d H:i:s');
        return strtoupper(substr(md5(sha1($itemId . $staffId . $dateTimeToUse)), 0, 16));
    }

    /**
     * Get the approval date for a given staff ID and/or approval order from approval trails
     */
    public static function getApprovalDate($staffId, $approvalTrails, $order)
    {
        // Try to find approval by staff_id and approval_order first
        $approval = $approvalTrails
            ->where('approval_order', $order)
            ->where('staff_id', $staffId)
            ->sortByDesc('created_at')
            ->first();

        // If not found, try to find by oic_staff_id and approval_order
        if (!$approval) {
            $approval = $approvalTrails
                ->where('approval_order', $order)
                ->where('oic_staff_id', $staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        // If still not found, try to find by staff_id only
        if (!$approval) {
            $approval = $approvalTrails
                ->where('staff_id', $staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        // If still not found, try to find by oic_staff_id only
        if (!$approval) {
            $approval = $approvalTrails
                ->where('oic_staff_id', $staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        $date = ($approval && isset($approval->created_at))
            ? (is_object($approval->created_at) ? $approval->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($approval->created_at)))
            : date('j F Y H:i');
        return $date;
    }

    /**
     * Render approver information with OIC support
     */
    public static function renderApproverInfo($approver, $role, $section, $context)
    {
        $isOic = isset($approver['is_oic']) ? $approver['is_oic'] : isset($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        $name = $isOic ? $staff['name'] . ' (OIC)' : trim(($staff['title'] ?? '') . ' ' . ($staff['name'] ?? ''));
        echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
        echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';

        // Add OIC watermark if applicable
        if ($isOic) {
            echo '<div style="position: relative; display: inline-block;">';
            echo '<span style="position: absolute; top: -5px; right: -10px; background: #6b7280; color: white; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; transform: rotate(15deg);">OIC</span>';
            echo '</div>';
        }

        // Show division name for FROM section
        if ($section === 'from') {
            $divisionName = $context->division->division_name ?? '';
            if (!empty($divisionName)) {
                echo '<div class="approver-title">' . htmlspecialchars($divisionName) . '</div>';
            }
        }
    }

    /**
     * Render signature with OIC support
     */
    public static function renderSignature($approver, $order, $approvalTrails, $item)
    {
        $isOic = isset($approver['is_oic']) ? $approver['is_oic'] : isset($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        $staffId = $staff['id'] ?? null;

        $approvalDate = self::getApprovalDate($staffId, $approvalTrails, $order);

        echo '<div style="line-height: 1.2;">';
        
        if (isset($staff['signature']) && !empty($staff['signature'])) {
            echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small> ';
            echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $staff['signature']) . '" alt="Signature">';
        } else {
            echo '<small style="color: #666; font-style:normal;">Signed By: ' . htmlspecialchars($staff['work_email'] ?? 'Email not available') . '</small>';
        }
        
        echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
        echo '<div class="signature-hash">Hash: ' . htmlspecialchars(self::generateVerificationHash($item->id, $staffId, $approvalDate)) . '</div>';
        echo '</div>';
    }

    /**
     * Render budget approver info with OIC support
     */
    public static function renderBudgetApproverInfo($approval, $label = '')
    {
        if (!$approval) {
            echo 'N/A';
            return;
        }

        $isOic = !empty($approval->oic_staff_id);
        $staff = $isOic ? $approval->oicStaff : $approval->staff;
        
        if (!$staff) {
            echo 'N/A';
            return;
        }

        $name = $staff->title . ' ' . $staff->fname . ' ' . $staff->lname . ' ' . $staff->oname;
        if ($isOic) {
            $name .= ' (OIC)';
        }

        echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';

        // Get role from workflow definition instead of job_name
        $role = 'N/A';
        if (isset($approval->workflowDefinition) && $approval->workflowDefinition) {
            $role = $approval->workflowDefinition->role ?? 'N/A';
        } elseif (isset($approval->role)) {
            $role = $approval->role;
        }
        echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';
    
        if($approval->workflowDefinition->approval_order == 1){
            echo '<div class="approver-title">' . htmlspecialchars($staff->division_name ?? 'N/A') . '</div>';
        }
        echo '<span class="fill line"></span>';
    }

    /**
     * Render budget signature with OIC support
     */
    public static function renderBudgetSignature($approval, $item, $label = '')
    {
        if (!$approval) {
            echo '<span style="color:#aaa;">N/A</span>';
            return;
        }

        $isOic = !empty($approval->oic_staff_id);
        $staff = $isOic ? $approval->oicStaff : $approval->staff;
        
        if (!$staff) {
            echo '<span style="color:#aaa;">N/A</span>';
            return;
        }

        $name = $staff->title . ' ' . $staff->fname . ' ' . $staff->lname . ' ' . $staff->oname;
        if ($isOic) {
            $name .= ' (OIC)';
        }

        echo '<div style="line-height: 1.2;">';
        
        echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small><br>';
        
        if (!empty($staff->signature)) {
            echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $staff->signature) . '" alt="Signature">';
        } else {
            echo '<small style="color: #666; font-style: normal;">' . htmlspecialchars($staff->work_email ?? 'Email not available') . '</small>';
        }
        
        $approvalDate = is_object($approval->created_at) ? $approval->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($approval->created_at));
        echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
        
        $hash = self::generateVerificationHash($item->id, $isOic ? $approval->oic_staff_id : $approval->staff_id, $approval->created_at);
        echo '<div class="signature-hash">Hash: ' . htmlspecialchars($hash) . '</div>';
         
        // Add OIC watermark if applicable
        if ($isOic) {
            echo '<div style="position: relative; display: inline-block; margin-top: 5px;">';
            echo '<span style="position: absolute; top: -5px; right: -10px; background: #6b7280; color: white; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; transform: rotate(15deg);">OIC</span>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Get latest approval for a specific order
     */
    public static function getLatestApprovalForOrder($approvalTrails, $order)
    {
        $approvals = $approvalTrails->where('approval_order', $order);
        return $approvals->sortByDesc('created_at')->first();
    }

    /**
     * Generate short code from division name
     */
    public static function generateShortCodeFromDivision(string $name): string
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
     * Fetch approvers from approval trails with single record per order
     */
    public static function fetchApproversFromTrails($modelId, $modelType, $divisionId = null, $workflowId = null)
    {
        $approvers = [];
        
        // Fetch approval trails for the model with staff and OIC staff
        $query = \App\Models\ApprovalTrail::where('model_id', $modelId)
            ->where('model_type', $modelType)
            ->with(['staff', 'oicStaff']);
            
        // Add workflow_id filter if provided to avoid mixing up approvers from different workflows
        if ($workflowId) {
            $query->where('forward_workflow_id', $workflowId);
        }
        
        $approvalTrails = $query->orderBy('approval_order')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Group by approval order, taking only the most recent for each order
        $processedOrders = [];
        foreach ($approvalTrails as $trail) {
            $order = $trail->approval_order;
            
            // Skip if we already have the most recent approval for this order
            if (in_array($order, $processedOrders)) {
                continue;
            }
            
            // Determine if this is an OIC approver
            $isOic = !empty($trail->oic_staff_id);
            
            // Get the correct workflow definition based on approval_order and workflow_id
            $workflowDefinition = null;
            if ($trail->forward_workflow_id) {
                $workflowDefinition = \App\Models\WorkflowDefinition::where('approval_order', $order)
                    ->where('workflow_id', $trail->forward_workflow_id)
                    ->first();
            }
            
            $approver = [
                'staff' => $trail->staff ? [
                    'id' => $trail->staff->id,
                    'name' => $trail->staff->fname . ' ' . $trail->staff->lname,
                    'title' => $trail->staff->title,
                    'work_email' => $trail->staff->work_email,
                    'signature' => $trail->staff->signature
                ] : null,
                'oic_staff' => $trail->oicStaff ? [
                    'id' => $trail->oicStaff->id,
                    'name' => $trail->oicStaff->fname . ' ' . $trail->oicStaff->lname,
                    'title' => $trail->oicStaff->title,
                    'work_email' => $trail->oicStaff->work_email,
                    'signature' => $trail->oicStaff->signature
                ] : null,
                'role' => $workflowDefinition ? $workflowDefinition->role : ($trail->role ?? 'Approver'),
                'order' => $order,
                'is_oic' => $isOic
            ];
            
            // Store as single record (not array) for each order
            $approvers[$order] = [$approver];
            $processedOrders[] = $order;
        }
        
        // Also fetch division head if available (only as fallback when no level 1 approver)
        if (!isset($approvers[1]) && $divisionId) {
            $division = \App\Models\Division::find($divisionId);
            if ($division && $division->head_of_division_id) {
                $divisionHead = \App\Models\Staff::find($division->head_of_division_id);
                if ($divisionHead) {
                    // Add division head as a single fallback approver
                    $approvers['division_head'] = [[
                        'staff' => [
                            'id' => $divisionHead->id,
                            'name' => $divisionHead->fname . ' ' . $divisionHead->lname,
                            'title' => $divisionHead->title,
                            'work_email' => $divisionHead->work_email,
                            'signature' => $divisionHead->signature
                        ],
                        'oic_staff' => null,
                        'role' => 'Head of Division',
                        'order' => 'division_head',
                        'is_oic' => false
                    ]];
                }
            }
        }

        return $approvers;
    }

    /**
     * Organize workflow steps by memo_print_section for dynamic memo rendering
     * This is a reusable helper for all memo print templates
     */
    public static function organizeWorkflowStepsBySection($workflowSteps)
    {
        $organizedSteps = [
            'to' => [],
            'through' => [],
            'from' => [],
            'others' => []
        ];

        foreach ($workflowSteps as $step) {
            $section = $step['memo_print_section'] ?? 'through';
            $organizedSteps[$section][] = $step;
        }

        // Sort each section by print_order first, then by approval order as fallback
        foreach ($organizedSteps as $section => $steps) {
            usort($steps, function($a, $b) {
                $aPrintOrder = $a['print_order'] ?? 0;
                $bPrintOrder = $b['print_order'] ?? 0;
                if ($aPrintOrder != $bPrintOrder) {
                    return $aPrintOrder <=> $bPrintOrder;
                }
                return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
            });
            $organizedSteps[$section] = $steps;
        }

        return $organizedSteps;
    }

    /**
     * Get workflow definitions with category filtering for memo printing
     * This is a reusable helper for all memo print templates
     */
    public static function getWorkflowDefinitionsForMemo($workflowId, $divisionCategory = null)
    {
        return \App\Models\WorkflowDefinition::where('workflow_id', $workflowId)
            ->where('is_enabled', 1)
            ->where(function($query) use ($divisionCategory) {
                $query->where('approval_order', '!=', 7)
                      ->orWhere(function($subQuery) use ($divisionCategory) {
                          $subQuery->where('approval_order', 7)
                                   ->where('category', $divisionCategory ?? '');
                      });
            })
            ->orderBy('approval_order')
            ->get();
    }

    /**
     * Organize approvers by section based on approval order and category for memo printing
     * This is a reusable helper for all memo print templates
     */
    public static function organizeApproversBySection($matrixId, $modelType, $divisionId, $workflowId, $divisionCategory = null)
    {
        // Fetch approvers from approval trails
        $approvers = self::fetchApproversFromTrails($matrixId, $modelType, $divisionId, $workflowId);

        // Organize approvers by section based on approval order and category
        $organizedApprovers = [
            'to' => [],
            'through' => [],
            'from' => [],
            'others' => []
        ];

        if ($workflowId) {
            // Get workflow definitions with category filtering
            $workflowDefinitions = self::getWorkflowDefinitionsForMemo($workflowId, $divisionCategory);

            foreach ($workflowDefinitions as $definition) {
                $section = $definition->memo_print_section ?? 'through';
                
                // Map approval orders to sections
                if ($definition->approval_order == 10) {
                    $section = 'to';
                } elseif (in_array($definition->approval_order, [7, 8, 9])) {
                    $section = 'through';
                } elseif ($definition->approval_order == 1) {
                    $section = 'from';
                }

                // Ensure section is valid, default to 'through' if not
                if (!in_array($section, ['to', 'through', 'from', 'others'])) {
                    $section = 'through';
                }

                // Get approvers for this definition from approval trails
                $sectionApprovers = [];
                if (isset($approvers[$definition->approval_order])) {
                    $sectionApprovers = $approvers[$definition->approval_order];
                } elseif ($definition->approval_order == 1 && isset($approvers['division_head'])) {
                    $sectionApprovers = $approvers['division_head'];
                }

                if (!empty($sectionApprovers)) {
                    if (!isset($organizedApprovers[$section])) {
                        $organizedApprovers[$section] = [];
                    }
                    $organizedApprovers[$section] = array_merge($organizedApprovers[$section], $sectionApprovers);
                }
            }
        }

        return $organizedApprovers;
    }
}
