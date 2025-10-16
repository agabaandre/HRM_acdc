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
    // Use centralized PrintHelper
    use App\Helpers\PrintHelper;

    // Fetch approvers from source data approval trails
    $sourceApprovers = [];
    if (isset($sourceData)) {
        // Determine the source ID and model type
        $sourceId = null;
        $modelType = null;
        $divisionId = null;
        
        if (isset($sourceData->matrix_id)) {
            // It's a matrix activity, use matrix ID
            $sourceId = $sourceData->matrix_id;
            $modelType = 'App\Models\Matrix';
            $divisionId = $sourceData->division_id ?? null;
        } else {
            // It's a direct memo, use the source data ID
            $sourceId = $sourceData->id;
            $divisionId = $sourceData->division_id ?? null;
            // Determine model type based on source data type
            if (isset($sourceData->matrix_id)) {
                $modelType = 'App\Models\Matrix';
            } else {
                // Check if it's a non-travel memo or special memo
                $modelType = 'App\Models\NonTravelMemo'; // Default to non-travel memo
            }
        }
        
        if ($sourceId && $modelType) {
            // Get the workflow_id from the source data
            $workflowId = null;
            if ($sourceData && isset($sourceData->forward_workflow_id)) {
                $workflowId = $sourceData->forward_workflow_id;
            }
            
            $sourceApprovers = PrintHelper::fetchApproversFromTrails($sourceId, $modelType, $divisionId, $workflowId);
      }
    }

    // Helper function to render approver info
    if (!function_exists('renderApproverInfo')) {
      function renderApproverInfo($approver, $role, $section, $serviceRequest) {
        PrintHelper::renderApproverInfo($approver, $role, $section, $serviceRequest);
        }
    }

    // Helper function to render signature
    if (!function_exists('renderSignature')) {
      function renderSignature($approver, $order, $serviceRequestApprovalTrails, $serviceRequest) {
        PrintHelper::renderSignature($approver, $order, $serviceRequestApprovalTrails, $serviceRequest);
    }
    }

    // Generate file reference once
    $serviceRequest_reference = 'N/A';
    if (isset($serviceRequest)) {
        $divisionName = $serviceRequest->division->division_name ?? '';
        $divisionShortName = $serviceRequest->division->division_short_name ?? '';
        
        // Use PrintHelper for generating short code
        
        // Use division_short_name if available, otherwise generate from division_name
        if (!empty($divisionShortName)) {
            $shortCode = strtoupper($divisionShortName);
        } else {
            $shortCode = $divisionName ? PrintHelper::generateShortCodeFromDivision($divisionName) : 'DIV';
        }
        
        $year = date('Y', strtotime($serviceRequest->created_at ?? 'now'));
        $serviceRequestId = $serviceRequest->id ?? 'N/A';
        $serviceRequest_reference = "AU/CDC/{$shortCode}/SR/{$year}/{$serviceRequestId}";
    }

      // Get division category safely
      $divisionCategory = null;
      if (isset($serviceRequest->division) && isset($serviceRequest->division->category)) {
          $divisionCategory = $serviceRequest->division->category;
      }

      // Organize approvers by section using helper
      $organizedApprovers = PrintHelper::organizeApproversBySection(
          $serviceRequest->id ?? null,
          'App\Models\ServiceRequest',
          $serviceRequest->division_id ?? null,
          $serviceRequest->forward_workflow_id ?? null,
          $divisionCategory
      );

      // Define the order of sections: TO, THROUGH, FROM
      $sectionOrder = ['to', 'from'];

      // Section labels in sentence case
      $sectionLabels = [
        'to' => 'To:',
        'from' => 'From:'
      ];

      // Calculate total rows needed for rowspan based on organized approvers
      $totalRows = 0;
      foreach ($sectionOrder as $section) {
        if (isset($organizedApprovers[$section]) && count($organizedApprovers[$section]) > 0) {
          $totalRows += count($organizedApprovers[$section]);
        } else {
          $totalRows += 1; // At least one row per section
        }
      }
      $dateFileRowspan = $totalRows;
    ?>
  <table class="mb-15">
    <?php foreach ($sectionOrder as $section): ?>
      <?php if (isset($organizedApprovers[$section]) && count($organizedApprovers[$section]) > 0): ?>
        <?php foreach ($organizedApprovers[$section] as $index => $approver): ?>
          <tr>
                <td style="width: 12%; vertical-align: top;">
                    <strong class="section-label"><?php echo $sectionLabels[$section] ?? (strtoupper($section) . ':'); ?></strong>
            </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    <?php renderApproverInfo($approver, $approver['role'], $section, $serviceRequest); ?>
      </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    <?php 
                    $order = $approver['order'];
                    if ($order === 'division_head') {
                        $order = 1; // Use level 1 for division head
                    }
                    renderSignature($approver, $order, $serviceRequest->serviceRequestApprovalTrails, $serviceRequest); 
                    ?>
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
                    <div class="approver-name"><?php echo htmlspecialchars(ucfirst($section)); ?></div>
                    <?php if ($section === 'from'): ?>
                        <div class="approver-title"><?php echo htmlspecialchars($serviceRequest->division->division_name ?? ''); ?></div>
                    <?php endif; ?>
          </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    <!-- Empty signature space -->
          </td>
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


 <p>
    Reference is made to the attached approval memo, <a href="<?=$sourceData->memo_link?>" class="text-success text-decoration-underline" style="color:#006633 !important;"><b><?=$sourceData->document_number?></b></a>, concerning <?=$sourceData->activity_title?>, 
    <?php if (isset($sourceData->date_from) && isset($sourceData->date_to)): ?>
        which is scheduled to commence on <?=date('j F Y', strtotime($sourceData->date_from))?>, and conclude on <?=date('j F Y', strtotime($sourceData->date_to))?>.
    <?php elseif (isset($sourceData->memo_date)): ?>
        dated <?=date('j F Y', strtotime($sourceData->memo_date))?>.
    <?php else: ?>
        for the specified period.
    <?php endif; ?>
</p>



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
  <?php if ($serviceRequest->source_type === 'non_travel_memo' && $sourceData && $sourceData->budget_breakdown): ?>
    <!-- Non-Travel Memo Source Budget Breakdown -->
    <?php
    $sourceBudgetBreakdown = is_string($sourceData->budget_breakdown) 
        ? json_decode($sourceData->budget_breakdown, true) 
        : $sourceData->budget_breakdown;
    ?>
    <?php if ($sourceBudgetBreakdown && is_array($sourceBudgetBreakdown)): ?>
    <div class="mb-4">
        <div class="mb-0" style="color:#006633; font-size: 15px;"><strong>Budget Breakdown (Non-Travel Memo)</strong></div>
        
        <table class="bordered-table mb-15">
            <thead>
                <tr>
                    <th class="bg-highlight">#</th>
                    <th class="bg-highlight">Description</th>
                    <th class="bg-highlight">Quantity</th>
                    <th class="bg-highlight">Unit Price</th>
                    <th class="bg-highlight">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandTotal = 0;
                $rowIndex = 1;
                unset($sourceBudgetBreakdown['grand_total']);
                ?>
                <?php foreach ($sourceBudgetBreakdown as $codeId => $items): ?>
                    <?php if (is_array($items)): ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $itemTotal = ($item['quantity'] ?? 1) * ($item['unit_cost'] ?? 0);
                            $grandTotal += $itemTotal;
                            ?>
                            <tr>
                                <td><?php echo $rowIndex; ?></td>
                                <td><?php echo $item['description'] ?? 'N/A'; ?></td>
                                <td class="text-center"><?php echo $item['quantity'] ?? 1; ?></td>
                                <td class="text-right">$<?php echo number_format($item['unit_cost'] ?? 0, 2); ?></td>
                                <td class="text-right">$<?php echo number_format($itemTotal, 2); ?></td>
                            </tr>
                            <?php $rowIndex++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="bg-highlight text-right" colspan="4">Grand Total</th>
                    <th class="bg-highlight text-right">$<?php echo number_format($grandTotal, 2); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
  <?php elseif ($budgetData && (isset($budgetData['internal_participants']) || isset($budgetData['external_participants']) || isset($budgetData['other_costs']))): ?>
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
                            // Prepare data for crosstab structure
                            $participantData = [];
                            $costTypes = [];
                            $ticketsData = []; // Special handling for Tickets (id 1)
                            
                            foreach ($budgetData['internal_participants'] as $participant) {
                                if (isset($participant['costs']) && is_array($participant['costs'])) {
                                    $staff = null;
                                    if (isset($participant['staff_id'])) {
                                        $staff = \App\Models\Staff::find($participant['staff_id']);
                                    }
                                    $participantName = $staff ? ($staff->fname . ' ' . $staff->lname) : ('Staff ID: ' . ($participant['staff_id'] ?? 'Unknown'));
                                    
                                    foreach ($participant['costs'] as $costName => $costValue) {
                                        // Check if this is Tickets (id 1) - keep current structure
                                        if (strtolower($costName) === 'tickets' || (isset($participant['cost_type_id']) && $participant['cost_type_id'] == 1)) {
                                            if (!isset($ticketsData[$costName])) {
                                                $ticketsData[$costName] = [];
                                            }
                                            $ticketsData[$costName][] = [
                                                'participant' => $participant,
                                                'participant_name' => $participantName,
                                                'unit_cost' => $costValue,
                                                'staff' => $staff
                                            ];
                                        } else {
                                            // Regular crosstab structure
                                            if (!isset($participantData[$participantName])) {
                                                $participantData[$participantName] = [];
                                            }
                                            $participantData[$participantName][$costName] = $costValue;
                                            if (!in_array($costName, $costTypes)) {
                                                $costTypes[] = $costName;
                                            }
                                        }
                                    }
                                }
                            }
                            ?>
                            
                            <!-- Individual Participant Cost Breakdown (non-Tickets) - 3 per row -->
                            <?php if (!empty($participantData)): ?>
                                <?php
                                $grandTotal = 0;
                                $participantTotals = []; // Track total per participant
                                $participantsWithCosts = []; // Array to store participants with costs
                                
                                // Initialize participant totals and collect participants with costs
                                foreach (array_keys($participantData) as $participantName) {
                                    $participantTotals[$participantName] = 0;
                                    
                                    // Check if participant has any costs
                                    $hasCosts = false;
                                    foreach ($costTypes as $costName) {
                                        $costValue = $participantData[$participantName][$costName] ?? 0;
                                        if ($costValue > 0) {
                                            $hasCosts = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($hasCosts) {
                                        $participantsWithCosts[] = $participantName;
                                    }
                                }
                                ?>
                                
                                <!-- Mega Table for 2 tables per row -->
                                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                                    <?php
                                    $participantsCount = count($participantsWithCosts);
                                    $rows = ceil($participantsCount / 2);
                                    
                                    for ($row = 0; $row < $rows; $row++):
                                        $startIndex = $row * 2;
                                        $endIndex = min($startIndex + 2, $participantsCount);
                                    ?>
                                    <tr>
                                        <?php for ($col = $startIndex; $col < $endIndex; $col++): ?>
                                            <?php if (isset($participantsWithCosts[$col])): ?>
                                                <?php
                                                $participantName = $participantsWithCosts[$col];
                                                $participantTotal = 0;
                                                ?>
                                                <td style="width: 50%; padding: 4px; vertical-align: top;">
                                                    <table class="bordered-table" style="width: 100%; margin: 0; margin-top: 4px;">
                                                        <thead>
                                                            <tr>
                                                                <th class="bg-highlight text-left" colspan="2" style="padding: 8px;">
                                                                    <strong><?php echo htmlspecialchars($participantName); ?></strong>
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $count = 1;
                                                            foreach ($costTypes as $costName):
                                                                $costValue = $participantData[$participantName][$costName] ?? 0;
                                                                if ($costValue > 0):
                                                                    $units = 1; // Default units
                                                                    $days = 1;  // Default days
                                                                    $total = $costValue * $units * $days;
                                                                    $participantTotal += $total;
                                                                    $grandTotal += $total;
                                                            ?>
                                                            <tr>
                                                                <td style="width: 60%; padding: 6px; font-size: 12px;">
                                                                    <strong><?php echo $count; ?>. <?php echo htmlspecialchars($costName); ?>:</strong>
                                                                </td>
                                                                <td style="width: 40%; padding: 6px; text-align: right; font-size: 12px;">
                                                                    <strong>$<?php echo number_format($total, 2); ?></strong>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                                    $count++;
                                                                endif;
                                                            endforeach;
                                                            ?>
                                                            <tr style="background-color: #f8f9fa; border-top: 2px solid #28a745;">
                                                                <td style="padding: 6px; font-size: 12px;">
                                                                    <strong>Total:</strong>
                                                                </td>
                                                                <td style="padding: 6px; text-align: right; font-size: 12px;">
                                                                    <strong>$<?php echo number_format($participantTotal, 2); ?></strong>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                                <?php 
                                                $participantTotals[$participantName] = $participantTotal;
                                                ?>
                                            <?php else: ?>
                                                <td style="width: 50%; padding: 4px;"></td>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </tr>
                                    <?php endfor; ?>
                                </table>
                                
                                <!-- Grand Total Summary -->
                                <div class="mt-4">
                                    <table class="bordered-table" style="width: 100%;">
                                        <tfoot>
                                            <tr style="background-color: #e8f5e8;">
                                                <th class="bg-highlight text-right" style="padding: 12px;">
                                                    <strong>Grand Total: $<?php echo number_format($grandTotal, 2); ?></strong>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Special Tickets Structure (id 1) -->
                            <?php foreach ($ticketsData as $costName => $participants): ?>
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
                                        $unitCost = $item['unit_cost'];
                                        $units = 1; // Default units for internal participants
                                        $days = 1;  // Default days for internal participants
                                        $total = $unitCost * $units * $days;
                                        $groupTotal += $total;
                                        ?>
                                        <tr>
                                            <td><?php echo $count; ?></td>
                                            <td><?php echo htmlspecialchars($item['participant_name']); ?></td>
                                            <td class="text-right">$<?php echo number_format($unitCost, 2); ?></td>
                                            <td class="text-right"><?php echo $units; ?></td>
                                            <td class="text-right"><?php echo $days; ?></td>
                                            <td class="text-right">$<?php echo number_format($total, 2); ?></td>
                                            <td>Internal participant - <?php echo htmlspecialchars($item['staff']->position ?? 'Staff'); ?></td>
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
                            // Prepare data for crosstab structure
                            $participantData = [];
                            $costTypes = [];
                            $ticketsData = []; // Special handling for Tickets (id 1)
                            
                            foreach ($budgetData['external_participants'] as $participant) {
                                if (isset($participant['costs']) && is_array($participant['costs'])) {
                                    $participantName = $participant['name'] ?? 'N/A';
                                    
                                    foreach ($participant['costs'] as $costName => $costValue) {
                                        // Check if this is Tickets (id 1) - keep current structure
                                        if (strtolower($costName) === 'tickets' || (isset($participant['cost_type_id']) && $participant['cost_type_id'] == 1)) {
                                            if (!isset($ticketsData[$costName])) {
                                                $ticketsData[$costName] = [];
                                            }
                                            $ticketsData[$costName][] = [
                                                'participant' => $participant,
                                                'participant_name' => $participantName,
                                                'unit_cost' => $costValue
                                            ];
                                        } else {
                                            // Regular crosstab structure
                                            if (!isset($participantData[$participantName])) {
                                                $participantData[$participantName] = [];
                                            }
                                            $participantData[$participantName][$costName] = $costValue;
                                            if (!in_array($costName, $costTypes)) {
                                                $costTypes[] = $costName;
                                            }
                                        }
                                    }
                                }
                            }
                            ?>
                            
                            <!-- Individual Participant Cost Breakdown (non-Tickets) - 3 per row -->
                            <?php if (!empty($participantData)): ?>
                                <?php
                                $grandTotal = 0;
                                $participantTotals = []; // Track total per participant
                                $participantsWithCosts = []; // Array to store participants with costs
                                
                                // Initialize participant totals and collect participants with costs
                                foreach (array_keys($participantData) as $participantName) {
                                    $participantTotals[$participantName] = 0;
                                    
                                    // Check if participant has any costs
                                    $hasCosts = false;
                                    foreach ($costTypes as $costName) {
                                        $costValue = $participantData[$participantName][$costName] ?? 0;
                                        if ($costValue > 0) {
                                            $hasCosts = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($hasCosts) {
                                        $participantsWithCosts[] = $participantName;
                                    }
                                }
                                ?>
                                
                                <!-- Mega Table for 2 tables per row -->
                                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                                    <?php
                                    $participantsCount = count($participantsWithCosts);
                                    $rows = ceil($participantsCount / 2);
                                    
                                    for ($row = 0; $row < $rows; $row++):
                                        $startIndex = $row * 2;
                                        $endIndex = min($startIndex + 2, $participantsCount);
                                    ?>
                                    <tr>
                                        <?php for ($col = $startIndex; $col < $endIndex; $col++): ?>
                                            <?php if (isset($participantsWithCosts[$col])): ?>
                                                <?php
                                                $participantName = $participantsWithCosts[$col];
                                                $participantTotal = 0;
                                                ?>
                                                <td style="width: 50%; padding: 4px; vertical-align: top;">
                                                    <table class="bordered-table" style="width: 100%; margin: 0; margin-top: 4px;">
                                                        <thead>
                                                            <tr>
                                                                <th class="bg-highlight text-left" colspan="2" style="padding: 8px;">
                                                                    <strong><?php echo htmlspecialchars($participantName); ?></strong>
                                                                </th>
      </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $count = 1;
                                                            foreach ($costTypes as $costName):
                                                                $costValue = $participantData[$participantName][$costName] ?? 0;
                                                                if ($costValue > 0):
                                                                    $units = 1; // Default units
                                                                    $days = 1;  // Default days
                                                                    $total = $costValue * $units * $days;
                                                                    $participantTotal += $total;
                                                                    $grandTotal += $total;
                                                            ?>
                                                            <tr>
                                                                <td style="width: 60%; padding: 6px; font-size: 12px;">
                                                                    <strong><?php echo $count; ?>. <?php echo htmlspecialchars($costName); ?>:</strong>
                                                                </td>
                                                                <td style="width: 40%; padding: 6px; text-align: right; font-size: 12px;">
                                                                    <strong>$<?php echo number_format($total, 2); ?></strong>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                                    $count++;
                                                                endif;
                                                            endforeach;
                                                            ?>
                                                            <tr style="background-color: #f8f9fa; border-top: 2px solid #28a745;">
                                                                <td style="padding: 6px; font-size: 12px;">
                                                                    <strong>Total:</strong>
                                                                </td>
                                                                <td style="padding: 6px; text-align: right; font-size: 12px;">
                                                                    <strong>$<?php echo number_format($participantTotal, 2); ?></strong>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                                <?php 
                                                $participantTotals[$participantName] = $participantTotal;
                                                ?>
                                            <?php else: ?>
                                                <td style="width: 50%; padding: 4px;"></td>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </tr>
                                    <?php endfor; ?>
                                </table>
                                
                                <!-- Grand Total Summary -->
                                <div class="mt-4">
                                    <table class="bordered-table" style="width: 100%;">
                                        <tfoot>
                                            <tr style="background-color: #e8f5e8;">
                                                <th class="bg-highlight text-right" style="padding: 12px;">
                                                    <strong>Grand Total: $<?php echo number_format($grandTotal, 2); ?></strong>
                                                </th>
      </tr>
                                        </tfoot>
    </table>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Special Tickets Structure (id 1) -->
                            <?php foreach ($ticketsData as $costName => $participants): ?>
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
                                            <td><?php echo htmlspecialchars($item['participant_name']); ?></td>
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
                                        <th class="bg-highlight">Quantity</th>
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
                                        // Handle both old format (days) and new format (quantity)
                                        $quantity = $cost['quantity'] ?? $cost['days'] ?? 1;
                                        $total = $unitCost * $quantity;
                                        $otherGrandTotal += $total;
                                        ?>
                                        <tr>
                                            <td><?php echo $count; ?></td>
                                            <td><?php echo htmlspecialchars($cost['cost_type'] ?? 'N/A'); ?></td>
                                            <td class="text-right">$<?php echo number_format($unitCost, 2); ?></td>
                                            <td class="text-right"><?php echo $quantity; ?></td>
                                            <td class="text-right">$<?php echo number_format($total, 2); ?></td>
                                            <td><?php echo htmlspecialchars($cost['description'] ?? 'N/A'); ?></td>
      </tr>
                                        <?php $count++; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
      <tr>
                                        <th class="bg-highlight text-right" colspan="4">Other Costs Total</th>
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