<html>
<head>
<style>
        /* Color variables */
        * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; color: #0f172a; }
         body { 
         font-size: 14px; 
         font-family: "freesans",arial, sans-serif; 
         background: #FFFFFF; 
         margin: 40px; 
         line-height: 1.8 !important;
         letter-spacing: 0.02em;
         word-spacing: 0.08em;
         margin-bottom: 1.2em;
     }

        /* Document container */
        .container {
            max-width: 900px;
            margin: 20px auto;
            background: white !important;
            padding: 25px;
            box-shadow: none;
            border-radius: 0;
            border: 1px solid #ddd;
        }
        
        /* Header section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #007e33;
        }
        
        .document-title {
            font-size: 22px; 
            text-align: center;
            font-weight: bold; 
            color: #007e33; 
            letter-spacing: 0.5px;
            margin: 0;
        }
        
        .address {
            float: right;
            text-align: right;
            font-size: 13px;
            background: #FFFFFF !important;
            padding: 12px;
            border-radius: 4px;
            max-width: 300px;
            border: 1px solid #e0e0e0;
        }
        .contact-info {
            float: right;
            text-align: left;
            font-size: 13px;
            background: #FFFFFF !important;
            padding: 12px;
            max-width: 300px;
        }
        
        .address div {
            text-align: left;
        }
        
        /* Form table */
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: #fff !important;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #ddd;
        }
        
        .form-table td {
            padding: 10px 12px;
            vertical-align: top;
            border-bottom: 1px solid #eaeaea;
        }
        
        .label {
            font-weight: bold;
            width: 15%;
            background-color: #f8f9fa !important;
            color: #007e33;
        }
        
        .content {
            width: 85%;
        }
        
        /* Section labels */
        .section-label {
            color: #007e33; 
            font-weight: bold; 
            font-size: 16px; 
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        /* Activity brief */
        .activity-brief {
            background-color: #FFFFFF !important;
            padding: 18px;
            border-left: 4px solid #007e33;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: justify;
            border: 1px solid #e0e0e0;
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            font-size: 12px;
            text-align: center;
            color: #777;
            padding-top: 10px;
            border-top: 1px dashed #ccc;
        }
        
        /* Reference number */
        .ref-number {
            font-family: monospace;
            background-color: #e8f5e9 !important;
            padding: 4px 8px;
            border-radius: 3px;
            border: 1px dashed #007e33;
            color: #007e33;
            font-size: 12px;
            display: inline-block;
            margin-top: 3px;
        }
        
        /* Budget table styling */
        .bordered-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background: #fff !important;
            border: 1px solid #ddd;
        }
        
        .bordered-table th, .bordered-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .bg-highlight {
            background-color: #f8f9fa !important;
        }
        
        .text-right {
            text-align: right;
        }
        
        .mb-15 {
            margin-bottom: 15px;
        }
        
        /* Signature table */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background: #fff !important;
        }
        
        .sig-table td {
            padding: 8px 12px;
            vertical-align: top;
            border: none;
        }
        
        .sig-table td:first-child {
            width: 20%;
            font-weight: 600;
            color: #007e33;
        }
        
        .sig-table td:nth-child(2) {
            width: 40%;
        }
        
        .sig-table td:nth-child(3) {
            width: 40%;
        }
        
        .approver-name {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .approver-title {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .signature-image {
            max-height: 40px;
            margin-bottom: 3px;
        }
        
        .signature-date {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
            margin-bottom: 2px;
        }
        
        .signature-hash {
            font-size: 9px;
            color: #888;
            font-family: monospace;
            margin-top: 1px;
        }
        
        .fill {
            display: block;
            min-height: 40px;
            margin-top: 5px;
        }
        
        .line {
            border-bottom: 1px solid #000;
            margin-top: 10px;
            width: 100%;
        }

        background-text{
            text-align: justify;
            text-justify: inter-word;
            word-spacing: 0.1em;
            margin-bottom: 10px;
            line-height: 1.6;
            font-size: 13px !important;
            font-style: regular !important;
        }
        
        /* Page break for printing */
        .page-break {
            page-break-before: always;
        }
        
        /* Print-specific styles */
        @media print {
            body, .container {
                background: white !important;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            
            .container {
                border: none;
            }
            
            .address, .label, .bg-highlight, .ref-number {
                background: white !important;
                color:rgb(53, 54, 53);
            }
            
            .form-table, .bordered-table {
                border: 1px solid #000 !important;
            }
            
            .form-table td, .bordered-table th, .bordered-table td {
                border: 1px solid #ddd !important;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
            }
            .address {
                text-align: left;
                margin-top: 15px;
                max-width: 100%;
            }
            .address div {
                text-align: center;
            }
            .form-table td {
                display: block;
                width: 100%;
            }
            .label {
                background-color: transparent !important;
                padding-bottom: 5px;
                font-weight: 700;
            }
        }
    </style>
</head>
<body>
<div class="container">
  <!-- Document Title -->
  <h1 class="document-title">Activity Request Form</h1>
  
  <?php
    // Helper functions to safely access staff data
    function getStaffEmail($approver) {
      if (isset($approver['staff']) && isset($approver['staff']['work_email'])) {
        return $approver['staff']['work_email'];
      } elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['work_email'])) {
        return $approver['oic_staff']['work_email'];
      }
      return null;
    }
    
    function getStaffId($approver) {
      if (isset($approver['staff']) && isset($approver['staff']['id'])) {
        return $approver['staff']['id'];
      } elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['id'])) {
        return $approver['oic_staff']['id'];
      }
      return null;
    }
    
    function generateVerificationHash($activityId, $staffId, $approvalDateTime = null) {
      if (!$activityId || !$staffId) return 'N/A';
      $dateTimeToUse = $approvalDateTime ? $approvalDateTime : date('Y-m-d H:i:s');
      return strtoupper(substr(md5(sha1($activityId . $staffId . $dateTimeToUse)), 0, 16));
    }

    /**
     * Get the approval date for a given staff ID and/or approval order from the matrix approval trails.
     * Returns a formatted date string if found, otherwise returns the current date/time.
     *
     * @param mixed $staffId
     * @param iterable $matrixApprovalTrails
     * @param mixed $order
     * @return string
     */
    function getApprovalDate($staffId, $matrixApprovalTrails, $order) {
        // Try to find approval by staff_id and approval_order first
      $approval = $matrixApprovalTrails
        ->where('approval_order', $order)
        ->where('staff_id', $staffId)
        ->sortByDesc('created_at')
        ->first();

        // If not found, try to find by oic_staff_id and approval_order
        if (!$approval) {
            $approval = $matrixApprovalTrails
                ->where('approval_order', $order)
                ->where('oic_staff_id', $staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        // If still not found, try to find by staff_id only
        if (!$approval) {
            $approval = $matrixApprovalTrails
                ->where('staff_id', $staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        // If still not found, try to find by oic_staff_id only
        if (!$approval) {
            $approval = $matrixApprovalTrails
                ->where('oic_staff_id', $staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        $date = ($approval && isset($approval->created_at))
            ? (is_object($approval->created_at) ? $approval->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($approval->created_at)))
            : date('j F Y H:i');
      return $date;
    }

    // Helper function to render approver info
    function renderApproverInfo($approver, $role, $section, $matrix) {
        $isOic = isset($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        $name = $isOic ? $staff['name'] . ' (OIC)' : trim(($staff['title'] ?? '') . ' ' . ($staff['name'] ?? ''));
        echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
        echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';

        // Add OIC watermark if applicable
        if ($isOic) {
            echo '<div style="position: relative; display: inline-block;">';
            echo '<span style="position: absolute; top: -5px; right: -10px; background: #ff6b6b; color: white; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; transform: rotate(15deg);">OIC</span>';
            echo '</div>';
        }

        // Show division name for FROM section
        if ($section === 'from') {
            $divisionName = $matrix->division->division_name ?? '';
            if (!empty($divisionName)) {
                echo '<div class="approver-title">' . htmlspecialchars($divisionName) . '</div>';
            }
        }
    }

    // Helper function to render signature
    function renderSignature($approver, $order, $matrix_approval_trails, $sourceModel) {
        $isOic = isset($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        $staffId = $staff['id'] ?? null;

        $approvalDate = getApprovalDate($staffId, $matrix_approval_trails, $order);

        echo '<div style="line-height: 1.2;">';
        
        if (isset($staff['signature']) && !empty($staff['signature'])) {
            echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small> ';
            echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $staff['signature']) . '" alt="Signature">';
        } else {
            echo '<small style="color: #666; font-style:normal;">Signed By: ' . htmlspecialchars($staff['work_email'] ?? 'Email not available') . '</small>';
        }
        
        echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
        echo '<div class="signature-hash">Hash: ' . htmlspecialchars(generateVerificationHash($sourceModel->id, $staffId, $approvalDate)) . '</div>';
        echo '</div>';
    }

    // Helper function to get latest approval for a specific order
    function getLatestApprovalForOrder($activityApprovalTrails, $order) {
        $approvals = $activityApprovalTrails->where('approval_order', $order);
        return $approvals->sortByDesc('created_at')->first();
    }

    // Helper function to render budget signature with OIC support
   // dd($sourceData);
    function renderBudgetSignature($approval, $sourceModel, $responsible_person = false, $sourceData = []) {
        // If responsible person is provided, get info from $sourceData['responsible_person']
        if ($responsible_person && is_array($sourceData) && isset($sourceData['responsible_person'])) {
            $person = $sourceData['responsible_person'];
            $name = trim(
                ($person['title'] ?? '') . ' ' .
                ($person['fname'] ?? '') . ' ' .
                ($person['lname'] ?? '') . ' ' .
                ($person['oname'] ?? '')
            );
            echo '<div style="line-height: 1.2;">';
            echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small><br>';
            if (!empty($person['signature'])) {
                echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $person['signature']) . '" alt="Signature">';
            } else {
                echo '<small style="color: #666; font-style: normal;">' . htmlspecialchars($person['work_email'] ?? 'Email not available') . '</small>';
            }
            // Use created_at from responsible_person for signing date/time
            $approvalDate = '';
            if (!empty($person['created_at'])) {
                if ($person['created_at'] instanceof DateTime) {
                    $approvalDate = $person['created_at']->format('j F Y H:i');
                } else {
                    $approvalDate = date('j F Y H:i', strtotime($person['created_at']));
                }
            }
            echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
            // Hash: use staff_id and created_at from responsible_person
            $hash = generateVerificationHash(
                $sourceModel->id,
                $person['id'] ?? '',
                $person['created_at'] ?? ''
            );
            echo '<div class="signature-hash">Hash: ' . htmlspecialchars($hash) . '</div>';
            echo '</div>';
            return;
        }

        // Default: use approval object as before
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
        
        $hash = generateVerificationHash($sourceModel->id, $isOic ? $approval->oic_staff_id : $approval->staff_id, $approval->created_at);
        echo '<div class="signature-hash">Hash: ' . htmlspecialchars($hash) . '</div>';
         
        // Add OIC watermark if applicable
        if ($isOic) {
            echo '<div style="position: relative; display: inline-block; margin-top: 5px;">';
            echo '<span style="position: absolute; top: -5px; right: -10px; background: #ff6b6b; color: white; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; transform: rotate(15deg);">OIC</span>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    // Helper function to render budget approver info from PrintHelper data
    function renderBudgetApproverInfoFromPrintHelper($approver) {
        $isOic = isset($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        
        if ($staff) {
            $name = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '') . ' ' . ($staff['oname'] ?? ''));
            $title = $staff['title'] ?? '';
            $role = $approver['role'] ?? 'Approver';
            
            echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
            echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';
            
            if ($title) {
                echo '<div class="approver-title">' . htmlspecialchars($title) . '</div>';
            }
        }
    }
    
    // Helper function to render budget signature from PrintHelper data
    function renderBudgetSignatureFromPrintHelper($approver, $sourceModel) {
        $isOic = isset($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        
        if ($staff) {
            $name = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '') . ' ' . ($staff['oname'] ?? ''));
            $role = $approver['role'] ?? 'Approver';
            
            echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
            echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';
            
            // Add signature if available
            if (!empty($staff['signature'])) {
                echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $staff['signature']) . '" alt="Signature">';
            } else {
                echo '<div class="signature-placeholder">_________________</div>';
            }
        }
    }

    // Helper function to render budget approver info with OIC support or responsible person info
    function renderBudgetApproverInfo($approval = null, $responsible_person = false) {
        // If $responsible_person is provided and is an object (Staff), render their info
        if ($responsible_person && is_object($responsible_person)) {
            $staff = $responsible_person;
            $name = trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? ''));
            echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
            // Use job_name as role for responsible person
            $role = $staff->job_name ?? 'N/A';
            echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';
            // Show division name if available
            if (!empty($staff->division_name)) {
                echo '<div class="approver-title">' . htmlspecialchars($staff->division_name) . '</div>';
            }
            echo '<span class="fill line"></span>';
            return;
        }

        // Otherwise, render approval info as before
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

        $name = trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? ''));
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
    
        if (isset($approval->workflowDefinition) && $approval->workflowDefinition && $approval->workflowDefinition->approval_order == 1) {
            echo '<div class="approver-title">' . htmlspecialchars($staff->division_name ?? 'N/A') . '</div>';
        }
        echo '<span class="fill line"></span>';
    }

    // Generate file reference once
    $activity_refernce = 'N/A';
    if (isset($sourceModel)) {
        $divisionName = $sourceData['division']->division_name ?? '';
        if (!function_exists('generateShortCodeFromDivision')) {
            function generateShortCodeFromDivision(string $name): string {
                $ignore = ['of', 'and', 'for', 'the', 'in'];
                $words = preg_split('/\s+/', strtolower($name));
                $initials = array_map(function ($word) use ($ignore) {
                    return in_array($word, $ignore) ? '' : strtoupper($word[0]);
                }, $words);
                return implode('', array_filter($initials));
            }
        }
        $shortCode = $divisionName ? generateShortCodeFromDivision($divisionName) : 'DIV';
        $year = date('Y', strtotime($sourceModel->created_at ?? 'now'));
        $activityId = $sourceModel->id ?? 'N/A';
        $activity_refernce = "AU/CDC/{$shortCode}/IM/Q{$requestARF->quarter}/{$year}/{$activityId}";
    } 
    
    // Calculate budget total for display
    $totalBudget = 0;
    if ($requestARF->model_type === 'App\\Models\\Activity') {
        $budgetItems = $sourceData['budget_breakdown'] ?? [];
        // Decode JSON string if needed
        if (is_string($budgetItems)) {
            $budgetItems = json_decode($budgetItems, true) ?? [];
        }
        if (!empty($budgetItems) && is_array($budgetItems)) {
            // Check if it has grand_total first
            if (isset($budgetItems['grand_total'])) {
                $totalBudget = floatval($budgetItems['grand_total']);
            } else {
                // Process individual items
                foreach ($budgetItems as $key => $item) {
                    if ($key === 'grand_total') {
                        $totalBudget = floatval($item);
                    } elseif (is_array($item)) {
                        foreach ($item as $budgetItem) {
                            if (is_object($budgetItem)) {
                                $totalBudget += $budgetItem->unit_cost * $budgetItem->units * $budgetItem->days;
                            } elseif (is_array($budgetItem)) {
                                $totalBudget += floatval($budgetItem['unit_cost'] ?? 0) * floatval($budgetItem['units'] ?? 0) * floatval($budgetItem['days'] ?? 0);
                            }
                        }
                    }
                }
            }
        }
    } else {
        // For memos, use budget_breakdown array
        $budget = $sourceData['budget_breakdown'] ?? [];
        // Decode JSON string if needed
        if (is_string($budget)) {
            $budget = json_decode($budget, true) ?? [];
        }
        if (!empty($budget) && is_array($budget)) {
            // Check if it's a simple array of budget items
            if (isset($budget[0]) && is_array($budget[0])) {
                // Simple array structure: [0 => {item1}, 1 => {item2}]
                foreach ($budget as $item) {
                    $totalBudget += floatval(
                        $item['total'] ?? ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1),
                    );
                }
            } else {
                // Keyed structure: {fund_code_id => [items]}
                foreach ($budget as $key => $item) {
                    if ($key === 'grand_total') {
                        $totalBudget = floatval($item);
                    } elseif (is_array($item)) {
                        foreach ($item as $budgetItem) {
                            $totalBudget += floatval(
                                $budgetItem['total'] ??
                                    ($budgetItem['unit_price'] ?? 0) * ($budgetItem['quantity'] ?? 1),
                            );
                        }
                    }
                }
            }
        }
    }
    
    ?>


    
<div class="" style="display: flex; margin-left:400px;">
            
            <div class="contact-info">
                AFRICA CDC<br>
                Tel: +251 11 551 7700<br>
                P.O.Box 3243, Addis Ababa, Ethiopia<br>
                Communications@africacdc.org
            </div>
</div>
       

        <table class="form-table">
            <tr>
                <td class="label">Date:</td>
                <td class="content"><?php echo $requestARF->request_date ? $requestARF->request_date->format('d/m/Y g:i A') : date('d/m/Y g:i A'); ?></td>
                <td class="label">REF:</td>
                <td class="content"><?php echo htmlspecialchars($requestARF->document_number ?? $requestARF->arf_number); ?></td>
            </tr>
            <tr>
                <td class="label">Payee:</td>
                <td class="content">Africa CDC</td>
                <td class="label">Funding Source:</td>
                <td class="content"><?php echo htmlspecialchars($requestARF->funder->name ?? 'N/A'); ?></td>
            </tr>
            <tr>

               
                <td class="label">Partner:</td>
                <td class="content">
                    <?php
                    // Extract partner from budget breakdown
                    $partner = 'N/A';
                    if (!empty($budgetBreakdown) && is_array($budgetBreakdown)) {
                        // Get all fund code IDs from budget breakdown (excluding grand_total)
                        $fundCodeIds = array_filter(array_keys($budgetBreakdown), function($key) {
                            return $key !== 'grand_total';
                        });
                        
                        if (!empty($fundCodeIds)) {
                            $partners = [];
                            foreach ($fundCodeIds as $fundCodeId) {
                                if (isset($fundCodes[$fundCodeId]) && $fundCodes[$fundCodeId]->funder) {
                                    $funderName = $fundCodes[$fundCodeId]->funder->name ?? null;
                                    if ($funderName && !in_array($funderName, $partners)) {
                                        $partners[] = $funderName;
                                    }
                                }
                            }
                            
                            if (!empty($partners)) {
                                $partner = implode(', ', $partners);
                            }
                        }
                    }
                    // Fallback to requestARF partner if budget breakdown doesn't have funder info
                    if ($partner === 'N/A' && isset($requestARF->partner)) {
                        $partner = $requestARF->partner;
                    }
                    echo htmlspecialchars($partner);
                    ?>
                </td>
                <td class="label">Code:</td>
                <td class="content"><?php echo htmlspecialchars($requestARF->extramural_code ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td class="label">Project Title:</td>
                <td class="content" colspan="3"><?php echo htmlspecialchars(to_sentence_case($requestARF->activity_title)); ?></td>
            </tr>
            <tr>
                <td class="label">Currency:</td>
                <td class="content">USD</td>
                <td class="label">Amount:</td>
                <td class="content">$<?php echo number_format($totalBudget ?? $requestARF->total_amount ?? 0, 2); ?></td>
            </tr>
        </table>

        <div class="section-label">Activity Brief</div>
    
        <div class="background-text justify-text"><?php echo $sourceModel['background']; ?></div>

 
    
    <?php
    // Process internal participants like the web view does
    $internalParticipants = $sourceData['internal_participants'] ?? [];
    if (is_string($internalParticipants)) {
        $internalParticipants = json_decode($internalParticipants, true) ?? [];
    }
    if (!is_array($internalParticipants)) {
        $internalParticipants = [];
    }
    ?>
    
    <?php if (!empty($internalParticipants)): ?>
    <div class="section-label mb-15"><strong>Internal Participants</strong></div>     
    <table class="bordered-table mb-15">
                            <thead>
                                <tr>
                                    <td class="bg-highlight">#</td>
                                    <th class="bg-highlight">Name</th>
                                    <th class="bg-highlight">Division</th>
                                    <th class="bg-highlight">Job Title</th>
                                    <th class="bg-highlight">Duty Station</th>
                                    <th class="bg-highlight">Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $count = 1;
                                foreach($internalParticipants as $participantId => $participantData):
                                    // Fetch staff data from database like the web view does
                                    $staff = \App\Models\Staff::where('staff_id', $participantId)
                                        ->with(['division'])
                                        ->first();
                                    
                                    if ($staff):
                                        $participantName = $staff->fname . ' ' . $staff->lname;
                                        $division = $staff->division ? $staff->division->division_name : 'N/A';
                                        $dutyStation = $staff->duty_station_name ?? $staff->duty_station ?? 'N/A';
                                        $days = is_array($participantData) ? ($participantData['days'] ?? 1) : 1;
                                ?>
                                    <tr>
                                        <td><?php echo $count; ?></td>
                                        <td><?php echo htmlspecialchars($participantName); ?></td>
                                        <td><?php echo htmlspecialchars($division); ?></td>
                                        <td><?php echo htmlspecialchars($staff->job_name ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($dutyStation); ?></td>
                                        <td><?php echo htmlspecialchars($days); ?></td>
                                    </tr>
                                <?php 
                                    $count++;
                                    endif;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
    <?php endif; ?>


    <div class="page-break"></div>
              <div class="section-label mb-15">Budget Details</div>
         
             <?php 
             $grandTotal = 0;
             // Decode JSON string if needed
             if (is_string($budgetBreakdown)) {
                 $budgetBreakdown = json_decode($budgetBreakdown, true) ?? [];
             }
             if (!empty($budgetBreakdown) && is_array($budgetBreakdown)): 
                 foreach($budgetBreakdown as $fundCodeId => $budgetItems):
                     if (is_array($budgetItems) && !empty($budgetItems)):
                         $fundCode = $fundCodes[$fundCodeId] ?? null;
                         $fundCodeName = $fundCode ? $fundCode->activity . ' - ' . $fundCode->code . ' - (' . $fundCode->fundType->name . ') - ' . ($fundCode->funder->name ?? 'N/A') : 'Fund Code ' . $fundCodeId;
             ?>
                 <h5 style="font-weight: 600; color: #006633; background: #f9fafb; padding: 8px; margin: 10px 0 5px 0; border-left: 4px solid #119A48;"><?php echo htmlspecialchars($fundCodeName); ?></h5>

                <div>
                    <table class="bordered-table mb-15">
                        <thead>
                            <tr>
                                <th class="bg-highlight">#</th>
                                <th class="bg-highlight">Cost Item</th>
                                <th class="bg-highlight">Unit Cost</th>
                                <th class="bg-highlight">Units</th>
                                <th class="bg-highlight">Days</th>
                                <th class="bg-highlight">Total</th>
                                <th class="bg-highlight">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                              $count = 1;
                              $fundTotal = 0;
                            ?>
                           
                            <?php foreach($budgetItems as $item): ?>
                                <?php
                                    if (is_array($item)) {
                                        $cost = $item['cost'] ?? '';
                                        $unitCost = (float)($item['unit_cost'] ?? 0);
                                        $units = (float)($item['units'] ?? 0);
                                        $days = (float)($item['days'] ?? 0);
                                        $description = $item['cost'] ?? $item['description'] ?? '';
                                        $total = $unitCost * $units * $days;
                                    } else {
                                        $cost = $item->cost ?? '';
                                        $unitCost = (float)($item->unit_cost ?? 0);
                                        $units = (float)($item->units ?? 0);
                                        $days = (float)($item->days ?? 0);
                                        $description = $item->description ?? '';
                                        $total = $unitCost * $units * $days;
                                    }
                                    $fundTotal += $total;
                                ?>
                                <tr>
                                    <td><?php echo $count; ?></td>
                                    <td class="text-right"><?php echo htmlspecialchars($cost); ?></td>
                                    <td class="text-right"><?php echo number_format($unitCost, 2); ?></td>
                                    <td class="text-right"><?php echo $units; ?></td>
                                    <td class="text-right"><?php echo $days; ?></td>
                                    <td class="text-right"><?php echo number_format($total, 2); ?></td>
                                    <td><?php echo htmlspecialchars($description); ?></td>
                                </tr>
                            <?php 
                                $count++;
                                endforeach; 
                            ?>
                            
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="bg-highlight text-right" colspan="5">Fund Total</th>
                                <th class="bg-highlight text-right"><?php echo number_format($fundTotal, 2); ?></th>
                                <th class="bg-highlight"></th>
                            </tr>
                        </tfoot>
                    </table>
                   
                </div>
                <?php 
                    $grandTotal += $fundTotal;
                    endif;
                endforeach; 
                ?>
                
            
            <?php endif; ?>

            <!-- Grand Total Display -->
            <?php if ($totalBudget > 0): ?>
                <div style="margin-top: 20px; text-align: right; padding: 15px; background-color: #f8f9fa; border: 2px solid #007e33; border-radius: 5px;">
                    <h3 style="margin: 0; color: #007e33; font-size: 18px; font-weight: bold;">
                        Grand Total: $<?php echo number_format($totalBudget, 2); ?>
                    </h3>
                </div>
            <?php endif; ?>

<?php
    // Use PrintHelper for consistent approver display
    use App\Helpers\PrintHelper;
    
    // Get division category for workflow filtering
    $divisionCategory = null;
    if (isset($sourceData['division']) && isset($sourceData['division']->category)) {
        $divisionCategory = $sourceData['division']->category;
    }
    
    // Get source model type and ID
    $sourceModelType = $requestARF->model_type ?? null;
    $sourceModelId = $requestARF->source_id ?? null;
    $sourceDivisionId = $sourceData['division']->id ?? null;
    $sourceWorkflowId = $sourceData['division']->workflow_id ?? $requestARF->forward_workflow_id ?? null;
    
    // Organize approvers by section using helper (same as other memo types)
    $organizedApprovers = [];
    if ($sourceModelType && $sourceModelId && $sourceWorkflowId) {
        $organizedApprovers = PrintHelper::organizeApproversBySection(
            $sourceModelId,
            $sourceModelType,
            $sourceDivisionId,
            $sourceWorkflowId,
            $divisionCategory
        );
    }
    
    // Get approval trails based on source model type
    $sourceApprovalTrails = collect();
    
    // Ensure sourceApprovalTrails is always a collection
    if (!isset($sourceApprovalTrails) || !is_object($sourceApprovalTrails) || !method_exists($sourceApprovalTrails, 'where')) {
        $sourceApprovalTrails = collect();
    }
    
    if ($sourceModelType === 'App\\Models\\Activity') {
        // Check if it's a single memo activity
        $isSingleMemo = isset($sourceData['is_single_memo']) ? $sourceData['is_single_memo'] : false;
        
        if ($isSingleMemo) {
            // For single memo activities, use activity approval trails
            // Ensure we have the approval trails collection with proper relationships loaded
            if (isset($sourceData['approval_trails']) && $sourceData['approval_trails']) {
                $sourceApprovalTrails = $sourceData['approval_trails'];
            } else {
                // Fallback: try to get approval trails directly from the source model
                $sourceApprovalTrails = isset($sourceModel) && method_exists($sourceModel, 'activityApprovalTrails') 
                    ? $sourceModel->activityApprovalTrails 
                    : collect();
            }
        } else {
            // For matrix activities, use matrix approval trails
            if (isset($sourceData['matrix']) && isset($sourceData['matrix']->approvalTrails)) {
                $sourceApprovalTrails = $sourceData['matrix']->approvalTrails;
            } elseif (isset($sourceData['approval_trails'])) {
                $sourceApprovalTrails = $sourceData['approval_trails'];
            } else {
                $sourceApprovalTrails = collect();
            }
        }
    } elseif ($sourceModelType === 'App\\Models\\SpecialMemo' || 
              $sourceModelType === 'App\\Models\\NonTravelMemo' || 
              $sourceModelType === 'App\\Models\\ServiceRequest' || 
              $sourceModelType === 'App\\Models\\ChangeRequest') {
        // For single memos and other memo types, use their approval trails
        if (isset($sourceData['approval_trails']) && $sourceData['approval_trails']) {
            $sourceApprovalTrails = $sourceData['approval_trails'];
        } else {
            // Fallback: try to get approval trails directly from the source model
            $sourceApprovalTrails = isset($sourceModel) && method_exists($sourceModel, 'approvalTrails') 
                ? $sourceModel->approvalTrails 
                : collect();
        }
    } else {
        // Fallback to general approval trails
        $sourceApprovalTrails = $sourceData['approval_trails'] ?? collect();
    }
    
    // Ensure sourceApprovalTrails is a collection (convert if needed)
    if (!is_object($sourceApprovalTrails) || !method_exists($sourceApprovalTrails, 'where')) {
        $sourceApprovalTrails = collect($sourceApprovalTrails ?? []);
    }
    
    $memo_approvers = PrintHelper::getARFApprovers($sourceApprovalTrails, $sourceData['forward_workflow_id'] ?? 1);

    //dd($memo_approvers);
    // Extract specific approvers for easier access
    $grants = $memo_approvers['Grants'] ?? null;
    $chief_of_staff = $memo_approvers['Chief of Staff'] ?? null;
    $directorGeneralApproval = $memo_approvers['Director General'] ?? null;
?>
    <!-- Budget / Certification (table-only, borderless unless specified inline) -->
 <div class="page-break"></div>
    <div class="section-label">Request for Approval</div>
    
    <p><?php echo $sourceModel['activity_request_remarks']; ?></p>


    <!-- Signatures (borderless by default). Last column adds ONLY a left border inline -->
    <table class="sig-table" role="table" aria-label="Approvals">
    <tr>
        <td>Prepared by:</td>
        <td>
          <?php 
          // Get the last approver from approval trails
          $lastApprover = $requestARF->approvalTrails->last();
          if ($lastApprover) {
              // Check if OIC signed instead of regular approver
              $actualSigner = null;
              $isOic = false;
              
              if ($lastApprover->oic_staff_id) {
                  // OIC signed
                  $actualSigner = \App\Models\Staff::find($lastApprover->oic_staff_id);
                  $isOic = true;
              } else {
                  // Regular approver signed
                  $actualSigner = \App\Models\Staff::find($lastApprover->staff_id);
              }
              
              if ($actualSigner) {
                  // Get the role from workflow definition instead of job title
                  $workflowId = $requestARF->forward_workflow_id ?? 1;
                  $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $workflowId)
                      ->where('approval_order', $lastApprover->approval_order)
                      ->first();
                  
                  $role = $workflowDefinition ? $workflowDefinition->role : ($actualSigner->job_name ?? 'N/A');
                  
                  // Render approver info without division, with OIC indicator if applicable
                  $name = trim(($actualSigner->title ?? '') . ' ' . ($actualSigner->fname ?? '') . ' ' . ($actualSigner->lname ?? '') . ' ' . ($actualSigner->oname ?? ''));
                  if ($isOic) {
                      $name .= ' (OIC)';
                  }
                  
                  echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
                  echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';
                  echo '<span class="fill line"></span>';
              } else {
                  renderBudgetApproverInfo(null, $sourceData['responsible_person']);
              }
          } else {
              renderBudgetApproverInfo(null, $sourceData['responsible_person']);
          }
          ?>
        </td>
        <td style="border-left:0px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
          <span class="fill">
            <?php 
            // Use the actual signer for the signature
            if (isset($actualSigner) && $actualSigner) {
                renderBudgetSignature($lastApprover, $sourceModel, false, $sourceData);
            } else {
                renderBudgetSignature(null, $sourceModel, true, $sourceData);
            }
            ?>
          </span>
        </td>
      </tr>
      <tr>
        <td>Reviewed By:</td>
        <td>
          <?php 
          // Use Grants approver with role display
          if (!empty($grants)) {
              $isOic = isset($grants['oic_staff']);
              $staff = $isOic ? $grants['oic_staff'] : $grants['staff'];
              
              if ($staff) {
                  $name = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '') . ' ' . ($staff['oname'] ?? ''));
                  if ($isOic) {
                      $name .= ' (OIC)';
                  }
                  $role = $grants['role'] ?? 'Grants';
                  
                  echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
                  echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';
                  echo '<span class="fill line"></span>';
          } else {
                  renderBudgetApproverInfo(null);
              }
          } else {
              renderBudgetApproverInfo(null);
          }
          ?>
        </td>
        <td style="border-left:0px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
          <span class="fill">
            <?php 
            if (!empty($grants)) {
                $isOic = isset($grants['oic_staff']);
                $staff = $isOic ? $grants['oic_staff'] : $grants['staff'];
                
                if ($staff) {
                    $name = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '') . ' ' . ($staff['oname'] ?? ''));
                    if ($isOic) {
                        $name .= ' (OIC)';
                    }
                    $role = $grants['role'] ?? 'Grants';
                    
                    echo '<div style="line-height: 1.2;">';
                    echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small><br>';
                    if (!empty($staff['signature'])) {
                        echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $staff['signature']) . '" alt="Signature">';
            } else {
                        echo '<small style="color: #666; font-style: normal;">' . htmlspecialchars($staff['work_email'] ?? 'Email not available') . '</small>';
                    }
                    echo '<div class="signature-date">' . htmlspecialchars(date('j F Y H:i')) . '</div>';
                    echo '<div class="signature-hash">Hash: ' . htmlspecialchars(generateVerificationHash($sourceModel->id, $staff['id'] ?? '', date('Y-m-d H:i:s'))) . '</div>';
                    echo '</div>';
                } else {
                    renderBudgetSignature(null, $sourceModel);
                }
            } else {
                renderBudgetSignature(null, $sourceModel);
            }
            ?>
          </span>
        </td>
      </tr>
      <tr>
        <td>Endorsed By:</td>
          <td>
           <?php 
          // Use Chief of Staff approver with role display
          if (!empty($chief_of_staff)) {
              $isOic = isset($chief_of_staff['oic_staff']);
              $staff = $isOic ? $chief_of_staff['oic_staff'] : $chief_of_staff['staff'];
              
              if ($staff) {
                  $name = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '') . ' ' . ($staff['oname'] ?? ''));
                  if ($isOic) {
                      $name .= ' (OIC)';
                  }
                  $role = $chief_of_staff['role'] ?? 'Chief of Staff';
                  
                  echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
                  echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';
                  echo '<span class="fill line"></span>';
           } else {
                  renderBudgetApproverInfo(null);
              }
          } else {
              renderBudgetApproverInfo(null);
           }
           ?>
          </td>
          <td style="border-left:0px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
            <span class="fill">
             <?php 
            if (!empty($chief_of_staff)) {
                // Check if it's a structured array (from PrintHelper) or direct approval object
                if (isset($chief_of_staff['staff']) || isset($chief_of_staff['oic_staff'])) {
                    // Structured data from PrintHelper
                    $isOic = isset($chief_of_staff['oic_staff']);
                    $staff = $isOic ? $chief_of_staff['oic_staff'] : $chief_of_staff['staff'];
                    
                    if ($staff) {
                        $name = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '') . ' ' . ($staff['oname'] ?? ''));
                        if ($isOic) {
                            $name .= ' (OIC)';
                        }
                        
                        echo '<div style="line-height: 1.2;">';
                        echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small><br>';
                        if (!empty($staff['signature'])) {
                            echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $staff['signature']) . '" alt="Signature">';
             } else {
                            echo '<small style="color: #666; font-style: normal;">' . htmlspecialchars($staff['work_email'] ?? 'Email not available') . '</small>';
                        }
                        echo '<div class="signature-date">' . htmlspecialchars(date('j F Y H:i')) . '</div>';
                        echo '<div class="signature-hash">Hash: ' . htmlspecialchars(generateVerificationHash($sourceModel->id, $staff['id'] ?? '', date('Y-m-d H:i:s'))) . '</div>';
                        echo '</div>';
                    } else {
                        renderBudgetSignature(null, $sourceModel);
                    }
                } else {
                    // Direct approval object from getARFApprovers
                    $isOic = !empty($chief_of_staff->oic_staff_id);
                    $staff = $isOic ? $chief_of_staff->oicStaff : $chief_of_staff->staff;
                    
                    if ($staff) {
                        $name = trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? ''));
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
                        $approvalDate = is_object($chief_of_staff->created_at) ? $chief_of_staff->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($chief_of_staff->created_at));
                        echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
                        $hash = generateVerificationHash($sourceModel->id, $isOic ? $chief_of_staff->oic_staff_id : $chief_of_staff->staff_id, $chief_of_staff->created_at);
                        echo '<div class="signature-hash">Hash: ' . htmlspecialchars($hash) . '</div>';
                        echo '</div>';
                    } else {
                        renderBudgetSignature(null, $sourceModel);
                    }
                }
            } else {
                renderBudgetSignature(null, $sourceModel);
             }
             ?>
            </span>
          </td>
      </tr>
      <tr>
        <td>Approved By:</td>
        <td>
          <?php 
          // Use Director General approver with role display
          if (!empty($directorGeneralApproval)) {
              $isOic = isset($directorGeneralApproval['oic_staff']);
              $staff = $isOic ? $directorGeneralApproval['oic_staff'] : $directorGeneralApproval['staff'];
              
              if ($staff) {
                  $name = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '') . ' ' . ($staff['oname'] ?? ''));
                  if ($isOic) {
                      $name .= ' (OIC)';
                  }
                  $role = $directorGeneralApproval['role'] ?? 'Director General';
                  
                  echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
                  echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';
                  echo '<span class="fill line"></span>';
          } else {
                  renderBudgetApproverInfo(null);
              }
          } else {
              renderBudgetApproverInfo(null);
          }
          ?>
        </td>
        <td style="border-left:0px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
          <span class="fill">
            <?php 
            if (!empty($directorGeneralApproval)) {
                // Check if it's a structured array (from PrintHelper) or direct approval object
                if (isset($directorGeneralApproval['staff']) || isset($directorGeneralApproval['oic_staff'])) {
                    // Structured data from PrintHelper
                    $isOic = isset($directorGeneralApproval['oic_staff']);
                    $staff = $isOic ? $directorGeneralApproval['oic_staff'] : $directorGeneralApproval['staff'];
                    
                    if ($staff) {
                        $name = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '') . ' ' . ($staff['oname'] ?? ''));
                        if ($isOic) {
                            $name .= ' (OIC)';
                        }
                        
                        echo '<div style="line-height: 1.2;">';
                        echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small><br>';
                        if (!empty($staff['signature'])) {
                            echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $staff['signature']) . '" alt="Signature">';
            } else {
                            echo '<small style="color: #666; font-style: normal;">' . htmlspecialchars($staff['work_email'] ?? 'Email not available') . '</small>';
                        }
                        echo '<div class="signature-date">' . htmlspecialchars(date('j F Y H:i')) . '</div>';
                        echo '<div class="signature-hash">Hash: ' . htmlspecialchars(generateVerificationHash($sourceModel->id, $staff['id'] ?? '', date('Y-m-d H:i:s'))) . '</div>';
                        echo '</div>';
                    } else {
                        renderBudgetSignature(null, $sourceModel);
                    }
                } else {
                    // Direct approval object from getARFApprovers
                    $isOic = !empty($directorGeneralApproval->oic_staff_id);
                    $staff = $isOic ? $directorGeneralApproval->oicStaff : $directorGeneralApproval->staff;
                    
                    if ($staff) {
                        $name = trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? ''));
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
                        $approvalDate = is_object($directorGeneralApproval->created_at) ? $directorGeneralApproval->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($directorGeneralApproval->created_at));
                        echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
                        $hash = generateVerificationHash($sourceModel->id, $isOic ? $directorGeneralApproval->oic_staff_id : $directorGeneralApproval->staff_id, $directorGeneralApproval->created_at);
                        echo '<div class="signature-hash">Hash: ' . htmlspecialchars($hash) . '</div>';
                        echo '</div>';
                    } else {
                        renderBudgetSignature(null, $sourceModel);
                    }
                }
            } else {
                renderBudgetSignature(null, $sourceModel);
            }
            ?>
          </span>
        </td>
      </tr>
    </table>

    <?php 
    
    ?>
</div>
</body>
</html>