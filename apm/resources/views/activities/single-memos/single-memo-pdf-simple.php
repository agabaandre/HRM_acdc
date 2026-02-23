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
 
  <!-- Document Title -->
  <h1 class="document-title">Interoffice Memorandum</h1>

   
  <?php
    // Use centralized PrintHelper
    use App\Helpers\PrintHelper;

    // Use PrintHelper for getApprovalDate
    if (!function_exists('getApprovalDate')) {
        function getApprovalDate($staffId, $matrixApprovalTrails, $order) {
            return PrintHelper::getApprovalDate($staffId, $matrixApprovalTrails, $order);
        }
    }

    // Helper function to render approver info
    if (!function_exists('renderApproverInfo')) {
        function renderApproverInfo($approver, $role, $section, $matrix) {
            PrintHelper::renderApproverInfo($approver, $role, $section, $matrix);
        }
    }

    // Helper function to render signature for single memos
    if (!function_exists('renderSignature')) {
        function renderSignature($approver, $order, $activity_approval_trails, $activity) {
            PrintHelper::renderSignature($approver, $order, $activity_approval_trails, $activity);
        }
    }

    // Helper function to get latest approval for a specific order
    if (!function_exists('getLatestApprovalForOrder')) {
        function getLatestApprovalForOrder($activityApprovalTrails, $order) {
            return PrintHelper::getLatestApprovalForOrder($activityApprovalTrails, $order);
        }
    }

    // Helper function to render budget signature with OIC support
    if (!function_exists('renderBudgetSignature')) {
        function renderBudgetSignature($approval, $activity, $label = '') {
            PrintHelper::renderBudgetSignature($approval, $activity, $label);
        }
    }

    // Helper function to render budget approver info with OIC support
    if (!function_exists('renderBudgetApproverInfo')) {
        function renderBudgetApproverInfo($approval, $label = '') {
            PrintHelper::renderBudgetApproverInfo($approval, $label);
        }
    }

    // Get division category safely
    $divisionCategory = null;
    if (isset($matrix->division) && isset($matrix->division->category)) {
        $divisionCategory = $matrix->division->category;
    }

    // Organize approvers by section using activity approval trails for single memos
    $organizedApprovers = PrintHelper::organizeApproversBySection(
        $activity->id ?? null,
        'App\Models\Activity',
        $matrix->division_id ?? null,
        $matrix->forward_workflow_id ?? null,
        $divisionCategory
    );

    // Generate file reference once
    $activity_refernce = 'N/A';
    if (isset($activity)) {
        $divisionName = $matrix->division->division_name ?? '';
        $divisionShortName = $matrix->division->division_short_name ?? '';
        
        // Use PrintHelper for generating short code
        
        // Use division_short_name if available, otherwise generate from division_name
        if (!empty($divisionShortName)) {
            $shortCode = strtoupper($divisionShortName);
        } else {
            $shortCode = $divisionName ? PrintHelper::generateShortCodeFromDivision($divisionName) : 'DIV';
        }
        
        $year = date('Y', strtotime($matrix->created_at ?? 'now'));
        $activityId = $activity->id ?? 'N/A';
        $activity_refernce = "AU/CDC/{$shortCode}/IM/{$matrix->quarter}/{$year}/{$activityId}";
    }

      // Define the order of sections: TO, THROUGH, FROM
      $sectionOrder = ['to', 'through', 'from'];

      // Section labels in sentence case
      $sectionLabels = [
        'to' => 'To:',
        'through' => 'Through:',
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
                    <?php renderApproverInfo($approver, $approver['role'], $section, $matrix); ?>
      </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    <?php 
                    $order = $approver['order'];
                    if ($order === 'division_head') {
                        $order = 1; // Use level 1 for division head
                    }
                    renderSignature($approver, $order, $activity->approvalTrails, $activity); 
                    ?>
      </td>
                <?php if ($section === $sectionOrder[0] && $index === 0): // Only output the Date/FileNo cell once ?>
                    <td style="width: 28%; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
                        <div class="text-right">
                  <div style="margin-bottom: 20px;">
                                <strong class="section-label">Date:</strong>
                                <span style="font-weight: bold;"><?php echo isset($matrix->created_at) ? (is_object($matrix->created_at) ? $matrix->created_at->format('j F Y') : date('j F Y', strtotime($matrix->created_at))) : date('j F Y'); ?></span>
          </div>
                  <div>
                    <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($activity->document_number); ?></span>
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
                        <div class="approver-title"><?php echo htmlspecialchars($matrix->division->division_name ?? ''); ?></div>
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
                                <span style="font-weight: bold;"><?php echo isset($matrix->created_at) ? (is_object($matrix->created_at) ? $matrix->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($matrix->created_at))) : date('j F Y H:i'); ?></span>
                </div>
                <div>
                                <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($activity->document_number ?? 'N/A'); ?></span>
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
    <td style="width: 88%; text-align: left; vertical-align: top;" class="subject-text"><?php echo htmlspecialchars($activity->activity_title ?? 'N/A'); ?></td>
  </tr>
 </table>

<!-- Background -->
 <table class="mb-15 mt-neg20">
  <tr>
    <td style="width: 12%; text-align: left; vertical-align: top;"><strong class="section-label">Background:</strong></td>
  </tr>
  <tr>
   <td class="justify-text" style="width: 100%; text-align: justify; vertical-align: top;"><div class="justify-text"><?=trim(preg_replace('/[a-zA-Z0-9.#\s]+\s*\{[^}]*\}/', '', strip_tags($activity->background ?? '')));?></div></td>
  </tr>
 </table>
  
  <div>
    <div class="page-break"></div>
    <div class="section-label mb-15"><strong>Activity Information</strong></div>
  
    <table class="form-table mb-15" role="table" aria-label="Activity Information">
    <tr>
        <th scope="row">Division</th>
        <td><?php echo htmlspecialchars($matrix->division->division_name ?? 'N/A'); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Activity Type</th>
        <td><?php echo htmlspecialchars($activity->requestType->name ?? 'N/A'); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Activity Start Date</th>
        <td><?php echo isset($activity->date_from) ? date('d/m/Y', strtotime($activity->date_from)) : 'N/A'; ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Activity End Date</th>
        <td><?php echo isset($activity->date_to) ? date('d/m/Y', strtotime($activity->date_to)) : 'N/A'; ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Location(s)</th>
        <td>
        <?php foreach($locations as $loc): ?>
            <span><?php echo htmlspecialchars($loc->name); ?></span>
        <?php endforeach; ?>
          <span class="fill line"></span>
        </td>
      </tr>
      <tr>
        <th scope="row">Budget Type</th>
        <td><?php echo htmlspecialchars($activity->fundType->name ?? 'N/A'); ?><span class="fill line"></span></td>
      </tr>
    </table>
    
    <div class="section-label mb-15"><strong>Internal Participants</strong></div>     
    <table class="bordered-table mb-15">
                            <thead>
                                <tr>
                                    <td class="bg-highlight">#</td>
                                    <th class="bg-highlight">Name</th>
                                    <th class="bg-highlight">Division</th>
                                    <th class="bg-highlight">Contract Status</th>
                                    <th class="bg-highlight">Job Title</th>
                                    <th class="bg-highlight">Duty Station</th>
                                  
                                    <th class="bg-highlight">Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $count = 1;
                                ?>
                                <?php foreach($internalParticipants as $entry): ?>
                                    <tr><td><?php echo $count; ?></td>
                                            <td><?php echo htmlspecialchars($entry['staff']->name ?? 'N/A'); ?></td>
                                             <td><?php echo htmlspecialchars($entry['staff']->division_name ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($entry['staff']->status ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($entry['staff']->job_name ?? 'N/A'); ?></td>
                                          <td><?php echo htmlspecialchars($entry['staff']->duty_station_name ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($entry['participant_days'] ?? '-'); ?></td>
                                    </tr>
                                    <?php
                                        $count++;
                                    ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>


    <?php 
    // Check if any fund code is intramural (1) or extramural (2)
    $hasBudgetSection = false;
    foreach($fundCodes ?? [] as $fundCode) {
        if($fundCode->fundType->id == 1 || $fundCode->fundType->id == 2) {
            $hasBudgetSection = true;
            break;
        }
    }
    
    if($hasBudgetSection): 
    ?>
    <div class="page-break"></div>
              <div class="section-label mb-15"><strong>Budget Details</strong></div>
         
             <?php foreach($fundCodes ?? [] as $fundCode): ?>

           
             
                 <h5 style="font-weight: 600;"> <?php echo htmlspecialchars($fundCode->activity); ?> - <?php echo htmlspecialchars($fundCode->code); ?> - (<?php echo htmlspecialchars($fundCode->fundType->name); ?>) </h5>

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
                              $grandTotal = 0;
                            ?>
                           
                            <?php
                            // Parse budget breakdown from activity
                            $budgetBreakdown = null;
                            if ($activity->budget_breakdown) {
                                $budgetBreakdown = is_string($activity->budget_breakdown) 
                                    ? json_decode($activity->budget_breakdown, true) 
                                    : $activity->budget_breakdown;
                            }
                            
                            // Display budget items from budget_breakdown
                            if ($budgetBreakdown && is_array($budgetBreakdown)) {
                                unset($budgetBreakdown['grand_total']); // Remove grand total from iteration
                                
                                foreach ($budgetBreakdown as $codeId => $items) {
                                    if (is_array($items)) {
                                        foreach ($items as $item) {
                                            $unitCost = floatval($item['unit_cost'] ?? 0);
                                            $units = floatval($item['units'] ?? 0);
                                            $days = floatval($item['days'] ?? 1);
                                            
                                            // Use days when greater than 1, otherwise just unit_cost * units
                                            if ($days > 1) {
                                                $itemTotal = $unitCost * $units * $days;
                                            } else {
                                                $itemTotal = $unitCost * $units;
                                            }
                                            
                                            $grandTotal += $itemTotal;
                                            ?>
                                            <tr>
                                                <td><?php echo $count; ?></td>
                                                <td class="text-right"><?php echo htmlspecialchars($item['cost'] ?? ''); ?></td>
                                                <td class="text-right"><?php echo number_format($unitCost, 2); ?></td>
                                                <td class="text-right"><?php echo $units; ?></td>
                                                <td class="text-right"><?php echo $days; ?></td>
                                                <td class="text-right"><?php echo number_format($itemTotal, 2); ?></td>
                                                <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                                            </tr>
                                            <?php
                                            $count++;
                                        }
                                    }
                                }
                            } else {
                                // Fallback to activity_budget if budget_breakdown is not available
                                foreach($activity->activity_budget as $item): ?>
                                    <?php
                                        $total = $item->unit_cost * $item->units*$item->days;
                                        $grandTotal+=$total;
                                    ?>
                                    <tr>
                                        <td><?php echo $count; ?></td>
                                        <td class="text-right"><?php echo htmlspecialchars($item->cost); ?></td>
                                        <td class="text-right"><?php echo number_format($item->unit_cost, 2); ?></td>
                                        <td class="text-right"><?php echo $item->units; ?></td>
                                        <td class="text-right"><?php echo $item->days; ?></td>
                                        <td class="text-right"><?php echo number_format($item->total, 2); ?></td>
                                        <td><?php echo htmlspecialchars($item->description); ?></td>
                                    </tr>
                                <?php endforeach;
                            }
                            ?>

                            <?php
                                $count++;
                            ?>
                            
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="bg-highlight text-right" colspan="5">Grand Total</th>
                                
                                <th class="bg-highlight text-right">USD <?php echo number_format($grandTotal ?? 0, 2); ?></th>
                                <th class="bg-highlight"></th>
                            </tr>
                        </tfoot>
                    </table>
                   
                </div>
     <div class="section-label"><strong>Request for Approval</strong></div>
     <div class="justify-text" style="padding: 2px;"><?php echo trim(preg_replace('/[a-zA-Z0-9.#\s]+\s*\{[^}]*\}/', '', strip_tags($activity->activity_request_remarks ?? 'N/A'))); ?></div>

    <?php if($fundCode->fundType->id == 1): ?>
    <div class="page-break"></div>

    <!-- Right-side memo meta (stacked, borderless) -->
    <div class="topbar">
      <div class="meta" aria-label="Memo metadata">
        <span class="memo-id"><?php echo $activity->document_number ?? 'N/A'; ?></span><br/>
        <span class="date">Date: <?php echo $activity->created_at->format('j F Y'); ?></span>
      </div>
    </div>

    <!-- Main form table (borderless by default) -->
    <table class="form-table" role="table" aria-label="Payment details">
      <tr>
        <th scope="row">Payee/Staff<br/><span class="muted">(Vendors)</span></th>
        <td>
        <?php echo $activity->responsiblePerson->title.' '.$activity->responsiblePerson->fname.' '.$activity->responsiblePerson->lname.' '.$activity->responsiblePerson->oname; ?>
          <span class="fill line" aria-hidden="true"></span>
        </td>
      </tr>
      <tr>
        <th scope="row">Purpose of Payment</th>
        <td><?php echo htmlspecialchars($activity->activity_title); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Department Name<br/><span class="muted">(Cost Center)</span></th>
        <td>Africa CDC - <?php echo $matrix->division->division_name ?? ''; ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Project/Program<br/><span class="muted">(Fund Center)</span></th>
        <td><?php echo htmlspecialchars($fundCode->code); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Fund <span class="muted">(Member State or Partner/Donor)</span></th>
        <td><?php echo htmlspecialchars($fundCode->fund); ?><span class="fill line"></span></td>
      </tr>
    </table>
<?php
    // Get financial approvers dynamically based on workflow definition
    $financialApprovers = PrintHelper::getFinancialApprovers($activity->approvalTrails, $matrix->forward_workflow_id ?? 1);
    
    // Extract specific approvers for easier access
    $sfoApproval = $financialApprovers['Finance Officer'] ?? null;
    $divisionHeadApproval = $financialApprovers['Head of Division'] ?? null;
    $directorFinanceApproval = $financialApprovers['Director Finance'] ?? null;
    $ddgApproval = $financialApprovers['Deputy Director General'] ?? null;
?>
    <!-- Budget / Certification (table-only, borderless unless specified inline) -->
    <table class="budget-table" role="table" aria-label="Budget and Certification">
      <tr>
        <td class="head">Strategic Axis Budget Balance (Certified by SFO)</td>
        <td>USD</td>
        <td><?php if(!empty($activity->available_budget)) echo number_format($activity->available_budget ?? 0, 2); else echo ''; ?></td>
        <td>Date: <?=$sfoApproval ? (is_object($sfoApproval->created_at) ? $sfoApproval->created_at->format('j F Y') : date('j F Y', strtotime($sfoApproval->created_at))) : 'N/A';?></td>
      </tr>
      <tr>
        <td></td>
        <td></td>
        <td></td>
        <td>
           
            <?php renderBudgetSignature($sfoApproval, $activity); ?>
        </td>
      </tr>
      <tr>
        <td class="head">Estimated cost</td>
        <td>USD</td>
        <td><?php echo number_format($grandTotal, 2); ?></td>
        <td>Name: <?php 
            if ($sfoApproval) {
                $isOic = !empty($sfoApproval->oic_staff_id);
                $staff = $isOic ? $sfoApproval->oicStaff : $sfoApproval->staff;
                if ($staff) {
                    $name = $staff->title.' '.$staff->fname.' '.$staff->lname.' '.$staff->oname;
                   
                    if ($isOic) $name .= ' (OIC)';
                    echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
                    echo '<div class="approver-title">' . htmlspecialchars($sfoApproval->workflowDefinition->role) . '</div>';
                } else {
                    echo 'N/A';
                }
            } else {
                echo 'N/A';
            }
        ?></td>
      </tr>
    </table>

    <!-- Signatures (borderless by default). Last column adds ONLY a left border inline -->
    <table class="sig-table" role="table" aria-label="Approvals">
      <tr>
        <td>Signed (Prepared by):</td>
        <td>
          <?php renderBudgetApproverInfo($divisionHeadApproval); ?>
        </td>
        <td style="border-left:1px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
          <span class="fill">
            <?php renderBudgetSignature($divisionHeadApproval, $activity); ?>
          </span>
        </td>
      </tr>
      <tr>
        <td>Signed (Endorsed by):</td>
        <td>
          <?php renderBudgetApproverInfo($directorFinanceApproval); ?>
        </td>
        <td style="border-left:1px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
          <span class="fill">
            <?php renderBudgetSignature($directorFinanceApproval, $activity); ?>
          </span>
        </td>
      </tr>
      <tr>
        <td>Signed (Approved by):</td>
        <td>
          <?php renderBudgetApproverInfo($ddgApproval); ?>
        </td>
        <td style="border-left:1px solid #d8dee9; border-top:none; border-right:none; border-bottom:none;">
          <span class="fill">
            <?php renderBudgetSignature($ddgApproval, $activity); ?>
          </span>
        </td>
      </tr>
    </table>

    <?php 
      endif;  
  endforeach; 
    
    endif; // Close the budget section condition
    ?>
</body>
</html>