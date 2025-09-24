<html>
<head>
<style>
    /* Color variables (mPDF doesn't support CSS variables, so we'll use direct values) */
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

    /* Document structure */
    .document-title {
        font-size: 18px; 
        font-weight: bold; 
        text-align: center; 
        text-transform: uppercase;
        margin-top: -20px; 
        margin-bottom: 15px; 
        color:#100f0f; 
        letter-spacing: 0.5px;
    }
    
    /* Right-side memo meta (stacked, no borders) */
    .topbar { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 10px; }
    .meta { margin-left: auto; text-align: right; line-height: 1.3; }
    .meta .memo-id { font-weight: 700; letter-spacing: .2px; }
    .meta .date { color: #64748b; }

    /* Tables (NO BORDERS BY DEFAULT) */
    table { border-collapse: collapse; width: 100%; }
    table, th, td { border: none; }  /* default: no borders */
    
    .form-table { 
        margin: 10px 0 16px; 
        background: #fff; 
        overflow: clip; 
        border-radius: 10px; 
    }
    .form-table th, .form-table td { padding: 10px; vertical-align: top; }
    .form-table th { width: 30%; font-weight: bold; text-align: left; background: #f9fafb; }
    
    .muted { color: #64748b; font-size: 12px; }
    .fill { min-height: 28px; display: block; }

    /* Budget/Certification table wrapper */
    .budget-table { 
        margin: 12px 0 18px; 
        background: #fff; 
        overflow: clip; 
        border-radius: 10px; 
    }
    .budget-table td { padding: 8px; vertical-align: top; min-height: 30px; }
    .budget-table .head { background: #f9fafb; font-weight: bold; }

    /* Signature table wrapper */
    .sig-table { 
        margin-top: 18px; 
        border-radius: 10px; 
        overflow: clip; 
    }
    .sig-table td { 
        padding: 14px 10px; 
        vertical-align: bottom; 
        height: 90px; 
    }
    .sig-table td:first-child { width: 12%; font-weight: bold; background: #f9fafb; }
    .sig-table td:nth-child(2) { width: 25%; }

    /* Helper underline */
    .line { display: block; border-bottom: 1px solid #4f545a; height: 22px; }
    
    /* Section labels */
    .section-label {
        color: #006633; 
        font-weight: bold; 
        font-size: 14px; 
        margin-top: 10px;
        margin-bottom: 10px;
        font-style: regular;
    }
    p {
      font-size: 14px;
      color: #222;
      text-align: justify !important;
    }
    
    /* Approver information */
    .approver-name {
        font-size: 14px; 
        font-weight: bold; 
        line-height: 1.2; 
        margin-bottom: 2px;
    }
    
    .approver-title {
        color: #666; 
        font-size: 12px; 
        line-height: 1.1; 
        margin-top: 1px;
    }
    
    /* Signature styling */
    .signature-image {
        height: 30px; 
        max-width: 80px; 
        object-fit: contain; 
        filter: contrast(1.2);
        display: block;
        margin: 0;
        padding: 0;
    }
    
    .signature-date {
        color: #666; 
        font-size: 8px; 
        margin: 0;
        padding: 0;
        line-height: 1.1;
    }
    
    .signature-hash {
        color: #999; 
        font-size: 8px;
        margin: 0;
        padding: 0;
        line-height: 1.1;
    }
    
    /* Table borders for specific sections */
    .bordered-table {
        border: 1px solid #ccc;
        border-collapse: collapse;
    }
    
    .bordered-table th, 
    .bordered-table td {
        border: 1px solid #ccc; 
        padding: 6px; 
        text-align: left; 
        vertical-align: top;
    }
    
    .bordered-table th {
        background-color: #f9f9f9; 
        font-weight: bold; 
        font-size: 12px;
    }
    
    /* Page break */
    .page-break {
        page-break-before: always;
    }
    
    /* Text alignment */
    .text-right {
        text-align: right;
    }
    
    .text-left {
        text-align: left;
    }
    
    .text-center {
        text-align: center;
    }
    
    /* Spacing */
    .mb-15 {
        margin-bottom: 15px;
    }
    
    .mt-neg20 {
        margin-top: -20px;
    }
    
    /* Special styles */
    .subject-text {
        text-decoration: underline; 
        font-weight: bold;
    }
    
    .underline {
        text-decoration: underline;
    }
    
    .bg-highlight {
        background-color: #f9f9f9;
    }
    
    .justify-text {
        text-align: justify;
        text-justify: inter-word;
        word-spacing: 0.1em;
        line-height: 1.6;
    }
</style>
</head>
<body>
 
  <!-- Service Request Document -->
  <!-- Document Title -->
  <h1 class="document-title">Interoffice Memorandum</h1>
  
  <?php
    // Helper functions to safely access staff data
    if (!function_exists('getStaffEmail')) {
    function getStaffEmail($approver) {
      if (isset($approver['staff']) && isset($approver['staff']['work_email'])) {
        return $approver['staff']['work_email'];
      } elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['work_email'])) {
        return $approver['oic_staff']['work_email'];
      }
      return null;
      }
    }
    
    if (!function_exists('getStaffId')) {
    function getStaffId($approver) {
      if (isset($approver['staff']) && isset($approver['staff']['id'])) {
        return $approver['staff']['id'];
      } elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['id'])) {
        return $approver['oic_staff']['id'];
      }
      return null;
    }
    }
    
    if (!function_exists('generateVerificationHash')) {
      function generateVerificationHash($serviceRequestId, $staffId, $approvalDateTime = null) {
        if (!$serviceRequestId || !$staffId) return 'N/A';
        $dateTimeToUse = $approvalDateTime ? $approvalDateTime : date('Y-m-d H:i:s');
        return strtoupper(substr(md5(sha1($serviceRequestId . $staffId . $dateTimeToUse)), 0, 16));
      }
    }

    // Wrapper function to adapt getApprovalDate call for embedded source memos
    if (!function_exists('getApprovalDateForServiceRequest')) {
      function getApprovalDateForServiceRequest($staffId, $serviceRequestApprovalTrails, $order) {
        // The embedded source memo's getApprovalDate expects different parameter names
        // We'll call it with the service request approval trails as the second parameter
        return getApprovalDate($staffId, $serviceRequestApprovalTrails, $order);
      }
    }

    // Helper function to render approver info
    if (!function_exists('renderApproverInfo')) {
      function renderApproverInfo($approver, $role, $section, $serviceRequest) {
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
            $divisionName = $serviceRequest->division->division_name ?? '';
            if (!empty($divisionName)) {
                echo '<div class="approver-title">' . htmlspecialchars($divisionName) . '</div>';
            }
            }
        }
    }

    // Helper function to render signature
    if (!function_exists('renderSignature')) {
      function renderSignature($approver, $order, $serviceRequestApprovalTrails, $serviceRequest) {
        $isOic = isset($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        $staffId = $staff['id'] ?? null;

        $approvalDate = getApprovalDateForServiceRequest($staffId, $serviceRequestApprovalTrails, $order);

        echo '<div style="line-height: 1.2;">';
        
        if (isset($staff['signature']) && !empty($staff['signature'])) {
            echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small> ';
            echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $staff['signature']) . '" alt="Signature">';
        } else {
            echo '<small style="color: #666; font-style:normal;">Signed By: ' . htmlspecialchars($staff['work_email'] ?? 'Email not available') . '</small>';
        }
        
        echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
        echo '<div class="signature-hash">Hash: ' . htmlspecialchars(generateVerificationHash($serviceRequest->id, $staffId, $approvalDate)) . '</div>';
        echo '</div>';
    }
    }

    // Generate file reference once
    $serviceRequest_reference = 'N/A';
    if (isset($serviceRequest)) {
        $divisionName = $serviceRequest->division->division_name ?? '';
        $divisionShortName = $serviceRequest->division->division_short_name ?? '';
        
        if (!function_exists('generateShortCodeFromDivision')) {
            function generateShortCodeFromDivision(string $name): string {
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
        }
        
        // Use division_short_name if available, otherwise generate from division_name
        if (!empty($divisionShortName)) {
            $shortCode = strtoupper($divisionShortName);
        } else {
            $shortCode = $divisionName ? generateShortCodeFromDivision($divisionName) : 'DIV';
        }
        
        $year = date('Y', strtotime($serviceRequest->created_at ?? 'now'));
        $serviceRequestId = $serviceRequest->id ?? 'N/A';
        $serviceRequest_reference = "AU/CDC/{$shortCode}/SR/{$year}/{$serviceRequestId}";
    }

      // Define the order of sections: TO, THROUGH, FROM (excluding 'others')
      $sectionOrder = ['to', 'through', 'from'];
      
      // Filter out 'others' section if it exists
      if (isset($organized_workflow_steps['others'])) {
        unset($organized_workflow_steps['others']);
      }

      // Section labels in sentence case
      $sectionLabels = [
        'to' => 'To:',
        'through' => 'Through:',
        'from' => 'From:'
      ];

      // Calculate total rows needed for rowspan
      $totalRows = 0;
      foreach ($sectionOrder as $section) {
        if (isset($organized_workflow_steps[$section]) && $organized_workflow_steps[$section]->count() > 0) {
          $totalRows += $organized_workflow_steps[$section]->count();
        } else {
          $totalRows += 1; // At least one row per section
        }
      }
      $dateFileRowspan = $totalRows;
    ?>
  <table class="mb-15">
    <?php foreach ($sectionOrder as $section): ?>
      <?php if (isset($organized_workflow_steps[$section]) && $organized_workflow_steps[$section]->count() > 0): ?>
        <?php foreach ($organized_workflow_steps[$section] as $index => $step): 
                $order = $step['order'];
                $role = $step['role'];
          ?>
          <tr>
                <td style="width: 12%; vertical-align: top;">
                    <strong class="section-label"><?php echo $sectionLabels[$section] ?? (strtoupper($section) . ':'); ?></strong>
            </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                            <?php renderApproverInfo($approver, $role, $section, $serviceRequest); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="approver-name"><?php echo htmlspecialchars($role); ?></div>
                        <?php if ($section === 'from'): ?>
                            <div class="approver-title"><?php echo htmlspecialchars($serviceRequest->division->division_name ?? ''); ?></div>
                    <?php endif; ?>
        <?php endif; ?>
      </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                            <?php renderSignature($approver, $order, $serviceRequest->serviceRequestApprovalTrails, $serviceRequest); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </td>
                <?php if ($section === $sectionOrder[0] && $index === 0): // Only output the Date/FileNo cell once ?>
                    <td style="width: 28%; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
                        <div class="text-right">
                  <div style="margin-bottom: 20px;">
                                <strong class="section-label">Date:</strong>
                                <span style="font-weight: bold;"><?php echo isset($serviceRequest->created_at) ? (is_object($serviceRequest->created_at) ? $serviceRequest->created_at->format('j F Y') : date('j F Y', strtotime($serviceRequest->created_at))) : date('j F Y'); ?></span>
          </div>
                  <div>
                    <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($serviceRequest->document_number ?? 'N/A'); ?></span>
          </div>
        </div>
      </td>
            <?php endif; ?>
    </tr>
          <?php endforeach; ?>
      <?php else: ?>
        <tr>
                <td style="width: 12%; vertical-align: top;">
                    <strong class="section-label"><?php echo $sectionLabels[$section] ?? (strtoupper($section) . ':'); ?></strong>
          </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    <div class="approver-name"><?php echo htmlspecialchars($section); ?></div>
                    <?php if ($section === 'from'): ?>
                        <div class="approver-title"><?php echo htmlspecialchars($serviceRequest->division->division_name ?? ''); ?></div>
                    <?php endif; ?>
          </td>
                <td style="width: 30%; vertical-align: top; text-align: left;"></td>
                <?php if ($section === $sectionOrder[0]): // Only output the Date/FileNo cell once ?>
                    <td style="width: 28%; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
                        <div class="text-right">
                <div style="margin-bottom: 20px;">
                                <strong class="section-label">Date:</strong>
                                <span style="font-weight: bold;"><?php echo isset($serviceRequest->created_at) ? (is_object($serviceRequest->created_at) ? $serviceRequest->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($serviceRequest->created_at))) : date('j F Y H:i'); ?></span>
                </div>
                <div>
                                <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($serviceRequest->document_number ?? 'N/A'); ?></span>
                </div>
              </div>
            </td>
          <?php endif; ?>
    </tr>
      <?php endif; ?>
    <?php endforeach; ?>
  </table>

  <!-- Subject -->
 <table class="mb-15">
  <tr>
    <td style="width: 12%; text-align: left; vertical-align: top;"><strong class="section-label">Subject:</strong></td>
    <td style="width: 88%; text-align: left; vertical-align: top;" class="subject-text">Service Request  for <?php echo htmlspecialchars($serviceRequest->service_title ?? 'N/A'); ?></td>
  </tr>
 </table>


<p>Reference is made to the attached approval memo <b><?=$sourceData->document_number?></b> regarding <?=$sourceData->title?> 
<?php if (isset($sourceData->date_from) && isset($sourceData->date_to)): ?>
    starting on <?=date('j F Y', strtotime($sourceData->date_from))?> and ending on <?=date('j F Y', strtotime($sourceData->date_to))?>.
<?php elseif (isset($sourceData->memo_date)): ?>
    dated <?=date('j F Y', strtotime($sourceData->memo_date))?>.
<?php else: ?>
    for the specified period.
<?php endif; ?>

  <?php
    // Parse the budget breakdown JSON from the service request
    $budgetData = null;
    if ($serviceRequest->budget_breakdown) {
        $budgetData = is_string($serviceRequest->budget_breakdown) 
            ? json_decode($serviceRequest->budget_breakdown, true) 
            : $serviceRequest->budget_breakdown;
    }
  ?>

  <!-- Service Request Budget Breakdown -->
  <?php if ($budgetData && (isset($budgetData['internal_participants']) || isset($budgetData['external_participants']) || isset($budgetData['other_costs']))): ?>
                    <div class="mb-4">
                      <!-- Service Request Details -->
<div class="mb-0" style="color:#006633; font-size: 15px;"><strong>Budget Breakdown</strong></div>
                        
                        <!-- Internal Participants -->
                        <?php if (isset($budgetData['internal_participants']) && is_array($budgetData['internal_participants']) && count($budgetData['internal_participants']) > 0): ?>
                        <div>
                            <h4 class="fw-bold text-success mb-3">
                                <i class="fas fa-users me-2"></i>Internal Participants
                            </h4>
                            
                            <?php
                            // Group participants by cost type
                            $costGroups = [];
                            foreach ($budgetData['internal_participants'] as $participant) {
                                if (isset($participant['costs']) && is_array($participant['costs'])) {
                                    foreach ($participant['costs'] as $costName => $costValue) {
                                        if (!isset($costGroups[$costName])) {
                                            $costGroups[$costName] = [];
                                        }
                                        $costGroups[$costName][] = [
                                            'participant' => $participant,
                                            'unit_cost' => $costValue
                                        ];
                                    }
                                }
                            }
                            ?>
                            
                            <?php foreach ($costGroups as $costName => $participants): ?>
                            <table class="bordered-table mb-15">
                                <thead>
                                    <tr>
                                        <th class="bg-highlight" colspan="7"><?php echo htmlspecialchars($costName); ?></th>
                                    </tr>
                                    <tr>
                                        <th class="bg-highlight">#</th>
                                        <th class="bg-highlight">Name</th>
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
                                    $groupTotal = 0;
                                    ?>
                                    <?php foreach ($participants as $item): ?>
                                        <?php
                                        $participant = $item['participant'];
                                        $unitCost = $item['unit_cost'];
                                        $staff = null;
                                        if (isset($participant['staff_id'])) {
                                            $staff = \App\Models\Staff::find($participant['staff_id']);
                                        }
                                        $units = 1; // Default units for internal participants
                                        $days = 1;  // Default days for internal participants
                                        $total = $unitCost * $units * $days;
                                        $groupTotal += $total;
                                        ?>
                                        <tr>
                                            <td><?php echo $count; ?></td>
                                            <td>
                                                <?php if ($staff): ?>
                                                    <?php echo htmlspecialchars($staff->fname . ' ' . $staff->lname); ?>
                                                <?php else: ?>
                                                    Staff ID: <?php echo htmlspecialchars($participant['staff_id'] ?? 'Unknown'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-right">$<?php echo number_format($unitCost, 2); ?></td>
                                            <td class="text-right"><?php echo $units; ?></td>
                                            <td class="text-right"><?php echo $days; ?></td>
                                            <td class="text-right">$<?php echo number_format($total, 2); ?></td>
                                            <td>Internal participant - <?php echo htmlspecialchars($staff->position ?? 'Staff'); ?></td>
                                        </tr>
                                        <?php $count++; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th class="bg-highlight text-right" colspan="5"><?php echo htmlspecialchars($costName); ?> Total</th>
                                        <th class="bg-highlight text-right">$<?php echo number_format($groupTotal, 2); ?></th>
                                        <th class="bg-highlight"></th>
                                    </tr>
                                </tfoot>
                            </table>
                            <?php endforeach; ?>
                            
                            <!-- Internal Participants Comments -->
                            <?php if ($serviceRequest->internal_participants_comment): ?>
                            <div class="mt-3 p-3 bg-light rounded border-start border-success border-4">
                                <h6 class="fw-bold text-success mb-2">
                                    <i class="fas fa-comment me-2"></i>Internal Participants Comments
                                </h6>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($serviceRequest->internal_participants_comment); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- External Participants -->
                        <?php if (isset($budgetData['external_participants']) && is_array($budgetData['external_participants']) && count($budgetData['external_participants']) > 0): ?>
                        <div class="mb-4">
                          <h4 class="fw-bold text-success mb-3">
                                <i class="fas fa-users me-2"></i>External Participants
                            </h4>
                            
                            <?php
                            // Group participants by cost type
                            $costGroups = [];
                            foreach ($budgetData['external_participants'] as $participant) {
                                if (isset($participant['costs']) && is_array($participant['costs'])) {
                                    foreach ($participant['costs'] as $costName => $costValue) {
                                        if (!isset($costGroups[$costName])) {
                                            $costGroups[$costName] = [];
                                        }
                                        $costGroups[$costName][] = [
                                            'participant' => $participant,
                                            'unit_cost' => $costValue
                                        ];
                                    }
                                }
                            }
                            ?>
                            
                            <?php foreach ($costGroups as $costName => $participants): ?>
                            <table class="bordered-table mb-15">
                                <thead>
                                    <tr>
                                        <th class="bg-highlight" colspan="7"><?php echo htmlspecialchars($costName); ?></th>
                                    </tr>
                                    <tr>
                                        <th class="bg-highlight">#</th>
                                        <th class="bg-highlight">Name</th>
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
                                    $groupTotal = 0;
                                    ?>
                                    <?php foreach ($participants as $item): ?>
                                        <?php
                                        $participant = $item['participant'];
                                        $unitCost = $item['unit_cost'];
                                        $units = 1; // Default units for external participants
                                        $days = 1;  // Default days for external participants
                                        $total = $unitCost * $units * $days;
                                        $groupTotal += $total;
                                        ?>
                                        <tr>
                                            <td><?php echo $count; ?></td>
                                            <td><?php echo htmlspecialchars($participant['name'] ?? 'N/A'); ?></td>
                                            <td class="text-right">$<?php echo number_format($unitCost, 2); ?></td>
                                            <td class="text-right"><?php echo $units; ?></td>
                                            <td class="text-right"><?php echo $days; ?></td>
                                            <td class="text-right">$<?php echo number_format($total, 2); ?></td>
                                            <td>External participant - <?php echo htmlspecialchars($participant['organization'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php $count++; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th class="bg-highlight text-right" colspan="5"><?php echo htmlspecialchars($costName); ?> Total</th>
                                        <th class="bg-highlight text-right">$<?php echo number_format($groupTotal, 2); ?></th>
                                        <th class="bg-highlight"></th>
                                    </tr>
                                </tfoot>
                            </table>
                            <?php endforeach; ?>
                            
                            <!-- External Participants Comments -->
                            <?php if ($serviceRequest->external_participants_comment): ?>
                            <div class="mt-3 p-3 bg-light rounded border-start border-warning border-4">
                                <h6 class="fw-bold text-warning mb-2">
                                    <i class="fas fa-comment me-2"></i>External Participants Comments
                                </h6>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($serviceRequest->external_participants_comment); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Other Costs -->
                        <?php if (isset($budgetData['other_costs']) && is_array($budgetData['other_costs']) && count($budgetData['other_costs']) > 0): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold text-info mb-3">
                                <i class="fas fa-list me-2"></i>Other Costs
                            </h6>
                            
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
                                    $otherGrandTotal = 0;
                                    ?>
                                    <?php foreach ($budgetData['other_costs'] as $index => $cost): ?>
                                        <?php
                                        $unitCost = $cost['unit_cost'] ?? 0;
                                        $units = $cost['units'] ?? 1;
                                        $days = $cost['days'] ?? 1;
                                        $total = $unitCost * $units * $days;
                                        $otherGrandTotal += $total;
                                        ?>
                                        <tr>
                                            <td><?php echo $count; ?></td>
                                            <td><?php echo htmlspecialchars($cost['cost_type'] ?? 'N/A'); ?></td>
                                            <td class="text-right">$<?php echo number_format($unitCost, 2); ?></td>
                                            <td class="text-right"><?php echo $units; ?></td>
                                            <td class="text-right"><?php echo $days; ?></td>
                                            <td class="text-right">$<?php echo number_format($total, 2); ?></td>
                                            <td><?php echo htmlspecialchars($cost['description'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php $count++; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th class="bg-highlight text-right" colspan="5">Other Costs Total</th>
                                        <th class="bg-highlight text-right">$<?php echo number_format($otherGrandTotal, 2); ?></th>
                                        <th class="bg-highlight"></th>
                                    </tr>
                                </tfoot>
                            </table>
                            
                            <!-- Other Costs Comments -->
                            <?php if ($serviceRequest->other_costs_comment): ?>
                            <div class="mt-3 p-3 bg-light rounded border-start border-info border-4">
                                <h6 class="fw-bold text-info mb-2">
                                    <i class="fas fa-comment me-2"></i>Other Costs Comments
                                </h6>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($serviceRequest->other_costs_comment); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
    


<div class="page-break"></div>

<?php if ($sourcePdfHtml): ?>
  <!-- Include the source memo HTML here -->
  <div class="section-label mb-15"><strong>Approval Memo</strong></div>
  
  <!-- The source memo HTML will be embedded here -->
  <?php echo $sourcePdfHtml; ?>
<?php endif; ?>

</body>
</html>