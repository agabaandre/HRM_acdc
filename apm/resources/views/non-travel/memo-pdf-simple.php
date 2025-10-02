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
    function getApprovalDate($staffId, $approvalTrails, $order) {
        return PrintHelper::getApprovalDate($staffId, $approvalTrails, $order);
    }

    // Helper function to render approver info
    function renderApproverInfo($approver, $role, $section, $nonTravel) {
        PrintHelper::renderApproverInfo($approver, $role, $section, $nonTravel);
    }

    // Helper function to render signature
    function renderSignature($approver, $order, $matrix_approval_trails, $nonTravel) {
        PrintHelper::renderSignature($approver, $order, $matrix_approval_trails, $nonTravel);
    }

    // Helper function to get latest approval for a specific order
    function getLatestApprovalForOrder($approvalTrails, $order) {
        return PrintHelper::getLatestApprovalForOrder($approvalTrails, $order);
    }

    // Helper function to render budget signature with OIC support
    function renderBudgetSignature($approval, $nonTravel, $label = '') {
        PrintHelper::renderBudgetSignature($approval, $nonTravel, $label);
    }

    // Helper function to render budget approver info with OIC support
    function renderBudgetApproverInfo($approval, $label = '') {
        PrintHelper::renderBudgetApproverInfo($approval, $label);
    }

    // Generate file reference once
    $memo_reference = 'N/A';
    if (isset($nonTravel)) {
        $divisionName = $nonTravel->division->division_name ?? '';
        // Use PrintHelper for generating short code
        $shortCode = $divisionName ? PrintHelper::generateShortCodeFromDivision($divisionName) : 'DIV';
        $year = date('Y', strtotime($nonTravel->created_at ?? 'now'));
        $memoId = $nonTravel->id ?? 'N/A';
        $quarter = 'Q' . ceil(date('n', strtotime($nonTravel->created_at ?? 'now')) / 3);
        $memo_reference = "AU/CDC/{$shortCode}/NTM/{$quarter}/{$year}/{$memoId}";
    }

    // Get division category for workflow filtering
    $divisionCategory = null;
    if (isset($nonTravel->division) && isset($nonTravel->division->category)) {
        $divisionCategory = $nonTravel->division->category;
    }

    // Organize approvers by section using helper (same as activity memo)
    $organizedApprovers = PrintHelper::organizeApproversBySection(
        $nonTravel->id ?? null,
        'App\Models\NonTravelMemo',
        $nonTravel->division_id ?? null,
        $nonTravel->forward_workflow_id ?? null,
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

    // Calculate total rows needed for rowspan
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
                    <?php renderApproverInfo($approver, $approver['role'], $section, $nonTravel); ?>
                </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    <?php renderSignature($approver, $approver['order'], $matrix_approval_trails, $nonTravel); ?>
                </td>
                <?php if ($section === $sectionOrder[0] && $index === 0): // Only output the Date/FileNo cell once ?>
                    <td style="width: 28%; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
                        <div class="text-right">
                  <div style="margin-bottom: 20px;">
                                <strong class="section-label">Date:</strong>
                                <span style="font-weight: bold;"><?php echo isset($nonTravel->created_at) ? (is_object($nonTravel->created_at) ? $nonTravel->created_at->format('j F Y') : date('j F Y', strtotime($nonTravel->created_at))) : date('j F Y'); ?></span>
          </div>
                  <div>
                    <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($nonTravel->document_number ?? 'N/A'); ?></span>
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
                    <div class="approver-title"><?php echo htmlspecialchars($nonTravel->division->division_name ?? ''); ?></div>
                <?php endif; ?>
            </td>
            <td style="width: 30%; vertical-align: top; text-align: left;"></td>
            <?php if ($section === $sectionOrder[0]): // Only output the Date/FileNo cell once ?>
                <td style="width: 28%; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
                    <div class="text-right">
                <div style="margin-bottom: 20px;">
                                <strong class="section-label">Date:</strong>
                                <span style="font-weight: bold;"><?php echo isset($nonTravel->created_at) ? (is_object($nonTravel->created_at) ? $nonTravel->created_at->format('j F Y') : date('j F Y', strtotime($nonTravel->created_at))) : date('j F Y'); ?></span>
                </div>
                <div>
                                <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($nonTravel->document_number ?? 'N/A'); ?></span>
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
    <td style="width: 88%; text-align: left; vertical-align: top;" class="subject-text"><?php echo htmlspecialchars($nonTravel->activity_title ?? 'N/A'); ?></td>
  </tr>
 </table>

<!-- Background -->
 <table class="mb-15 mt-neg20">
  <tr>
    <td style="width: 12%; text-align: left; vertical-align: top;"><strong class="section-label">Background:</strong></td>
  </tr>
  <tr>
   <td class="justify-text" style="width: 100%; text-align: justify; vertical-align: top;"><p class="justify-text"><?=strip_tags($nonTravel->background);?></p></td>
  </tr>
 </table>
  
  <div>
    <div class="page-break"></div>
    <div class="section-label mb-15"><strong>Non-Travel Information</strong></div>
    
    <table class="form-table mb-15" role="table" aria-label="Non-Travel Information">
      <tr>
        <th scope="row">Division</th>
        <td><?php echo htmlspecialchars($nonTravel->division->division_name ?? 'N/A'); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Category</th>
        <td><?php echo htmlspecialchars($nonTravel->nonTravelMemoCategory->name ?? 'N/A'); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Date Required</th>
        <td><?php echo isset($nonTravel->memo_date) ? date('d/m/Y', strtotime($nonTravel->memo_date)) : 'N/A'; ?><span class="fill line"></span></td>
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
        <th scope="row">Fund Type</th>
        <td><?php echo htmlspecialchars($nonTravel->fundType->name ?? 'N/A'); ?><span class="fill line"></span></td>
      </tr>
    </table>
     
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
                           
                            <?php foreach($budgetBreakdown[$fundCode->id] ?? [] as $item): ?>
                                <?php
                                    $total = $item['unit_cost'] * $item['quantity'] * ($item['days'] ?? 1);
                                    $grandTotal+=$total;
                                ?>
                                <tr>
                                    <td><?php echo $count; ?></td>
                                    <td class="text-right"><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                                    <td class="text-right"><?php echo number_format($item['unit_cost'] ?? 0, 2); ?></td>
                                    <td class="text-right"><?php echo $item['quantity'] ?? 1; ?></td>
                                    <td class="text-right"><?php echo $item['days'] ?? 1; ?></td>
                                    <td class="text-right"><?php echo number_format($total, 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['notes'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php
                                $count++;
                            ?>
                            
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="bg-highlight text-right" colspan="5">Grand Total</th>
                                
                                <th class="bg-highlight text-right"><?php echo number_format($grandTotal ?? 0, 2); ?></th>
                                <th class="bg-highlight"></th>
                            </tr>
                        </tfoot>
                    </table>
                   
                </div>
     <div style="margin-bottom: 0; color: #006633; font-style: italic;"><strong>Request for Approval</strong></div>
     <div class="justify-text" style="padding: 10px;"><?php echo strip_tags($nonTravel->activity_request_remarks ?? 'N/A'); ?></div>
         
    <div class="page-break"></div>

    <?php if($fundCode->fundType->id == 1): ?>

    <!-- Right-side memo meta (stacked, borderless) -->
    <div class="topbar">
      <div class="meta" aria-label="Memo metadata">
        <span class="memo-id"><?php echo $nonTravel->document_number ?? 'N/A'; ?></span><br/>
        <span class="date">Date: <?php echo $nonTravel->created_at->format('j F Y'); ?></span>
      </div>
    </div>

    <!-- Main form table (borderless by default) -->
    <table class="form-table" role="table" aria-label="Payment details">
      <tr>
        <th scope="row">Payee/Staff<br/><span class="muted">(Vendors)</span></th>
        <td>
          <?php echo $nonTravel->staff->title.' '.$nonTravel->staff->fname.' '.$nonTravel->staff->lname.' '.$nonTravel->staff->oname; ?>
          <span class="fill line" aria-hidden="true"></span>
        </td>
      </tr>
      <tr>
        <th scope="row">Purpose of Payment</th>
        <td><?php echo htmlspecialchars($nonTravel->activity_title); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Department Name<br/><span class="muted">(Cost Center)</span></th>
        <td>Africa CDC - <?php echo $nonTravel->division->division_name ?? ''; ?><span class="fill line"></span></td>
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
    $financialApprovers = PrintHelper::getFinancialApprovers($approval_trails, $nonTravel->forward_workflow_id ?? 1);
    
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
        <td>$ <?=number_format($sfoApproval->amount_allocated ?? 0, 2);?></td>
        <td>Date: <?=$sfoApproval ? (is_object($sfoApproval->created_at) ? $sfoApproval->created_at->format('j F Y') : date('j F Y', strtotime($sfoApproval->created_at))) : 'N/A';?></td>
      </tr>
      <tr>
        <td></td>
        <td></td>
        <td></td>
        <td>
            
            <?php renderBudgetSignature($sfoApproval, $nonTravel); ?>
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
            <?php renderBudgetSignature($divisionHeadApproval, $nonTravel); ?>
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
            <?php renderBudgetSignature($directorFinanceApproval, $nonTravel); ?>
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
            <?php renderBudgetSignature($ddgApproval, $nonTravel); ?>
          </span>
        </td>
      </tr>
    </table>

    <?php 
      endif;  
  endforeach; 
    
    ?>
</body>
</html>
