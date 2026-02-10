<html>
<head>
<style>
    /* Color variables (mPDF doesn't support CSS variables, so we'll use direct values) */
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
        font-style: italic;
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
  <h1 class="document-title">Special Memorandum</h1>
  
  <?php
    // Use centralized PrintHelper
    use App\Helpers\PrintHelper;
    
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
    
    function generateVerificationHash($specialMemoId, $staffId, $approvalDateTime = null) {
      if (!$specialMemoId || !$staffId) return 'N/A';
      $dateTimeToUse = $approvalDateTime ? $approvalDateTime : date('Y-m-d H:i:s');
      return strtoupper(substr(md5(sha1($specialMemoId . $staffId . $dateTimeToUse)), 0, 16));
    }

    /**
     * Get the approval date for a given staff ID and/or approval order from the approval trails.
     * Returns a formatted date string if found, otherwise returns the current date/time.
     *
     * @param mixed $staffId
     * @param iterable $approvalTrails
     * @param mixed $order
     * @return string
     */
    function getApprovalDate($staffId, $approvalTrails, $order) {
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

    // Helper function to render approver info
    function renderApproverInfo($approver, $role, $section, $specialMemo) {
        $isOic = isset($approver['oic_staff']) && !empty($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        $staffName = '';
        if ($staff) {
            // Check if we have the new structure with 'name' field or old structure with fname/lname/oname
            if (isset($staff['name'])) {
                $staffName = trim($staff['name']);
            } elseif (isset($staff['fname']) || isset($staff['lname'])) {
                $staffName = trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '') . ' ' . ($staff['oname'] ?? ''));
            } elseif (is_object($staff) && (isset($staff->fname) || isset($staff->lname))) {
                $staffName = trim(($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? ''));
            }
        }
        $title = '';
        if ($staff) {
            if (isset($staff['title'])) {
                $title = $staff['title'];
            } elseif (is_object($staff) && isset($staff->title)) {
                $title = $staff->title;
            }
        }
        $name = $isOic ? $staffName . ' (OIC)' : trim(($title ? $title . ' ' : '') . $staffName);
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
            $divisionName = $specialMemo->division->division_name ?? '';
            if (!empty($divisionName)) {
                echo '<div class="approver-title">' . htmlspecialchars($divisionName) . '</div>';
            }
        }
    }

    // Helper function to render signature
    function renderSignature($approver, $order, $approval_trails, $specialMemo) {
        $isOic = isset($approver['oic_staff']) && !empty($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        
        // Get staff ID - handle both array and object structures
        $staffId = null;
        if ($staff) {
            if (isset($staff['staff_id'])) {
                $staffId = $staff['staff_id'];
            } elseif (isset($staff['id'])) {
                $staffId = $staff['id'];
            } elseif (is_object($staff) && isset($staff->id)) {
                $staffId = $staff->id;
            }
        }

        $approvalDate = getApprovalDate($staffId, $approval_trails, $order);

        echo '<div style="line-height: 1.2;">';
        
        // Get signature - handle both array and object structures
        $signature = null;
        $workEmail = null;
        if ($staff) {
            if (isset($staff['signature'])) {
                $signature = $staff['signature'];
            } elseif (is_object($staff) && isset($staff->signature)) {
                $signature = $staff->signature;
            }
            if (isset($staff['work_email'])) {
                $workEmail = $staff['work_email'];
            } elseif (is_object($staff) && isset($staff->work_email)) {
                $workEmail = $staff->work_email;
            }
        }
        
        if (!empty($signature)) {
            echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small> ';
            echo '<img class="signature-image" src="' . htmlspecialchars(user_session('base_url') . 'uploads/staff/signature/' . $signature) . '" alt="Signature">';
        } else {
            echo '<small style="color: #666; font-style:normal;">Signed By: ' . htmlspecialchars($workEmail ?? 'Email not available') . '</small>';
        }
        
        echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
        echo '<div class="signature-hash">Hash: ' . htmlspecialchars(generateVerificationHash($specialMemo->id, $staffId, $approvalDate)) . '</div>';
        echo '</div>';
    }

    // Helper function to get latest approval for a specific order
    function getLatestApprovalForOrder($approvalTrails, $order) {
        $approvals = $approvalTrails->where('approval_order', $order);
        return $approvals->sortByDesc('created_at')->first();
    }

    // Helper function to render budget signature with OIC support
    function renderBudgetSignature($approval, $specialMemo, $label = '') {
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
        
        $hash = generateVerificationHash($specialMemo->id, $isOic ? $approval->oic_staff_id : $approval->staff_id, $approval->created_at);
        echo '<div class="signature-hash">Hash: ' . htmlspecialchars($hash) . '</div>';
         
        // Add OIC watermark if applicable
        if ($isOic) {
            echo '<div style="position: relative; display: inline-block; margin-top: 5px;">';
            echo '<span style="position: absolute; top: -5px; right: -10px; background: #ff6b6b; color: white; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; transform: rotate(15deg);">OIC</span>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    // Helper function to render budget approver info with OIC support
    function renderBudgetApproverInfo($approval, $label = '') {
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

    // Generate file reference once
    $special_memo_reference = 'N/A';
    if (isset($specialMemo)) {
        $divisionName = $specialMemo->division->division_name ?? '';
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
        $year = date('Y', strtotime($specialMemo->created_at ?? 'now'));
        $specialMemoId = $specialMemo->id ?? 'N/A';
        $special_memo_reference = "AU/CDC/{$shortCode}/SM/{$year}/{$specialMemoId}";
    }

      // Get division category for workflow filtering
      $divisionCategory = null;
      if (isset($specialMemo->division) && isset($specialMemo->division->category)) {
          $divisionCategory = $specialMemo->division->category;
      }

      // Organize approvers by section using helper (same as activity memo)
      $organizedApprovers = PrintHelper::organizeApproversBySection(
          $specialMemo->id ?? null,
          'App\Models\SpecialMemo',
          $specialMemo->division_id ?? null,
          $specialMemo->forward_workflow_id ?? null,
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
                    <?php renderApproverInfo($approver, $approver['role'], $section, $specialMemo); ?>
                </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    <?php renderSignature($approver, $approver['order'], $approval_trails, $specialMemo); ?>
                </td>
                <?php if ($section === $sectionOrder[0] && $index === 0): // Only output the Date/FileNo cell once ?>
                    <td style="width: 28%; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
                        <div class="text-right">
                  <div style="margin-bottom: 20px;">
                                <strong class="section-label">Date:</strong>
                                <span style="font-weight: bold;"><?php echo isset($specialMemo->created_at) ? (is_object($specialMemo->created_at) ? $specialMemo->created_at->format('j F Y') : date('j F Y', strtotime($specialMemo->created_at))) : date('j F Y'); ?></span>
          </div>
                  <div>
                    <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($specialMemo->document_number ?? 'N/A'); ?></span>
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
                        <div class="approver-title"><?php echo htmlspecialchars($specialMemo->division->division_name ?? ''); ?></div>
                    <?php endif; ?>
          </td>
                <td style="width: 30%; vertical-align: top; text-align: left;"></td>
                <?php if ($section === $sectionOrder[0]): // Only output the Date/FileNo cell once ?>
                    <td style="width: 28%; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
                        <div class="text-right">
                <div style="margin-bottom: 20px;">
                                <strong class="section-label">Date:</strong>
                                <span style="font-weight: bold;"><?php echo isset($specialMemo->created_at) ? (is_object($specialMemo->created_at) ? $specialMemo->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($specialMemo->created_at))) : date('j F Y H:i'); ?></span>
                </div>
                <div>
                                <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($specialMemo->document_number ?? 'N/A'); ?></span>
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
    <td style="width: 88%; text-align: left; vertical-align: top;" class="subject-text"><?php echo htmlspecialchars($specialMemo->activity_title ?? 'N/A'); ?></td>
  </tr>
 </table>

<!-- Background -->
 <table class="mb-15 mt-neg20">
  <tr>
    <td style="width: 12%; text-align: left; vertical-align: top;"><strong class="section-label">Background:</strong></td>
  </tr>
  <tr>
   <td class="justify-text" style="width: 100%; text-align: justify; vertical-align: top;"><p class="justify-text"><?=strip_tags($specialMemo->background);?></p></td>
  </tr>
 </table>

 <!-- Background -->
 <div class="page-break"></div>
 <table class="mb-15 mt-neg20">
  <tr>
    <td style="width: 12%; text-align: left; vertical-align: top;"><strong class="section-label">Justification:</strong></td>
  </tr>
  <tr>
   <td class="justify-text" style="width: 100%; text-align: justify; vertical-align: top;"><p class="justify-text"><?=strip_tags($specialMemo->justification);?></p></td>
  </tr>
 </table>
  
  <div>
    <div class="page-break"></div>
    <div class="section-label mb-15"><strong>Special Memo Information</strong></div>
  
    <table class="form-table mb-15" role="table" aria-label="Special Memo Information">
    <tr>
        <th scope="row">Division</th>
        <td><?php echo htmlspecialchars($specialMemo->division->division_name ?? 'N/A'); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Request Type</th>
        <td><?php echo htmlspecialchars($specialMemo->requestType->name ?? 'N/A'); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Activity Start Date</th>
        <td><?php echo isset($specialMemo->date_from) ? date('d/m/Y', strtotime($specialMemo->date_from)) : 'N/A'; ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Activity End Date</th>
        <td><?php echo isset($specialMemo->date_to) ? date('d/m/Y', strtotime($specialMemo->date_to)) : 'N/A'; ?><span class="fill line"></span></td>
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
        <th scope="row">Total Participants</th>
        <td><?php echo htmlspecialchars($specialMemo->total_participants ?? 'N/A'); ?><span class="fill line"></span></td>
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
                                <?php if (empty($internalParticipants)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">No participants listed</td></tr>
                                <?php else: ?>
                                    <?php foreach($internalParticipants as $entry): ?>
                                        <?php if ($entry['staff']): ?>
                                            <tr><td><?php echo $count; ?></td>
                                                    <td><?php 
                                                        if (is_array($entry['staff'])) {
                                                            echo htmlspecialchars(trim(($entry['staff']['fname'] ?? '') . ' ' . ($entry['staff']['lname'] ?? '') . ' ' . ($entry['staff']['oname'] ?? '')));
                                                        } else {
                                                            echo htmlspecialchars(trim(($entry['staff']->fname ?? '') . ' ' . ($entry['staff']->lname ?? '') . ' ' . ($entry['staff']->oname ?? '')));
                                                        }
                                                    ?></td>
                                                     <td><?php echo htmlspecialchars(is_array($entry['staff']) ? ($entry['staff']['division_name'] ?? 'N/A') : ($entry['staff']->division_name ?? 'N/A')); ?></td>
                                                    <td><?php echo htmlspecialchars(is_array($entry['staff']) ? ($entry['staff']['status'] ?? 'N/A') : ($entry['staff']->status ?? 'N/A')); ?></td>
                                                    <td><?php echo htmlspecialchars(is_array($entry['staff']) ? ($entry['staff']['job_name'] ?? 'N/A') : ($entry['staff']->job_name ?? 'N/A')); ?></td>
                                                  <td><?php echo htmlspecialchars(is_array($entry['staff']) ? ($entry['staff']['duty_station_name'] ?? 'N/A') : ($entry['staff']->duty_station_name ?? 'N/A')); ?></td>
                                                <td><?php echo htmlspecialchars($entry['participant_days'] ?? '-'); ?></td>
                                            </tr>
                                            <?php
                                                $count++;
                                            ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
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
                                    $unitCost = floatval($item['unit_cost'] ?? 0);
                                    $units = floatval($item['units'] ?? 0);
                                    $days = floatval($item['days'] ?? 1);
                                    
                                    // Use days when greater than 1, otherwise just unit_cost * units
                                    if ($days > 1) {
                                        $total = $unitCost * $units * $days;
                                    } else {
                                        $total = $unitCost * $units;
                                    }
                                    $grandTotal+=$total;
                                ?>
                                <tr>
                                    <td><?php echo $count; ?></td>
                                    <td class="text-right"><?php echo htmlspecialchars($item['cost'] ?? 'N/A'); ?></td>
                                    <td class="text-right"><?php echo number_format($item['unit_cost'] ?? 0, 2); ?></td>
                                    <td class="text-right"><?php echo $item['units'] ?? 0; ?></td>
                                    <td class="text-right"><?php echo $item['days'] ?? 0; ?></td>
                                    <td class="text-right"><?php echo number_format($total, 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
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
     <div class="justify-text" style="padding: 10px;"><?php echo strip_tags($specialMemo->activity_request_remarks ?? 'N/A'); ?></div>

    <?php if($fundCode->fundType->id == 1): ?>
    <div class="page-break"></div>

    <!-- Right-side memo meta (stacked, borderless) -->
    <div class="topbar">
      <div class="meta" aria-label="Memo metadata">
        <span class="memo-id"><?php echo $specialMemo->document_number ?? 'N/A'; ?></span><br/>
        <span class="date">Date: <?php echo $specialMemo->created_at->format('j F Y'); ?></span>
      </div>
    </div>

    <!-- Main form table (borderless by default) -->
    <table class="form-table" role="table" aria-label="Payment details">
      <tr>
        <th scope="row">Payee/Staff<br/><span class="muted">(Vendors)</span></th>
        <td>
        <?php echo $specialMemo->staff->title.' '.$specialMemo->staff->fname.' '.$specialMemo->staff->lname.' '.$specialMemo->staff->oname; ?>
          <span class="fill line" aria-hidden="true"></span>
        </td>
      </tr>
      <tr>
        <th scope="row">Purpose of Payment</th>
        <td><?php echo htmlspecialchars($specialMemo->activity_title); ?><span class="fill line"></span></td>
      </tr>
      <tr>
        <th scope="row">Department Name<br/><span class="muted">(Cost Center)</span></th>
        <td>Africa CDC - <?php echo $specialMemo->division->division_name ?? ''; ?><span class="fill line"></span></td>
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
    $financialApprovers = PrintHelper::getFinancialApprovers($approval_trails, $specialMemo->forward_workflow_id ?? 1);
    
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
           
            <?php renderBudgetSignature($sfoApproval, $specialMemo); ?>
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
            <?php renderBudgetSignature($divisionHeadApproval, $specialMemo); ?>
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
            <?php renderBudgetSignature($directorFinanceApproval, $specialMemo); ?>
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
            <?php renderBudgetSignature($ddgApproval, $specialMemo); ?>
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
