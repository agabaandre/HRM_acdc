<html>
<head>
<style>
        /* Color variables */
        * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; color: #0f172a; }
         body { 
         font-size: 14px; 
         font-family: "freesans",arial, sans-serif; 
         background: #f6f8fb; 
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
            width: 25%;
            background-color: #f8f9fa !important;
            color: #007e33;
        }
        
        .content {
            width: 75%;
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
  <h1 class="document-title">ARF Request Form</h1>
  
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
                <td class="content"><?php echo htmlspecialchars($requestARF->arf_number); ?></td>
            </tr>
            <tr>
                <td class="label">Payee:</td>
                <td class="content">Africa CDC</td>
                <td class="label">Funding Source:</td>
                <td class="content"><?php echo htmlspecialchars($requestARF->funder->name ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <?php //dd($sourceModel);?>
                <td class="label">Partner:</td>
                <td class="content">Clarify</td>
                <td class="label">Code:</td>
                <td class="content"><?php echo htmlspecialchars($requestARF->extramural_code ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td class="label">Project Title:</td>
                <td class="content" colspan="3"><?php echo htmlspecialchars($requestARF->activity_title); ?></td>
            </tr>
            <tr>
                <td class="label">Currency:</td>
                <td class="content">USD</td>
                <td class="label">Amount:</td>
                <td class="content">$<?php echo number_format($requestARF->total_amount ?? 0, 2); ?></td>
            </tr>
        </table>

        <div class="section-label">Activity Brief</div>
    
        <div><?php echo $sourceModel['background']; ?></div>

 
    
    <?php if (!empty($internalParticipants) && is_array($internalParticipants)): ?>
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
                                    // Handle different data structures
                                    $staff = null;
                                    $days = 0;
                                    
                                    if (is_array($participantData) && isset($participantData['staff'])) {
                                        $staff = $participantData['staff'];
                                        $days = $participantData['participant_days'] ?? 0;
                                    } elseif (is_object($participantData)) {
                                        $staff = $participantData;
                                        $days = $participantData->participant_days ?? 0;
                                    }
                                    
                                    if ($staff):
                                ?>
                                    <tr>
                                        <td><?php echo $count; ?></td>
                                        <td><?php echo htmlspecialchars($staff->fname . ' ' . $staff->lname ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($staff->division_name ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($staff->job_name ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($staff->duty_station_name ?? 'N/A'); ?></td>
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

<?php
    // Get latest approvals for each order from source model approval trails
    $sourceApprovalTrails = $sourceData['approval_trails'] ?? collect();
   
   
    $approvalOrder1 = getLatestApprovalForOrder($sourceApprovalTrails, 1);
    $approvalOrder9 = getLatestApprovalForOrder($sourceApprovalTrails, 9);
    $approvalOrder10 = getLatestApprovalForOrder($sourceApprovalTrails, 10);
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
          <?php renderBudgetApproverInfo(null, $sourceData['responsible_person']); ?>
        </td>
        <td style="border-left:0px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
          <span class="fill">
            <?php renderBudgetSignature($approvalOrder1, $sourceModel, true, $sourceData); ?>
          </span>
        </td>
      </tr>
      <tr>
        <td>Signed (Endorsed by):</td>
        <td>
          <?php renderBudgetApproverInfo($approvalOrder1); ?>
        </td>
        <td style="border-left:0px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
          <span class="fill">
            <?php renderBudgetSignature($approvalOrder1, $sourceModel); ?>
          </span>
        </td>
      </tr>
      <tr>
        
          <td>Approved  By:</td>
          <td>
           <?php renderBudgetApproverInfo($approvalOrder9); ?>
          </td>
          <td style="border-left:0px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
            <span class="fill">
             <?php renderBudgetSignature($approvalOrder9, $sourceModel); ?>
            </span>
          </td>
      </tr>
      <tr>
        <td>Approved by:</td>
        <td>
          <?php renderBudgetApproverInfo($approvalOrder10); ?>
        </td>
        <td style="border-left:0px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
          <span class="fill">
            <?php renderBudgetSignature($approvalOrder10, $sourceModel); ?>
          </span>
        </td>
      </tr>
    </table>

    <?php 
    
    ?>
</div>
</body>
</html>