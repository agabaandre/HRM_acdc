<html>
<head>
  <style>
    body { 
      font-family: Arial, sans-serif; 
      font-size: 13px; 
      margin: 20px; 
      line-height: 1.4;
    }
    .header { 
      text-align: center; 
      border-bottom: 2px solid #000000; 
      padding-bottom: 15px; 
      margin-bottom: 20px; 
    }
    .header img { 
      height: 60px; 
      margin-bottom: 10px; 
    }
    .tagline { 
      font-size: 14px; 
      color: #911C39; 
      font-weight: bold; 
    }
    .doc-title { 
      font-size: 20px; 
      font-weight: bold; 
      text-align: center; 
      margin-top: -20px; 
      margin-bottom: 10px;
      color: #000000; 
    }
    .section-title { 
      font-size: 14px; 
      font-weight: bold; 
      margin-top: 20px; 
       
      padding-bottom: 5px;
      color:rgb(17, 19, 18); /* AU Green */
    }
    table { 
      width: 100%; 
      border-collapse: collapse; 
      margin-bottom: 15px; 
    }
    td, th { 
      border: 1px solid #ccc; 
      padding: 8px; 
      text-align: left; 
      vertical-align: top;
    }
    .no-border td { 
      border: none; 
    }
    .meta-table td {
      border: none;
      padding: 5px 8px;
    }
    .meta-label {
      font-weight: bold;
      color: #006633; /* AU Green */
      width: 80px;
    }
    .signature-section {
      margin-top: 30px;
    }
    .signature-row {
      margin: 15px 0;
    }
    .signature-label {
      font-weight: bold;
      margin-bottom: 5px;
      color: #006633; /* AU Green */
    }
    .signature-line {
      border-bottom: 1px solid #333;
      height: 20px;
      margin-top: 5px;
    }
    .signature-info {
      font-size: 10px;
      color: #666;
      margin-top: 3px;
    }
  </style>
</head>
<body>
  <?php  //dd($activity); ?>
  <!-- Header -->
  <div style="width: 100%; text-align: center; padding-bottom: 5px;">
    <div style="width: 100%; padding-bottom: 5px;">
      <div style="width: 100%; padding: 10px 0;">
        <!-- Top Row: Logo and Tagline -->
        <div style="display:flex; justify-content: space-between; align-items: center;">
          <!-- Left: Logo -->
          <div style="width: 60%; text-align: left; float:left;">
            <img src="<?= asset('assets/images/logo.png') ?>" alt="Africa CDC Logo" style="height: 80px;">
          </div>
          <!-- Right: Tagline -->
          <div style="text-align: right; width: 35%; float:right; margin-top:10px;">
            <span style="font-size: 14px; color: #911C39;">Safeguarding Africa's Health</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Document Title -->
  <h1 class="doc-title">Interoffice Memorandum</h1>
  
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
    
    function generateVerificationHash($activityId, $staffId) {
      if (!$activityId || !$staffId) return 'N/A';
      return strtoupper(substr(md5(sha1($activityId . $staffId . date('Y-m-d'))), 0, 16));
    }

    
    // Helper function to get approval date from matrix approval trail
    function getApprovalDate($staffId, $matrixApprovalTrails) {
      foreach ($matrixApprovalTrails as $trail) {
        if (isset($trail['staff']['id']) && $trail['staff']['id'] == $staffId) {
          // Try different possible date fields from the approval trail
          $approvalDate = $trail['approval_date'] ?? $trail['created_at'] ?? $trail['updated_at'] ?? $trail['date'] ?? null;
          if ($approvalDate) {
            return date('d/m/Y H:i', strtotime($approvalDate));
          }
          // Debug: Show available fields for troubleshooting
          // echo "<!-- Debug: Trail fields for staff $staffId: " . implode(', ', array_keys($trail)) . " -->";
        }
      }
      return date('d/m/Y H:i');
    }
  ?>
  
  <style>
    .memo-table td {
      border: none !important;
      padding: 5px !important;
    }
    .memo-table {
      border: none !important;
      border-collapse: collapse !important;
    }
    .signature-info {
      margin-top: 5px;
    }
    .signature-date {
      color: #666;
      font-size: 10px;
    }
    .signature-hash {
      color: #999;
      font-size: 9px;
    }
  </style>

  <table class="memo-table" style="width: 100%; border-collapse: collapse;">
    <?php
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
      $currentRow = 0;
      $dateFileRowspan = $totalRows;
    ?>
    <?php foreach ($sectionOrder as $section): ?>
      <?php if (isset($organized_workflow_steps[$section]) && $organized_workflow_steps[$section]->count() > 0): ?>
        <?php foreach ($organized_workflow_steps[$section] as $index => $step): ?>
          <tr>
            <td style="width: 12%; border: none; padding: 8px; vertical-align: top;">
              <strong style="color: #006633; font-style: italic;"><?php echo $sectionLabels[$section] ?? strtoupper($section) . ':'; ?></strong>
            </td>

                        <td style="width: 30%; border: none; padding: 4px 6px; vertical-align: top; text-align: left;">
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff'])): ?>
                    <div style="font-size: 14px; font-weight: bold; line-height: 1.1;">
                      <?php echo htmlspecialchars(trim($approver['staff']['title'] . ' ' . $approver['staff']['name'])); ?>
                    </div>
                    <?php if (isset($approver['staff']['job_title']) && !empty($approver['staff']['job_title'])): ?>
                      <div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></div>
                    <?php elseif (isset($approver['staff']['title']) && !empty($approver['staff']['title'])): ?>
                      <div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;"><?php echo htmlspecialchars($approver['staff']['title']); ?></div>
                    <?php else: ?>
                      <div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;"><?php echo htmlspecialchars($step['role']); ?></div>
                    <?php endif; ?>
                    <?php
                      // If this is the FROM section, display the division name under the title/job title
                      if ($section === 'from') {
                        $divisionName = $matrix->division->division_name ?? '';
                        if (!empty($divisionName)) {
                          echo '<div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;">' . htmlspecialchars($divisionName) . '</div>';
                        }
                      }
                    ?>
                  <?php elseif (isset($approver['oic_staff'])): ?>
                    <div style="font-size: 14px; font-weight: bold; line-height: 1.1;">
                      <?php echo htmlspecialchars($approver['oic_staff']['name'] . ' (OIC)'); ?>
                    </div>
                    <?php if (isset($approver['oic_staff']['job_title']) && !empty($approver['oic_staff']['job_title'])): ?>
                      <div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></div>
                    <?php elseif (isset($approver['oic_staff']['title']) && !empty($approver['oic_staff']['title'])): ?>
                      <div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;"><?php echo htmlspecialchars($approver['oic_staff']['title']); ?></div>
                    <?php else: ?>
                      <div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;"><?php echo htmlspecialchars($step['role']); ?></div>
                    <?php endif; ?>
                    <?php
                      // If this is the FROM section, display the division name under the title/job title for OIC as well
                      if ($section === 'from') {
                        $divisionName = $matrix->division->division_name ?? '';
                        if (!empty($divisionName)) {
                          echo '<div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;">' . htmlspecialchars($divisionName) . '</div>';
                        }
                      }
                    ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                <div style="font-size: 14px; font-weight: bold; line-height: 1.1;">
                  <?php echo htmlspecialchars($step['role']); ?>
                </div>
                <?php
                  // If this is the FROM section, display the division name under the role
                  if ($section === 'from') {
                    $divisionName = $matrix->division->division_name ?? '';
                    if (!empty($divisionName)) {
                      echo '<div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;">' . htmlspecialchars($divisionName) . '</div>';
                    }
                  }
                ?>
        <?php endif; ?>
      </td>

?>

            <td style="width: 30%; border: none; padding: 4px 6px; vertical-align: top; text-align: left;">
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff']) && isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                                          <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approver['staff']['signature']; ?>" 
                          alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                     <br><small style="color: #666; font-size: 10px;"><?php echo getApprovalDate($approver['staff']['id'], $matrix_approval_trails); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                                          <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approver['oic_staff']['signature']; ?>" 
                          alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                     <br><small style="color: #666; font-size: 10px;"><?php echo getApprovalDate($approver['oic_staff']['id'], $matrix_approval_trails); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php else: ?>
                    <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
                     <br><small style="color: #666; font-size: 10px;"><?php echo getApprovalDate(getStaffId($approver), $matrix_approval_trails); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </td>

            <?php if ($currentRow === 0): ?>
              <td style="width: 28%; border: none; padding: 8px; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
                <div style="text-align: right; padding-left: 15px;">
                  <div style="margin-bottom: 20px;">
                    <strong style="color: #006633; font-style: italic;">Date:</strong>
                    <span style="font-size: 12px; font-weight: bold;"><?php echo isset($matrix->created_at) ? date('d/m/Y', strtotime($matrix->created_at)) : date('d/m/Y'); ?></span>
          </div>
                  <div>
                    <br><br>
                    <strong style="color: #006633; font-style: italic;">File No:</strong><br>
                    <span style="font-size: 10px; word-break: break-all; font-weight: bold;">
            <?php
              if (isset($activity)) {
                $divisionName = $matrix->division->division_name ?? '';
                if (!function_exists('generateShortCodeFromDivision')) {
                  function generateShortCodeFromDivision(string $name): string
                  {
                      $ignore = ['of', 'and', 'for', 'the', 'in'];
                      $words = preg_split('/\s+/', strtolower($name));
                      $initials = array_map(function ($word) use ($ignore) {
                          return in_array($word, $ignore) ? '' : strtoupper($word[0]);
                      }, $words);
                      return implode('', array_filter($initials));
                  }
                }
                $shortCode = $divisionName ? generateShortCodeFromDivision($divisionName) : 'DIV';
                        $year = date('Y', strtotime($matrix->created_at ?? 'now'));
                $activityId = $activity->id ?? 'N/A';
                echo htmlspecialchars("AU/CDC/{$shortCode}/IM/{$matrix->quarter}/{$year}/{$activityId}");
              } else {
                echo 'N/A';
              }
            ?>
                    </span>
          </div>
        </div>
      </td>
            <?php endif; ?>
    </tr>
          <?php $currentRow++; ?>
          <?php endforeach; ?>
      <?php else: ?>
        <!-- Empty section placeholder -->
        <tr>
          <td style="width: 12%; border: none; padding: 8px; vertical-align: top;">
            <strong style="color: #006633; font-style: italic;"><?php echo $sectionLabels[$section] ?? ucfirst($section) . ':'; ?></strong>
          </td>
          <td style="width: 30%; border: none; padding: 4px 6px; vertical-align: top; text-align: left;">
            <!-- No approvers for this section -->
            <?php
              // If this is the FROM section, display the division name under the empty cell
              if ($section === 'from') {
                $divisionName = $matrix->division->division_name ?? '';
                if (!empty($divisionName)) {
                  echo '<div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;">' . htmlspecialchars($divisionName) . '</div>';
                }
              }
            ?>
          </td>
          <td style="width: 30%; border: none; padding: 4px 6px; vertical-align: top; text-align: center;">
            <!-- No signatures for this section -->
      </td>
          <?php if ($currentRow === 0): ?>
            <td style="width: 28%; border: none; padding: 8px; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
              <div style="text-align: right; padding-left: 15px;">
                <div style="margin-bottom: 20px;">
                  <strong style="color: #006633;">DATE:</strong><br>
                  <span style="font-size: 12px;"><?php echo isset($matrix->created_at) ? date('d/m/Y', strtotime($matrix->created_at)) : date('d/m/Y'); ?></span>
                </div>
                <div>
                  <strong style="color: #006633;">FILE NO:</strong><br>
                  <span style="font-size: 10px; word-break: break-all;">
        <?php
                    if (isset($activity)) {
                      $divisionName = $matrix->division->division_name ?? '';
                      if (!function_exists('generateShortCodeFromDivision')) {
                        function generateShortCodeFromDivision(string $name): string
                        {
                            $ignore = ['of', 'and', 'for', 'the', 'in'];
                            $words = preg_split('/\s+/', strtolower($name));
                            $initials = array_map(function ($word) use ($ignore) {
                                return in_array($word, $ignore) ? '' : strtoupper($word[0]);
                            }, $words);
                            return implode('', array_filter($initials));
                        }
                      }
                      $shortCode = $divisionName ? generateShortCodeFromDivision($divisionName) : 'DIV';
                      $year = date('Y', strtotime($matrix->created_at ?? 'now'));
                      $activityId = $activity->id ?? 'N/A';
                      echo htmlspecialchars("AU/CDC/{$shortCode}/IM/{$matrix->quarter}/{$year}/{$activityId}");
                    } else {
                      echo 'N/A';
                    }
                  ?>
                  </span>
                </div>
              </div>
            </td>
          <?php endif; ?>
    </tr>
        <?php $currentRow++; ?>
      <?php endif; ?>
    <?php endforeach; ?>
  </table>

  <!-- Subject -->
 <table class="no-border">
  <tr>
    <td style="width: 12%;"><strong style="color: #006633; font-style: italic;">Subject:</strong></td>
    <td style="width: 88%; text-align: left; text-decoration: underline; font-weight: bold;"><?php echo htmlspecialchars($activity->activity_title ?? 'N/A'); ?></td>
  </tr>
 </table>

<!-- Background -->
 <table class="no-border" style="margin-top: -5px;">
  <tr>
   <td style="width: 100%; text-align: justify;"><?=$activity->background;?></td>
  </tr>
 </table>
  
  <div>
    
    <div class="section-title">Activity Information</div>
    <table class="no-border">
    
      <tr>
        <td><strong>Start Date:</strong></td>
        <td><?php echo isset($activity->start_date) ? date('d/m/Y', strtotime($activity->start_date)) : 'N/A'; ?></td>
      </tr>
      <tr>
        <td><strong>End Date:</strong></td>
        <td><?php echo isset($activity->end_date) ? date('d/m/Y', strtotime($activity->end_date)) : 'N/A'; ?></td>
      </tr>
      <tr>
        <td><strong>Location:</strong></td>
        <td>
          <?php
            $locationNames = [];
            if (isset($locations) && (is_array($locations) || $locations instanceof \Illuminate\Support\Collection)) {
              foreach ($locations as $loc) {
                if (is_object($loc) && isset($loc->location_name)) {
                  $locationNames[] = $loc->location_name;
                } elseif (is_array($loc) && isset($loc['location_name'])) {
                  $locationNames[] = $loc['location_name'];
                }
              }
            }
            if (count($locationNames) > 0) {
              echo htmlspecialchars(implode(', ', $locationNames));
            } else {
              echo 'N/A';
            }
          ?>
        </td>
      </tr>
      <tr>
        <td><strong>Division:</strong></td>
        <td><?php echo htmlspecialchars($matrix->division->division_name ?? 'N/A'); ?></td>
      </tr>
      <tr>
        <td><strong>Budget Code:</strong></td>
        <td><?php echo htmlspecialchars($matrix->fund_code->code ?? 'N/A'); ?></td>
      </tr>
    </table>

    <!-- Participants Table -->
    <div class="section-title">Participants</div>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
      <tr style="background-color: #f5f5f5;">
        <th style="border: 1px solid #ccc; padding: 8px; text-align: left; font-weight: bold;">Name</th>
        <th style="border: 1px solid #ccc; padding: 8px; text-align: left; font-weight: bold;">Division</th>
        <th style="border: 1px solid #ccc; padding: 8px; text-align: center; font-weight: bold;">Days</th>
        <th style="border: 1px solid #ccc; padding: 8px; text-align: left; font-weight: bold;">Role</th>
      </tr>
      
      <?php if (isset($internal_participants) && $internal_participants->count() > 0): ?>
      <?php foreach ($internal_participants as $participant): ?>
      <tr>
          <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top;">
            <?php echo htmlspecialchars($participant->staff->name ?? 'N/A'); ?>
            <?php if (isset($participant->staff->work_email) && !empty($participant->staff->work_email)): ?>
              <br><small style="color: #666; font-size: 10px;"><?php echo htmlspecialchars($participant->staff->work_email); ?></small>
            <?php endif; ?>
          </td>
          <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top;">
            <?php echo htmlspecialchars($participant->staff->division->division_name ?? 'N/A'); ?>
          </td>
          <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top; text-align: center;">
            <?php echo htmlspecialchars($participant->no_of_days ?? 'N/A'); ?>
          </td>
          <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top;">
            <?php 
              // Check if this participant is the focal person
              if (isset($activity->focal_person_id) && $participant->staff_id == $activity->focal_person_id) {
                echo '<strong style="color: #006633;">Focal Person</strong>';
              } else {
                echo 'Participant';
              }
            ?>
          </td>
      </tr>
      <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="4" style="border: 1px solid #ccc; padding: 8px; text-align: center; font-style: italic; color: #666;">
            No participants assigned to this activity
          </td>
        </tr>
      <?php endif; ?>
    </table>

    <?php if (isset($matrix->budget_items) && $matrix->budget_items->count() > 0): ?>
    <div class="section-title">Budget Details</div>
    <table>
      <tr>
        <th>Item</th>
        <th>Cost</th>
        <th>Units</th>
        <th>Total</th>
      </tr>
      <?php foreach ($matrix->budget_items as $item): ?>
      <tr>
        <td><?php echo htmlspecialchars($item->cost_item->name ?? 'N/A'); ?></td>
        <td><?php echo htmlspecialchars($item->unit_cost ?? 'N/A'); ?></td>
        <td><?php echo htmlspecialchars($item->no_of_units ?? 'N/A'); ?></td>
        <td><?php echo htmlspecialchars($item->total_cost ?? 'N/A'); ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <div style="page-break-before: always;"></div>
   
  <!-- Signature Section -->
  <div class="section-title">Signatures</div>
  <table style="width: 100%; border-collapse: collapse;">
    <tr>
      <th style="width: 30%; border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;">Position</th>
      <th style="width: 40%; border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;">Name & Title</th>
      <th style="width: 30%; border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;">Signature</th>
    </tr>
    
    <!-- First Approver -->
    <?php if(isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
      <?php 
      $firstStep = $workflow_info['workflow_steps']->first();
      if(isset($firstStep['approvers']) && count($firstStep['approvers']) > 0):
        $approver = $firstStep['approvers']->first();
        if(isset($approver['staff'])): ?>
          <tr>
            <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top;"><strong><?php echo htmlspecialchars($firstStep['role'] ?? 'Endorsed by'); ?></strong></td>
            <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top;">
              <?php echo htmlspecialchars($approver['staff']['name']); ?>
              <?php if (isset($approver['staff']['job_title'])): ?>
                <br><small style="color: #666;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></small>
              <?php elseif (isset($approver['staff']['title'])): ?>
                <br><small style="color: #666;"><?php echo htmlspecialchars($approver['staff']['title']); ?></small>
              <?php endif; ?>
            </td>
            <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top; text-align: center;">
              <?php if (isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approver['staff']['signature']; ?>" 
                     alt="Signature" style="height: 40px; max-width: 120px; object-fit: contain; filter: contrast(1.2);">
              <?php else: ?>
                <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
              <?php endif; ?>
            </td>
          </tr>
        <?php elseif(isset($approver['oic_staff'])): ?>
          <tr>
            <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top;"><strong><?php echo htmlspecialchars($firstStep['role'] ?? 'Endorsed by'); ?></strong></td>
            <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top;">
              <?php echo htmlspecialchars($approver['oic_staff']['name']); ?> (OIC)
              <?php if (isset($approver['oic_staff']['job_title'])): ?>
                <br><small style="color: #666;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></small>
              <?php elseif (isset($approver['oic_staff']['title'])): ?>
                <br><small style="color: #666;"><?php echo htmlspecialchars($approver['oic_staff']['title']); ?></small>
              <?php endif; ?>
            </td>
            <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top; text-align: center;">
              <?php if (isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approver['oic_staff']['signature']; ?>" 
                     alt="Signature" style="height: 40px; max-width: 120px; object-fit: contain; filter: contrast(1.2);">
              <?php else: ?>
                <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Division Head -->
    <tr>
      <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top;"><strong>Division Head</strong></td>
      <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top;">
        <?php 
          // Get HOD from matrix division
          if (isset($matrix->division->divisionHead) && $matrix->division->divisionHead): 
            echo htmlspecialchars($matrix->division->divisionHead->name ?? 'Head of Division');
            if (isset($matrix->division->divisionHead->job_title) && !empty($matrix->division->divisionHead->job_title)):
              echo '<br><small style="color: #666;">' . htmlspecialchars($matrix->division->divisionHead->job_title) . '</small>';
            elseif (isset($matrix->division->divisionHead->title) && !empty($matrix->division->divisionHead->title)):
              echo '<br><small style="color: #666;">' . htmlspecialchars($matrix->division->divisionHead->title) . '</small>';
            else:
              echo '<br><small style="color: #666;">Head of Division, Africa CDC</small>';
            endif;
          else:
            echo 'Head of Division, Africa CDC';
          endif;
        ?>
      </td>
      <td style="border: 1px solid #ccc; padding: 8px; vertical-align: top; text-align: center;"><?php echo date('M j, Y | H:i'); ?> EAST</td>
    </tr>
  </table>
</body>
</html>