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
 
  <!-- Service Request Document -->
  <!-- Document Title -->
  <h1 class="document-title">Service Request</h1>
  
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
                                <strong class="section-label">Request No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($serviceRequest->request_number ?? 'N/A'); ?></span>
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
                                <strong class="section-label">Request No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold;"><?php echo htmlspecialchars($serviceRequest->request_number ?? 'N/A'); ?></span>
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
    <td style="width: 88%; text-align: left; vertical-align: top;" class="subject-text"><?php echo htmlspecialchars($serviceRequest->service_title ?? 'N/A'); ?></td>
  </tr>
 </table>

<!-- Service Request Details -->
<div class="section-label mb-15"><strong>Service Request Information</strong></div>

<table class="form-table mb-15" role="table" aria-label="Service Request Information">
  <tr>
    <th scope="row">Division</th>
    <td><?php echo htmlspecialchars($serviceRequest->division->division_name ?? 'N/A'); ?><span class="fill line"></span></td>
  </tr>
  <tr>
    <th scope="row">Service Type</th>
    <td><?php echo htmlspecialchars($serviceRequest->service_type ?? 'N/A'); ?><span class="fill line"></span></td>
  </tr>
  <tr>
    <th scope="row">Priority</th>
    <td><?php echo htmlspecialchars($serviceRequest->priority ?? 'N/A'); ?><span class="fill line"></span></td>
  </tr>
  <tr>
    <th scope="row">Required By Date</th>
    <td><?php echo isset($serviceRequest->required_by_date) ? date('d/m/Y', strtotime($serviceRequest->required_by_date)) : 'N/A'; ?><span class="fill line"></span></td>
  </tr>
  <tr>
    <th scope="row">Location</th>
    <td><?php echo htmlspecialchars($serviceRequest->location ?? 'N/A'); ?><span class="fill line"></span></td>
  </tr>
  <tr>
    <th scope="row">Estimated Cost</th>
    <td><?php echo number_format($serviceRequest->estimated_cost ?? 0, 2); ?><span class="fill line"></span></td>
  </tr>
</table>

<!-- Description -->
<table class="mb-15 mt-neg20">
  <tr>
    <td style="width: 12%; text-align: left; vertical-align: top;"><strong class="section-label">Description:</strong></td>
  </tr>
  <tr>
   <td class="justify-text" style="width: 100%; text-align: justify; vertical-align: top;"><div class="justify-text"><?php echo htmlspecialchars($serviceRequest->description ?? 'N/A'); ?></div></td>
  </tr>
</table>

<!-- Justification -->
<table class="mb-15 mt-neg20">
  <tr>
    <td style="width: 12%; text-align: left; vertical-align: top;"><strong class="section-label">Justification:</strong></td>
  </tr>
  <tr>
   <td class="justify-text" style="width: 100%; text-align: justify; vertical-align: top;"><div class="justify-text"><?php echo htmlspecialchars($serviceRequest->justification ?? 'N/A'); ?></div></td>
  </tr>
</table>

<div class="page-break"></div>

<?php if ($sourcePdfHtml): ?>
  <!-- Include the source memo HTML here -->
  <div class="section-label mb-15"><strong>Source Memorandum</strong></div>
  
  <!-- The source memo HTML will be embedded here -->
  <?php echo $sourcePdfHtml; ?>
<?php endif; ?>

</body>
</html>