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
        font-size: 12px; 
        color: #64748b; 
        line-height: 1.2;
    }

    /* Changes table */
    .changes-table {
        margin: 15px 0;
        border: 1px solid #e5e7eb;
    }
    .changes-table th {
        background: #f9fafb;
        font-weight: bold;
        padding: 10px;
        text-align: left;
        border-bottom: 2px solid #e5e7eb;
    }
    .changes-table td {
        padding: 10px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
    }
    .changes-table tr:last-child td {
        border-bottom: none;
    }

    /* Approval trail table */
    .approval-trail-table {
        margin: 15px 0;
        border: 1px solid #e5e7eb;
    }
    .approval-trail-table th {
        background: #f9fafb;
        font-weight: bold;
        padding: 10px;
        text-align: left;
        border-bottom: 2px solid #e5e7eb;
    }
    .approval-trail-table td {
        padding: 10px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
    }

    /* Page break */
    .page-break {
        page-break-before: always;
        margin-top: 30px;
    }

    .mb-15 { margin-bottom: 15px; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .bg-highlight { background-color: #fef3c7; }
</style>
</head>
<body>
 
  <!-- Change Request Document -->
  <!-- Document Title -->
  <h1 class="document-title">
  INTEROFFICE MEMORANDUM  
  <?php //echo ($changeRequest->has_budget_id_changed || $changeRequest->has_budget_breakdown_changed) ? 'Addendum' : 'Change Request'; ?></h1>
  
  <?php
    // Use centralized PrintHelper
    use App\Helpers\PrintHelper;
    use Illuminate\Support\Facades\DB;

    // Helper function to render approver info
    if (!function_exists('renderApproverInfo')) {
      function renderApproverInfo($approver, $role, $section, $changeRequest) {
        PrintHelper::renderApproverInfo($approver, $role, $section, $changeRequest);
        }
    }

    // Helper function to render signature
    if (!function_exists('renderSignature')) {
      function renderSignature($approver, $order, $approvalTrails, $changeRequest) {
        PrintHelper::renderSignature($approver, $order, $approvalTrails, $changeRequest);
    }
    }

    // Get division information
    $divisionName = $changeRequest->division->division_name ?? '';
    $divisionShortName = $changeRequest->division->division_short_name ?? '';
    
    // Use division_short_name if available, otherwise generate from division_name
    if (!empty($divisionShortName)) {
        $shortCode = strtoupper($divisionShortName);
    } else {
        $shortCode = $divisionName ? PrintHelper::generateShortCodeFromDivision($divisionName) : 'DIV';
    }

    // Get change request approval trails
    $crApprovalTrails = $changeRequest->approvalTrails ?? collect();
    if (!$crApprovalTrails instanceof \Illuminate\Support\Collection) {
        $crApprovalTrails = collect($crApprovalTrails);
    }

    // Get parent memo document number
    $parentDocNumber = 'N/A';
    if ($parentMemo) {
        $parentDocNumber = $parentMemo->document_number ?? 'N/A';
    }

    // Get division category for workflow filtering
    $divisionCategory = null;
    if (isset($changeRequest->division) && isset($changeRequest->division->category)) {
        $divisionCategory = $changeRequest->division->category;
    }

    // Organize approvers by section using helper (same as non-travel memo)
    $organizedApprovers = PrintHelper::organizeApproversBySection(
        $changeRequest->id ?? null,
        'App\Models\ChangeRequest',
        $changeRequest->division_id ?? null,
        $changeRequest->forward_workflow_id ?? null,
        $divisionCategory
    );

    // Define the order of sections: TO, THROUGH, FROM (excluding 'others')
    $sectionOrder = ['to', 'through', 'from'];

    // Section labels in sentence case
    $sectionLabels = [
        'to' => 'To:',
        'through' => 'Through:',
        'from' => 'From:'
    ];

    // Filter out sections that have no approvers
    $sectionsWithApprovers = [];
    foreach ($sectionOrder as $section) {
        if (isset($organizedApprovers[$section]) && count($organizedApprovers[$section]) > 0) {
            $sectionsWithApprovers[] = $section;
        }
    }

    // Calculate total rows needed for rowspan (only for sections with approvers)
    $totalRows = 0;
    foreach ($sectionsWithApprovers as $section) {
        $totalRows += count($organizedApprovers[$section]);
    }
    $dateFileRowspan = $totalRows;
  ?>
  
  <table class="mb-15">
    <?php if (!empty($sectionsWithApprovers)): ?>
      <?php foreach ($sectionsWithApprovers as $sectionIndex => $section): ?>
        <?php foreach ($organizedApprovers[$section] as $index => $approver): ?>
          <tr>
                <td style="width: 12%; vertical-align: top;">
                    <strong class="section-label"><?php echo $sectionLabels[$section] ?? (strtoupper($section) . ':'); ?></strong>
            </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    <?php 
                    // Centralized rendering handles OIC/name/role formatting consistently
                    renderApproverInfo($approver, $approver['role'] ?? 'Approver', $section, $changeRequest);
                    ?>
      </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    <?php 
                    $order = $approver['order'];
                    if ($order === 'division_head') {
                        $order = 1; // Use level 1 for division head
                    }
                    renderSignature($approver, $order, $crApprovalTrails, $changeRequest); 
                    ?>
      </td>
                <?php if ($sectionIndex === 0 && $index === 0): // Only output the Date/FileNo cell once ?>
                    <td style="width: 28%; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
                        <div class="text-right">
                  <div style="margin-bottom: 20px;">
                                <strong class="section-label">Date:</strong>
                                <span style="font-weight: bold;"><?php echo isset($changeRequest->created_at) ? (is_object($changeRequest->created_at) ? $changeRequest->created_at->format('j F Y') : date('j F Y', strtotime($changeRequest->created_at))) : date('j F Y'); ?></span>
          </div>
                  <div>
                    <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($changeRequest->document_number ?? 'N/A'); ?></span>
          </div>
        </div>
      </td>
            <?php endif; ?>
    </tr>
          <?php endforeach; ?>
      <?php endforeach; ?>
    <?php else: ?>
      <!-- Fallback: If no approvers found, show basic structure with date/file -->
      <tr>
        <td style="width: 12%; vertical-align: top;">
          <strong class="section-label">From:</strong>
        </td>
        <td style="width: 30%; vertical-align: top; text-align: left;">
          <?php
          $divisionHeadName = '';
          if ($changeRequest->division && $changeRequest->division->division_head) {
              $divisionHead = \App\Models\Staff::find($changeRequest->division->division_head);
              if ($divisionHead) {
                  $divisionHeadName = $divisionHead->fname . ' ' . $divisionHead->lname;
              }
          }
          ?>
          <div class="approver-name"><?php echo htmlspecialchars($divisionHeadName ?: 'Division Head'); ?></div>
          <div class="approver-title">Head of Division (HOD)</div>
          <div class="approver-title"><?php echo htmlspecialchars($divisionName); ?></div>
        </td>
        <td style="width: 30%; vertical-align: top; text-align: left;">
          <!-- Signature space -->
        </td>
        <td style="width: 28%; vertical-align: top; text-align: right;">
          <div>
            <div style="margin-bottom: 20px;">
              <strong class="section-label">Date:</strong>
              <span style="font-weight: bold;"><?php echo isset($changeRequest->created_at) ? (is_object($changeRequest->created_at) ? $changeRequest->created_at->format('j F Y') : date('j F Y', strtotime($changeRequest->created_at))) : date('j F Y'); ?></span>
            </div>
            <div>
              <br><br>
              <strong class="section-label">File No:</strong><br>
              <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($changeRequest->document_number ?? 'N/A'); ?></span>
            </div>
          </div>
        </td>
      </tr>
    <?php endif; ?>
  </table>

  <!-- Subject -->
  <table class="mb-15">
    <tr>
      <td style="width: 12%; text-align: left; vertical-align: top;"><strong class="section-label">Subject:</strong></td>
      <td style="width: 88%; text-align: left; vertical-align: top;" class="subject-text">
        <?php echo ($changeRequest->has_budget_id_changed || $changeRequest->has_budget_breakdown_changed) ? 'Addendum' : 'Change Request'; ?> to Memo <?php echo htmlspecialchars($parentDocNumber); ?> - <?php echo htmlspecialchars($changeRequest->activity_title ?? 'N/A'); ?>
      </td>
    </tr>
  </table>

  <p>
    Reference is made to the attached approval memo, <strong><?php echo htmlspecialchars($parentDocNumber); ?></strong>, concerning <?php echo htmlspecialchars($changeRequest->activity_title ?? 'N/A'); ?>.
    The following changes are requested:
  </p>

  <!-- Changes List -->
  <?php 
    // Filter out budget and participants from summary table (they'll be shown in detail sections)
    $summaryChanges = array_filter($changes, function($change) {
        $type = strtolower($change['type']);
        return !in_array($type, ['budget', 'internal participants', 'external participants', 'number of participants']);
    });
  ?>
  
  <?php if (!empty($summaryChanges)): ?>
    <div class="section-label mb-15" style="margin-top: 15px;"><strong>Changes Requested</strong></div>
    <table class="changes-table">
      <thead>
        <tr>
          <th style="width: 25%;">Change Type</th>
          <th style="width: 37.5%;">Original Value</th>
          <th style="width: 37.5%;">Changed Value</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summaryChanges as $change): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($change['type']); ?></strong></td>
            <td><?php echo htmlspecialchars($change['original']); ?></td>
            <td><?php echo htmlspecialchars($change['changed']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- Detailed Participants Comparison -->
  <?php if ($changeRequest->has_internal_participants_changed || $changeRequest->has_participant_days_changed): ?>
    <div class="section-label mb-15" style="margin-top: <?php echo !empty($summaryChanges) ? '20px' : '15px'; ?>;"><strong>Internal Participants - Detailed Comparison</strong></div>
    
    <?php
      // Get raw JSON from parent memo database to preserve the structure with international_travel
      $rawParentParticipants = null;
      if ($parentMemo) {
          // Get the table name based on model
          $modelClass = $changeRequest->parent_memo_model;
          $tableName = (new $modelClass)->getTable();
          $rawParentParticipants = DB::table($tableName)->where('id', $changeRequest->parent_memo_id)->value('internal_participants');
      }
      
      $parentParticipants = [];
      
      if ($rawParentParticipants) {
          if (is_string($rawParentParticipants)) {
              // First decode
              $firstDecode = json_decode($rawParentParticipants, true);
              // If result is still a string, decode again (double-encoded JSON)
              if (is_string($firstDecode)) {
                  $parentParticipants = json_decode($firstDecode, true) ?? [];
              } elseif (is_array($firstDecode)) {
                  $parentParticipants = $firstDecode;
              }
          } elseif (is_array($rawParentParticipants)) {
              $parentParticipants = $rawParentParticipants;
          }
      } elseif ($parentMemo) {
          // Fallback to model accessor
          $fallbackParticipants = $parentMemo->internal_participants ?? [];
          if (is_string($fallbackParticipants)) {
              $firstDecode = json_decode($fallbackParticipants, true);
              if (is_string($firstDecode)) {
                  $parentParticipants = json_decode($firstDecode, true) ?? [];
              } elseif (is_array($firstDecode)) {
                  $parentParticipants = $firstDecode;
              }
          } elseif (is_array($fallbackParticipants)) {
              $parentParticipants = $fallbackParticipants;
          }
      }
      
      // Ensure it's always an array
      if (!is_array($parentParticipants)) {
          $parentParticipants = [];
      }
      
      // Get participants from CHANGE REQUEST
      $rawParticipants = DB::table('change_request')->where('id', $changeRequest->id)->value('internal_participants');
      $currentParticipants = [];
      
      if ($rawParticipants) {
          if (is_string($rawParticipants)) {
              // First decode
              $firstDecode = json_decode($rawParticipants, true);
              // If result is still a string, decode again (double-encoded JSON)
              if (is_string($firstDecode)) {
                  $currentParticipants = json_decode($firstDecode, true) ?? [];
              } elseif (is_array($firstDecode)) {
                  $currentParticipants = $firstDecode;
              }
          } elseif (is_array($rawParticipants)) {
              $currentParticipants = $rawParticipants;
          }
      }
      
      // Ensure it's always an array
      if (!is_array($currentParticipants)) {
          $currentParticipants = [];
      }
      
      // Build a map of original participants for comparison
      $originalParticipantMap = [];
      foreach ($parentParticipants as $key => $details) {
          $originalParticipantMap[$key] = [
              'days' => $details['participant_days'] ?? 0,
          ];
      }
    ?>
    
    <table class="changes-table" style="margin-bottom: 20px;">
      <thead>
        <tr>
          <th style="width: 50%;">Original Participants</th>
          <th style="width: 50%;">Changed Participants</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="vertical-align: top; padding: 10px;">
            <?php if (is_array($parentParticipants) && count($parentParticipants) > 0): ?>
              <table style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr style="background: #f9fafb;">
                    <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Name</th>
                    <th style="padding: 8px; text-align: right; border-bottom: 1px solid #e5e7eb;">Days</th>
                    <th style="padding: 8px; text-align: center; border-bottom: 1px solid #e5e7eb;">Travel</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($parentParticipants as $key => $details): ?>
                    <?php
                      $staffName = 'Unknown';
                      $staffId = $key;
                      
                      // Handle different data structures
                      if (isset($details['staff'])) {
                          $staffName = $details['staff']->fname . ' ' . $details['staff']->lname;
                      } elseif (is_numeric($key)) {
                          $staff = \App\Models\Staff::find($key);
                          if ($staff) {
                              $staffName = $staff->fname . ' ' . $staff->lname;
                          }
                      }
                      
                      // Handle international_travel: can be 1, "1", true, or "true"
                      $internationalTravel = $details['international_travel'] ?? 0;
                      $hasInternationalTravel = (intval($internationalTravel) === 1);
                    ?>
                    <tr>
                      <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo htmlspecialchars($staffName); ?></td>
                      <td style="padding: 6px; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo $details['participant_days'] ?? 'N/A'; ?></td>
                      <td style="padding: 6px; text-align: center; border-bottom: 1px solid #e5e7eb;"><?php echo $hasInternationalTravel ? 'Yes' : 'No'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div style="color: #64748b;">No participants</div>
            <?php endif; ?>
          </td>
          <td style="vertical-align: top; padding: 10px;">
            <?php if (is_array($currentParticipants) && count($currentParticipants) > 0): ?>
              <table style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr style="background: #f9fafb;">
                    <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Name</th>
                    <th style="padding: 8px; text-align: right; border-bottom: 1px solid #e5e7eb;">Days</th>
                    <th style="padding: 8px; text-align: center; border-bottom: 1px solid #e5e7eb;">Travel</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($currentParticipants as $key => $details): ?>
                    <?php
                      $staffName = 'Unknown';
                      $staffId = $key;
                      
                      // Handle different data structures
                      if (isset($details['staff'])) {
                          $staffName = $details['staff']->fname . ' ' . $details['staff']->lname;
                      } elseif (is_numeric($key)) {
                          $staff = \App\Models\Staff::find($key);
                          if ($staff) {
                              $staffName = $staff->fname . ' ' . $staff->lname;
                          }
                      }
                      
                      // Handle international_travel: can be 1, "1", true, or "true"
                      $internationalTravel = $details['international_travel'] ?? 0;
                      $hasInternationalTravel = (intval($internationalTravel) === 1);
                      
                      // Check if this participant should be highlighted
                      $shouldHighlight = false;
                      $currentDays = (int)($details['participant_days'] ?? 0);
                      
                      // Check if it's a new participant (not in original)
                      if (!isset($originalParticipantMap[$key])) {
                          $shouldHighlight = true;
                      } else {
                          // Check if days have changed
                          $originalDays = (int)($originalParticipantMap[$key]['days'] ?? 0);
                          if ($currentDays != $originalDays) {
                              $shouldHighlight = true;
                          }
                      }
                    ?>
                    <tr <?php if ($shouldHighlight): ?>style="background-color: #ffe6e6;"<?php endif; ?>>
                      <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo htmlspecialchars($staffName); ?></td>
                      <td style="padding: 6px; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo $details['participant_days'] ?? 'N/A'; ?></td>
                      <td style="padding: 6px; text-align: center; border-bottom: 1px solid #e5e7eb;"><?php echo $hasInternationalTravel ? 'Yes' : 'No'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div style="color: #64748b;">No participants</div>
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- Detailed Budget Comparison -->
  <?php if ($changeRequest->has_budget_breakdown_changed || $changeRequest->has_budget_id_changed): ?>
    <div style="margin-top: 30px;"></div>
    <div class="section-label mb-15" style="margin-top: 20px;"><strong>Budget Breakdown - Detailed Comparison</strong></div>
    
    <?php
      $parentBudgetBreakdown = $parentMemo->budget_breakdown ?? [];
      if (is_string($parentBudgetBreakdown)) {
          $parentBudgetBreakdown = json_decode($parentBudgetBreakdown, true) ?? [];
      }
      $parentTotal = $parentBudgetBreakdown['grand_total'] ?? 0;
      unset($parentBudgetBreakdown['grand_total']);
      
      $currentBudgetBreakdown = $changeRequest->budget_breakdown ?? [];
      if (is_string($currentBudgetBreakdown)) {
          $currentBudgetBreakdown = json_decode($currentBudgetBreakdown, true) ?? [];
      }
      $currentTotal = $currentBudgetBreakdown['grand_total'] ?? 0;
      unset($currentBudgetBreakdown['grand_total']);
      
      // Build a map of original budget items for comparison
      // Key: fundCodeId_itemName, Value: amount
      $originalBudgetMap = [];
      foreach ($parentBudgetBreakdown as $fundCodeId => $items) {
          if (is_array($items)) {
              foreach ($items as $item) {
                  $itemName = $item['cost'] ?? $item['description'] ?? '';
                  $cost = $item['unit_cost'] ?? $item['cost'] ?? 0;
                  $units = $item['units'] ?? $item['days'] ?? 1;
                  $amount = $cost * $units;
                  $key = $fundCodeId . '_' . $itemName;
                  $originalBudgetMap[$key] = $amount;
              }
          }
      }
    ?>
    
    <table class="changes-table" style="margin-bottom: 20px;">
      <thead>
        <tr>
          <th style="width: 50%;">Original Budget</th>
          <th style="width: 50%;">Changed Budget</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="vertical-align: top; padding: 10px;">
            <?php if (count($parentBudgetBreakdown) > 0): ?>
              <table style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr style="background: #f9fafb;">
                    <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Fund Code</th>
                    <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Item</th>
                    <th style="padding: 8px; text-align: right; border-bottom: 1px solid #e5e7eb;">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($parentBudgetBreakdown as $fundCodeId => $items): ?>
                    <?php if (is_array($items)): ?>
                      <?php foreach ($items as $item): ?>
                        <?php
                          $fundCode = \App\Models\FundCode::find($fundCodeId);
                          $cost = $item['unit_cost'] ?? $item['cost'] ?? 0;
                          $units = $item['units'] ?? $item['days'] ?? 1;
                          $total = $cost * $units;
                        ?>
                        <tr>
                          <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo htmlspecialchars($fundCode->code ?? 'N/A'); ?></td>
                          <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo htmlspecialchars($item['cost'] ?? $item['description'] ?? 'N/A'); ?></td>
                          <td style="padding: 6px; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo number_format($total, 2); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <tr style="background: #fef3c7;">
                    <th colspan="2" style="padding: 8px; text-align: right; border-top: 2px solid #e5e7eb;">Total:</th>
                    <th style="padding: 8px; text-align: right; border-top: 2px solid #e5e7eb;"><?php echo number_format($parentTotal, 2); ?></th>
                  </tr>
                </tbody>
              </table>
            <?php else: ?>
              <div style="color: #64748b;">No budget breakdown available</div>
            <?php endif; ?>
          </td>
          <td style="vertical-align: top; padding: 10px;">
            <?php if (count($currentBudgetBreakdown) > 0): ?>
              <table style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr style="background: #f9fafb;">
                    <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Fund Code</th>
                    <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Item</th>
                    <th style="padding: 8px; text-align: right; border-bottom: 1px solid #e5e7eb;">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($currentBudgetBreakdown as $fundCodeId => $items): ?>
                    <?php if (is_array($items)): ?>
                      <?php foreach ($items as $item): ?>
                        <?php
                          $fundCode = \App\Models\FundCode::find($fundCodeId);
                          $itemName = $item['cost'] ?? $item['description'] ?? '';
                          $cost = $item['unit_cost'] ?? $item['cost'] ?? 0;
                          $units = $item['units'] ?? $item['days'] ?? 1;
                          $total = $cost * $units;
                          
                          // Check if this budget item should be highlighted
                          $shouldHighlight = false;
                          $key = $fundCodeId . '_' . $itemName;
                          
                          // Check if it's a new item (not in original)
                          if (!isset($originalBudgetMap[$key])) {
                              $shouldHighlight = true;
                          } else {
                              // Check if amount has changed (with small tolerance for floating point)
                              $originalAmount = $originalBudgetMap[$key];
                              if (abs($total - $originalAmount) > 0.01) {
                                  $shouldHighlight = true;
                              }
                          }
                        ?>
                        <tr <?php if ($shouldHighlight): ?>style="background-color: #ffe6e6;"<?php endif; ?>>
                          <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo htmlspecialchars($fundCode->code ?? 'N/A'); ?></td>
                          <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo htmlspecialchars($itemName ?: 'N/A'); ?></td>
                          <td style="padding: 6px; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo number_format($total, 2); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <tr style="background: #d4edda;">
                    <th colspan="2" style="padding: 8px; text-align: right; border-top: 2px solid #e5e7eb;">Total:</th>
                    <th style="padding: 8px; text-align: right; border-top: 2px solid #e5e7eb;"><?php echo number_format($currentTotal, 2); ?></th>
                  </tr>
                </tbody>
              </table>
            <?php else: ?>
              <div style="color: #64748b;">No budget breakdown available</div>
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>


  <!-- Parent Memo -->
  <?php if ($parentPdfHtml): ?>
    <div class="page-break"></div>
    <div class="section-label mb-15"><strong>Original Approval Memo</strong></div>
    
    <!-- The parent memo HTML will be embedded here -->
    <?php echo $parentPdfHtml; ?>
  <?php endif; ?>

</body>
</html>

