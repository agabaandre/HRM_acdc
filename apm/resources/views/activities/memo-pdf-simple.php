<html>
<head>
</head>
<body style="font-family: Arial, sans-serif; font-size: 12px; margin: 40px; line-height: 1.4;">
 
  <!-- Document Title -->
  <h1 style="font-size: 18px; font-weight: bold; text-align: center; margin-top: -20px; margin-bottom: 15px; color: #000000; letter-spacing: 0.5px;">Interoffice Memorandum</h1>
  
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

    
    // Helper function to get approval date from matrix approval trail
    /**
     * Get the approval date for a given staff ID and/or approval order from the matrix approval trails.
     * Returns a formatted date string if found, otherwise returns an empty string.
     *
     * @param mixed $staffId
     * @param iterable $matrixApprovalTrails
     * @param mixed $order
     * @return string
     */
    function getApprovalDate($staffId, $matrixApprovalTrails, $order) {

      // Return the formatted approval date for the given staff and order, or empty string if not found
      $approval = $matrixApprovalTrails
        ->where('approval_order', $order)
        ->where('staff_id', $staffId)
        ->sortByDesc('created_at')
        ->first();
     $date =   $approval->created_at->format('d/m/Y H:i');
      //dd($date);

      return $date;
    }
  ?>
  
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
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
        <?php foreach ($organized_workflow_steps[$section] as $index => $step): 
          $order = $organized_workflow_steps[$section][$index]['order'];
          ?>
          <tr>
            <td style="width: 12%; border: none; padding: 5px; vertical-align: top;">
              <strong style="color: #006633; font-weight: bold; font-size: 13px;"><?php echo $sectionLabels[$section] ?? strtoupper($section) . ':'; ?></strong>
            </td>

              <td style="width: 30%; border: none; padding: 5px; vertical-align: top; text-align: left;">
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff'])): 
                    
                    ?>
                    <div style="font-size: 13px; font-weight: bold; line-height: 1.2; margin-bottom: 2px;">
                      <?php echo htmlspecialchars(trim($approver['staff']['title'] . ' ' . $approver['staff']['name'])); ?>
                    </div>
                    <?php if (isset($approver['staff']['job_title']) && !empty($approver['staff']['job_title'])): ?>
                      <div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></div>
                    <?php elseif (isset($approver['staff']['title']) && !empty($approver['staff']['title'])): ?>
                      <div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;"><?php echo htmlspecialchars($approver['staff']['title']); ?></div>
                    <?php else: ?>
                      <div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;"><?php echo htmlspecialchars($step['role']); ?></div>
                    <?php endif; ?>
                    <?php
                      // If this is the FROM section, display the division name under the title/job title
                      if ($section === 'from') {
                        $divisionName = $matrix->division->division_name ?? '';
                        if (!empty($divisionName)) {
                          echo '<div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;">' . htmlspecialchars($divisionName) . '</div>';
                        }
                      }
                    ?>
                  <?php elseif (isset($approver['oic_staff'])): ?>
                    <div style="font-size: 14px; font-weight: bold; line-height: 1.1;">
                      <?php echo htmlspecialchars($approver['oic_staff']['name'] . ' (OIC)'); ?>
                    </div>
                    <?php if (isset($approver['oic_staff']['job_title']) && !empty($approver['oic_staff']['job_title'])): ?>
                      <div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></div>
                    <?php elseif (isset($approver['oic_staff']['title']) && !empty($approver['oic_staff']['title'])): ?>
                      <div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;"><?php echo htmlspecialchars($approver['oic_staff']['title']); ?></div>
                    <?php else: ?>
                      <div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;"><?php echo htmlspecialchars($step['role']); ?></div>
                    <?php endif; ?>
                    <?php
                      // If this is the FROM section, display the division name under the title/job title for OIC as well
                      if ($section === 'from') {
                        $divisionName = $matrix->division->division_name ?? '';
                        if (!empty($divisionName)) {
                          echo '<div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;">' . htmlspecialchars($divisionName) . '</div>';
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
                      echo '<div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;">' . htmlspecialchars($divisionName) . '</div>';
                    }
                  }
                ?>
        <?php endif; ?>
      </td>

?>

            <td style="width: 30%; border: none; padding: 5px; vertical-align: top; text-align: left;">
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff']) && isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                                          <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approver['staff']['signature']; ?>" 
                          alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                     <br><small style="color: #666; font-size: 10px; margin-top: 5px;"><?php echo $approvalDate = getApprovalDate($approver['staff']['id'], $matrix_approval_trails,$order); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver),$approvalDate); ?></small>
                  <?php elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                                          <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approver['oic_staff']['signature']; ?>" 
                          alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                     <br><small style="color: #666; font-size: 10px; margin-top: 5px;"><?php echo $approvalDate = getApprovalDate($approver['oic_staff']['id'], $matrix_approval_trails,$order); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver),$approvalDate); ?></small>
                  <?php else: ?>
                    <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
                     <br><small style="color: #666; font-size: 10px; margin-top: 5px;"><?php echo $approvalDate = getApprovalDate(getStaffId($approver), $matrix_approval_trails,$order); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver),$approvalDate); ?></small>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </td>

            <?php if ($currentRow === 0): ?>
              <td style="width: 28%; border: none; padding: 5px; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
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
                echo $activity_refernce = htmlspecialchars("AU/CDC/{$shortCode}/IM/{$matrix->quarter}/{$year}/{$activityId}");
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
          <td style="width: 12%; border: none; padding: 5px; vertical-align: top;">
            <strong style="color: #006633; font-style: italic;"><?php echo $sectionLabels[$section] ?? ucfirst($section) . ':'; ?></strong>
          </td>
          <td style="width: 30%; border: none; padding: 5px; vertical-align: top; text-align: left;">
            <!-- No approvers for this section -->
            <?php
              // If this is the FROM section, display the division name under the empty cell
              if ($section === 'from') {
                $divisionName = $matrix->division->division_name ?? '';
                if (!empty($divisionName)) {
                  echo '<div style="color: #666; font-size: 11px; line-height: 1.1; margin-top: 1px;">' . htmlspecialchars($divisionName) . '</div>';
                }
              }
            ?>
          </td>
          <td style="width: 30%; border: none; padding: 5px; vertical-align: top; text-align: center;">
            <!-- No signatures for this section -->
      </td>
          <?php if ($currentRow === 0): ?>
            <td style="width: 28%; border: none; padding: 5px; vertical-align: top;" rowspan="<?php echo $dateFileRowspan; ?>">
              <div style="text-align: right; padding-left: 15px;">
                <div style="margin-bottom: 20px;">
                  <strong style="color: #006633;">DATE:</strong><br>
                  <span style="font-size: 12px;"><?php echo isset($matrix->created_at) ? date('j F Y', strtotime($matrix->created_at)) : date('d/m/Y'); ?></span>
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
 <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
  <tr>
    <td style="width: 12%; border: none; padding: 8px; text-align: left; vertical-align: top;"><strong style="color: #006633; font-style: italic;">Subject:</strong></td>
    <td style="width: 88%; border: none; padding: 8px; text-align: left; vertical-align: top; text-decoration: underline; font-weight: bold;"><?php echo htmlspecialchars($activity->activity_title ?? 'N/A'); ?></td>
  </tr>
 </table>

<!-- Background -->
 <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; margin-top: -5px;">
  <tr>
    <td style="width: 12%; border: none; padding: 8px; text-align: left; vertical-align: top;"><strong style="color: #006633; font-style: italic;">Background:</strong></td>
  </tr>
  <tr>
   <td style="width: 100%; border: none; padding: 8px; text-align: justify; vertical-align: top;"><?=$activity->background;?></td>
  </tr>
 </table>
  
  <div>
    
  
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
    <tr>
    <td style="width:50%; border: none; padding: 8px; text-align: left; vertical-align: top;"><strong style="color: #006633; font-style: italic;">Activity Information:</strong></td>
      </tr>
      <tr>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><strong>Division:</strong></td>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($matrix->division->division_name ?? 'N/A'); ?></td>
      </tr>
       <tr>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><strong>Activity Type:</strong></td>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($activity->requestType->name ?? 'N/A'); ?></td>
      </tr>
  
      <tr>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><strong>Activity Start Date:</strong></td>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><?php echo isset($activity->date_from) ? date('d/m/Y', strtotime($activity->date_from)) : 'N/A'; ?></td>
      </tr>
      <tr>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><strong>Activity End Date:</strong></td>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><?php echo isset($activity->date_to) ? date('d/m/Y', strtotime($activity->date_to)) : 'N/A'; ?></td>
      </tr>
      <tr>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><strong>Location (s):</strong></td>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;">
     
        <?php foreach($locations as $loc): ?>
                        <span style="display: inline-block; padding: 0.25em 0.4em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.25rem; background-color: #0dcaf0; color: #000;"><?php echo htmlspecialchars($loc->name); ?></span>
        <?php endforeach; ?>
           
          
        </td>
      </tr>
      <tr>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top; font-weight: bold; font-size: 13px;"><strong>Budget Type:</strong></td>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($activity->fundType->name ?? 'N/A'); ?></td>
      </tr>
     
    </table>
      <div style="page-break-before: always;"></div>
      <div style="margin-bottom: 1rem;">
                   
                    <div style="margin-top: 0.5rem;">
                     <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
    <tr>
    <td style="width:50%; border: none; padding: 8px; text-align: left; vertical-align: top;"><strong style="color: #006633; font-weight: bold; font-size: 14px;">Internal Participants:</strong></td>
      </tr>
      </table>
                  
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #ccc;">
                            <thead>
                                <tr>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">#</td>
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Name</th>
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Division</th>
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Job Title</th>
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Duty Station</th>
                                  
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $count = 1;
                                ?>
                                <?php foreach($internalParticipants as $entry): ?>
                                    <tr><td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;"><?php echo $count; ?></td>
                                            <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($entry['staff']->name ?? 'N/A'); ?></td>
                                             <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($entry['staff']->division_name ?? 'N/A'); ?></td>
                                            <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($entry['staff']->job_name ?? 'N/A'); ?></td>
                                          <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($entry['staff']->duty_station_name ?? 'N/A'); ?></td>
                                        <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($entry['participant_days'] ?? '-'); ?></td>
                                    </tr>
                                    <?php
                                        $count++;
                                    ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

    <div style="page-break-before: always;"></div>
              <div style="margin-bottom: 10px; color: #006633; font-weight: bold; font-size: 14px;"><strong>Budget Details</strong></div>
         
             <?php foreach($fundCodes ?? [] as $fundCode): ?>

           
             
                 <h6  style="color: #911C39; font-weight: 600;"> <?php echo htmlspecialchars($fundCode->activity); ?> - <?php echo htmlspecialchars($fundCode->code); ?> - (<?php echo htmlspecialchars($fundCode->fundType->name); ?>) </h6>

                <div>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #ccc;">
                        <thead>
                            <tr>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">#</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Cost Item</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Unit Cost</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Units</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Days</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Total</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                              $count = 1;
                              $grandTotal = 0;
                            ?>
                           
                            <?php foreach($activity->activity_budget as $item): ?>
                                <?php
                                    $total = $item->unit_cost * $item->units * $item->days;
                                    $grandTotal+=$total;
                                ?>
                                <tr>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;"><?php echo $count; ?></td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;"><?php echo htmlspecialchars($item->cost); ?></td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;"><?php echo number_format($item->unit_cost, 2); ?></td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;"><?php echo $item->units; ?></td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;"><?php echo $item->days; ?></td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;"><?php echo number_format($item->total, 2); ?></td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($item->description); ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php
                                $count++;
                            ?>
                            
                        </tbody>
                        <tfoot>
                            <tr>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;" colspan="5">Grand Total</th>
                                
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top; background-color: #f9f9f9; font-weight: bold; font-size: 12px;"><?php echo number_format($grandTotal ?? 0, 2); ?></th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;"></th>
                            </tr>
                        </tfoot>
                    </table>
                   
                </div>
     <div style="margin-bottom: 0; color: #006633; font-style: italic;"><strong>Request for Approval</strong></div>
     <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
      <tr>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($activity->activity_request_remarks ?? 'N/A'); ?></td>
      </tr>
     </table>
         
    <div style="page-break-before:always;"></div>

    <?php if($fundCode->fundType->id == 1): ?>

    <!-- Right-side memo meta (stacked, borderless) -->
    <div style="display:flex; align-items:flex-start; gap:16px; margin-bottom:10px;">
      <div style="margin-left:auto; text-align:right; line-height:1.3;" aria-label="Memo metadata">
        <span style="font-weight:700; letter-spacing:.2px;"><?php echo $activity_refernce; ?></span><br/>
        <span style="color:#64748b;">Date: <?php echo $activity->created_at->format('j F Y'); ?></span>
      </div>
    </div>

    <!-- Main form table (borderless by default) -->
    <table style="margin:12px 0 18px; background:#fff; overflow:clip; border-radius:10px; border-collapse:collapse; width:100%;" role="table" aria-label="Payment details">
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Payee/Staff<br/><span style="color:#64748b; font-size:12px;">(Vendors)</span></th>
        <td style="padding:10px; vertical-align:top; border:none;">
        <?php echo $activity->responsiblePerson->title.' '.$activity->responsiblePerson->fname.' '.$activity->responsiblePerson->lname.' '.$activity->responsiblePerson->oname; ?>
        <span style="min-height:28px; display:block; border-bottom:1px dashed #3d4043; height:22px;" aria-hidden="true"></span></td>
      </tr>
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Purpose of Payment</th>
        <td style="padding:10px; vertical-align:top; border:none;"><?php echo htmlspecialchars($activity->activity_title); ?><span style="min-height:28px; display:block; border-bottom:1px dashed #3d4043; height:22px;"></span></td>
      </tr>
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Department Name<br/><span style="color:#64748b; font-size:12px;">(Cost Center)</span></th>
        <td style="padding:10px; vertical-align:top; border:none;">Africa CDC - <?php echo $matrix->division->division_name ?? ''; ?><span style="min-height:28px; display:block; border-bottom:1px dashed #3d4043; height:22px;"></span></td>
      </tr>
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Project/Program<br/><span style="color:#64748b; font-size:12px;">(Fund Center)</span></th>
        <td style="padding:10px; vertical-align:top; border:none;"><?php echo htmlspecialchars($fundCode->code); ?><span style="min-height:28px; display:block; border-bottom:1px dashed #3d4043; height:22px;"></span></td>
      </tr>
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Fund <span style="color:#64748b; font-size:12px;">(Member State or Partner/Donor)</span></th>
          <td style="padding:10px; vertical-align:top; border:none;"><?php echo htmlspecialchars($fundCode->fund); ?><span style="min-height:28px; display:block; border-bottom:1px dashed #3d4043; height:22px;"></span></td>
      </tr>
    </table>
<?php
    $approvalOrder5 = $activity->activityApprovalTrails->where('approval_order', 5);
    //dd($approvalOrder5->first());
?>
    <!-- Budget / Certification (table-only, borderless unless specified inline) -->
    <table style="margin:12px 0 18px; background:#fff; overflow:clip; border-radius:10px; border-collapse:collapse; width:100%;" role="table" aria-label="Budget and Certification">
      <tr>
        <td style="background:#f9fafb; font-weight:600; padding:10px; vertical-align:top; min-height:36px; border:none;">Strategic Axis Budget Balance (Certified by SFO)</td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">USD</td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">$ <?=number_format($approvalOrder5->first()->amount_allocated ?? 0, 2);?></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">Date: <?=$approvalOrder5->first()->created_at->format('j F Y') ?? 'N/A';?></td>
      </tr>
      <tr>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;"></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;"></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;"></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">
            Signature:
            <?php
           
                // For approval_order 5, use $approvalOrder5->first() as the approver
                $sfoApproval = $approvalOrder5->first();
                if ($sfoApproval && $sfoApproval->staff && !empty($sfoApproval->staff->signature)) {
                    // Staff signature present
            ?>
                <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $sfoApproval->staff->signature; ?>"
                     alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                <br>
                <small style="color: #666; font-size: 10px; margin-top: 5px;">
                    <?php echo \Carbon\Carbon::parse($sfoApproval->created_at)->format('j F Y'); ?>
                </small>
                <br>
                <small style="color: #999; font-size: 9px;">
                    Hash: <?php echo generateVerificationHash($activity->id, $sfoApproval->staff_id, $sfoApproval->created_at ? $sfoApproval->created_at->format('Y-m-d H:i:s') : null); ?>
                </small>
            <?php } else { ?>
                <span style="color:#aaa;">N/A</span>
            <?php } ?>
        </td>
      </tr>
      <tr>
        <td style="background:#f9fafb; font-weight:600; padding:10px; vertical-align:top; min-height:36px; border:none;">Estimated cost</td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">USD</td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;"><?php echo number_format($grandTotal, 2); ?></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">Name: <?php echo $sfoApproval && $sfoApproval->staff ? $sfoApproval->staff->title.' '.$sfoApproval->staff->fname.' '.$sfoApproval->staff->lname.' '.$sfoApproval->staff->oname : 'N/A'; ?></td>
      </tr>
    </table>

    <!-- Signatures (borderless by default). Last column adds ONLY a left border inline -->
    <table style="margin-top:18px; border-radius:10px; overflow:clip; border-collapse:collapse; width:100%;" role="table" aria-label="Approvals">
      <tr>
        <td style="width:40%; font-weight:600; background:#f9fafb; padding:14px 10px; vertical-align:bottom; height:90px; border:none;">Signed (Prepared By)</td>
        <?php
            $approvalOrder1 = $activity->activityApprovalTrails->where('approval_order', 1)->first();
            //dd($approvalOrder1->first());
        ?>
        
        <td style="width:30%; padding:14px 10px; vertical-align:bottom; height:90px; border:none;">
          <?php echo $approvalOrder1 && $approvalOrder1->staff ? $approvalOrder1->staff->title.' '.$approvalOrder1->staff->fname.' '.$approvalOrder1->staff->lname.' '.$approvalOrder1->staff->oname : 'N/A'; ?>
          <br>
          <?=$approvalOrder1 && $approvalOrder1->staff ? $approvalOrder1->staff->job_name : 'N/A';?><br>
          <?=$approvalOrder1 && $approvalOrder1->staff ? $approvalOrder1->staff->division_name : 'N/A';?><br>
        <span style="min-height:28px; display:block; border-bottom:1px dashed #3d4043; height:22px;"></span></td>
        <td style="border-left:1px solid #d8dee9; border-top:none; border-right:none; border-bottom:none; padding:14px 10px; vertical-align:bottom; height:90px;">
          <span style="min-height:28px; display:block;">
         <?php if ($approvalOrder1 && $approvalOrder1->staff && !empty($approvalOrder1->staff->signature)) {
                    // Staff signature present
            ?>
          <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approvalOrder1->staff->signature; ?>"
                     alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                <br>
                <small style="color: #666; font-size: 10px; margin-top: 5px; font-weight: 500;">
                    <?php echo \Carbon\Carbon::parse($approvalOrder1->created_at)->format('j F Y'); ?>
                </small>
                <br>
                <small style="color: #999; font-size: 9px; font-weight: 500;">
                    Hash: <?php echo generateVerificationHash($activity->id, $approvalOrder1->staff_id, $approvalOrder1->created_at ? $approvalOrder1->created_at->format('Y-m-d H:i:s') : null); ?>
                </small>
            <?php } else { ?>
                <span style="color:#aaa;">N/A</span>
            <?php } ?>
          </span>
        </td>
      </tr>
      <tr>
        <td style="width:40%; font-weight:600; background:#f9fafb; padding:14px 10px; vertical-align:bottom; height:90px; border:none;">Endorsed by</td>
        <?php
            $approvalOrder6 = $activity->activityApprovalTrails->where('approval_order', 6)->first();
            //dd($approvalOrder2->first());
        ?>
        <td style="width:30%; padding:14px 10px; vertical-align:bottom; height:90px; border:none;">
        <?php echo $approvalOrder6 && $approvalOrder6->staff ? $approvalOrder6->staff->title.' '.$approvalOrder6->staff->fname.' '.$approvalOrder6->staff->lname.' '.$approvalOrder6->staff->oname : 'N/A'; ?>
          <br>
          <?=$approvalOrder6 && $approvalOrder6->staff ? $approvalOrder6->staff->job_name : 'N/A';?><br>
          <?=$approvalOrder6 && $approvalOrder6->staff ? $approvalOrder6->staff->division_name : 'N/A';?><br><span style="min-height:28px; display:block; border-bottom:1px dashed #3d4043; height:22px;"></span></td>
        <td style="border-left:1px solid #d8dee9; border-top:none; border-right:none; border-bottom:none; padding:14px 10px; vertical-align:bottom; height:90px;">
          
          <span style="min-height:28px; display:block;">
            <?php if ($approvalOrder6 && $approvalOrder6->staff && !empty($approvalOrder6->staff->signature)) {
                    // Staff signature present
            ?>
          <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approvalOrder6->staff->signature; ?>"
                     alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                <br>
                <small style="color: #666; font-size: 10px; margin-top: 5px;">
                    <?php echo \Carbon\Carbon::parse($approvalOrder6->created_at)->format('j F Y'); ?>
                </small>
                <br>
                <small style="color: #999; font-size: 9px;">
                    Hash: <?php echo generateVerificationHash($activity->id, $approvalOrder6->staff_id, $approvalOrder6->created_at ? $approvalOrder6->created_at->format('Y-m-d H:i:s') : null); ?>
                </small>
            <?php } else { ?>
                <span style="color:#aaa;">N/A</span>
            <?php } ?>
          </span>
        </td>
      </tr>
      <tr>
        <td style="width:40%; font-weight:600; background:#f9fafb; padding:14px 10px; vertical-align:bottom; height:90px; border:none;">Approved</td>
        <?php
            $approvalOrder8 = $activity->activityApprovalTrails->where('approval_order', 8)->first();
            //dd($approvalOrder8->first());
        ?>
        <td style="width:30%; padding:14px 10px; vertical-align:bottom; height:90px; border:none;">
        <?php echo $approvalOrder8 && $approvalOrder8->staff ? $approvalOrder8->staff->title.' '.$approvalOrder8->staff->fname.' '.$approvalOrder8->staff->lname.' '.$approvalOrder8->staff->oname : 'N/A'; ?>
          <br>
          <?=$approvalOrder8 && $approvalOrder8->staff ? $approvalOrder8->staff->job_name : 'N/A';?><br>
          <?=$approvalOrder8 && $approvalOrder8->staff ? $approvalOrder8->staff->division_name : 'N/A';?><br>
          <span style="min-height:28px; display:block; border-bottom:1px dashed #3d4043; height:22px;"></span></td>
        <td style="border-left:1px solid #d8dee9; border-top:none; border-right:none; border-bottom:none; padding:14px 10px; vertical-align:bottom; height:90px;">
       
          <span style="min-height:28px; display:block;">
            <?php if ($approvalOrder8 && $approvalOrder8->staff && !empty($approvalOrder8->staff->signature)) {
                    // Staff signature present
            ?>
          <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approvalOrder8->staff->signature; ?>"
                     alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                <br>
                <small style="color: #666; font-size: 10px; margin-top: 5px;">
                    <?php echo \Carbon\Carbon::parse($approvalOrder8->created_at)->format('j F Y'); ?>
                </small>
                <br>
                <small style="color: #999; font-size: 9px;">
                    Hash: <?php echo generateVerificationHash($activity->id, $approvalOrder8->staff_id, $approvalOrder8->created_at ? $approvalOrder8->created_at->format('Y-m-d H:i:s') : null); ?>
                </small>
            <?php } else { ?>
                <span style="color:#aaa;">N/A</span>
            <?php } ?>
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