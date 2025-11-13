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
  <?php if (!empty($changes)): ?>
    <div class="section-label mb-15"><strong>Changes Requested</strong></div>
    <table class="changes-table">
      <thead>
        <tr>
          <th style="width: 25%;">Change Type</th>
          <th style="width: 37.5%;">Original Value</th>
          <th style="width: 37.5%;">Changed Value</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($changes as $change): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($change['type']); ?></strong></td>
            <td><?php echo htmlspecialchars($change['original']); ?></td>
            <td><?php echo htmlspecialchars($change['changed']); ?></td>
          </tr>
        <?php endforeach; ?>
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

