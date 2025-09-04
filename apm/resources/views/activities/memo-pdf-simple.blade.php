<html>
<head>
</head>
<body style="font-family: Arial, sans-serif; font-size: 13px; margin: 20px; line-height: 1.4;">
  <?php  //dd($activity); ?>
  <!-- Header -->


  <!-- Document Title -->
  <h1 style="font-size: 20px; font-weight: bold; text-align: center; margin-top: -20px; margin-bottom: 10px; color: #000000;">Interoffice Memorandum</h1>
  
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
        <?php foreach ($organized_workflow_steps[$section] as $index => $step): ?>
          <tr>
            <td style="width: 12%; border: none; padding: 5px; vertical-align: top;">
              <strong style="color: #006633; font-style: italic;"><?php echo $sectionLabels[$section] ?? strtoupper($section) . ':'; ?></strong>
            </td>

                        <td style="width: 30%; border: none; padding: 5px; vertical-align: top; text-align: left;">
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

            <td style="width: 30%; border: none; padding: 5px; vertical-align: top; text-align: left;">
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff']) && isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                                          <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approver['staff']['signature']; ?>" 
                          alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                     <br><small style="color: #666; font-size: 10px; margin-top: 5px;"><?php echo getApprovalDate($approver['staff']['id'], $matrix_approval_trails); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                                          <img src="<?php echo user_session('base_url') . 'uploads/staff/signature/' . $approver['oic_staff']['signature']; ?>" 
                          alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain; filter: contrast(1.2);">
                     <br><small style="color: #666; font-size: 10px; margin-top: 5px;"><?php echo getApprovalDate($approver['oic_staff']['id'], $matrix_approval_trails); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php else: ?>
                    <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
                     <br><small style="color: #666; font-size: 10px; margin-top: 5px;"><?php echo getApprovalDate(getStaffId($approver), $matrix_approval_trails); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
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
                  echo '<div style="color: #666; font-size: 12px; line-height: 1.0; margin-top: 1px;">' . htmlspecialchars($divisionName) . '</div>';
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
     
        @foreach($locations as $loc)
                        <span style="display: inline-block; padding: 0.25em 0.4em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.25rem; background-color: #0dcaf0; color: #000;">{{ $loc->name }}</span>
        @endforeach
           
          
        </td>
      </tr>
      <tr>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><strong>Budget Type:</strong></td>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($activity->fundType->name ?? 'N/A'); ?></td>
      </tr>
     
    </table>
      <div style="page-break-before: always;"></div>
      <div style="margin-bottom: 1rem;">
                   
                    <div style="margin-top: 0.5rem;">
                     <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
    <tr>
    <td style="width:50%; border: none; padding: 8px; text-align: left; vertical-align: top;"><strong style="color: #006633; font-style: italic;">Internal Participants:</strong></td>
      </tr>
      </table>
                  
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #ccc;">
                            <thead>
                                <tr>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">#</td>
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Name</th>
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Division</th>
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Job Title</th>
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Duty Station</th>
                                  
                                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $count = 1;
                                @endphp
                                @foreach($internalParticipants as $entry)
                                    <tr><td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;">{{$count}}</td>
                                            <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;">{{ $entry['staff']->name ?? 'N/A' }}</td>
                                             <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;">{{ $entry['staff']->division_name ?? 'N/A' }}</td>
                                            <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;">{{ $entry['staff']->job_name ?? 'N/A' }}</td>
                                          <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;">{{ $entry['staff']->duty_station_name ?? 'N/A' }}</td>
                                        <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;">{{ $entry['participant_days'] ?? '-' }}</td>
                                    </tr>
                                    @php
                                        $count++;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

    <div style="page-break-before: always;"></div>
              <div style="margin-bottom: 0; color: #006633; font-style: italic;"><strong>Budget Details</strong></div>
         
             @foreach($fundCodes ?? [] as $fundCode )

           
             
                 <h6  style="color: #911C39; font-weight: 600;"> {{ $fundCode->activity }} - {{ $fundCode->code }} - ({{ $fundCode->fundType->name }}) </h6>

                <div>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #ccc;">
                        <thead>
                            <tr>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">#</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Cost Item</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Unit Cost</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Units</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Days</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Total</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                              $count = 1;
                              $grandTotal = 0;
                            @endphp
                           
                            @foreach($activity->activity_budget as $item)
                                @php
                                    $total = $item->unit_cost * $item->units * $item->days;
                                    $grandTotal+=$total;
                                @endphp
                                <tr>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;">{{$count}}</td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;">{{ $item->cost }}</td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;">{{ number_format($item->unit_cost, 2) }}</td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;">{{ $item->units }}</td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;">{{ $item->days }}</td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top;">{{ number_format($item->total, 2) }}</td>
                                    <td style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top;">{{ $item->description }}</td>
                                </tr>
                            @endforeach

                            @php
                                $count++;
                            @endphp
                            
                        </tbody>
                        <tfoot>
                            <tr>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top; background-color: #f9f9f9;" colspan="5">Grand Total</th>
                                
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: right; vertical-align: top; background-color: #f9f9f9;">{{  number_format($grandTotal?? 0, 2)}}</th>
                                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; background-color: #f9f9f9;"></th>
                            </tr>
                        </tfoot>
                    </table>
                     @endforeach
                </div>
     <div style="margin-bottom: 0; color: #006633; font-style: italic;"><strong>Request for Approval</strong></div>
     <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
      <tr>
        <td style="border: none; padding: 8px; text-align: left; vertical-align: top;"><?php echo htmlspecialchars($activity->activity_request_remarks ?? 'N/A'); ?></td>
      </tr>
     </table>
         
    <div style="page-break-before:always;"></div>


    <!-- Right-side memo meta (stacked, borderless) -->
    <div style="display:flex; align-items:flex-start; gap:16px; margin-bottom:10px;">
      <div style="margin-left:auto; text-align:right; line-height:1.3;" aria-label="Memo metadata">
        <span style="font-weight:700; letter-spacing:.2px;">AU/MEMO/001</span><br/>
        <span style="color:#64748b;">Date: 20/08/2010</span>
      </div>
    </div>

    <!-- Main form table (borderless by default) -->
    <table style="margin:12px 0 18px; background:#fff; overflow:clip; border-radius:10px; border-collapse:collapse; width:100%;" role="table" aria-label="Payment details">
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Payee/Staff<br/><span style="color:#64748b; font-size:12px;">(Vendors)</span></th>
        <td style="padding:10px; vertical-align:top; border:none;"><span style="min-height:28px; display:block; border-bottom:1px dashed #cbd5e1; height:22px;" aria-hidden="true"></span></td>
      </tr>
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Purpose of Payment</th>
        <td style="padding:10px; vertical-align:top; border:none;"><span style="min-height:28px; display:block; border-bottom:1px dashed #cbd5e1; height:22px;"></span></td>
      </tr>
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Department Name<br/><span style="color:#64748b; font-size:12px;">(Cost Center)</span></th>
        <td style="padding:10px; vertical-align:top; border:none;"><span style="min-height:28px; display:block; border-bottom:1px dashed #cbd5e1; height:22px;"></span></td>
      </tr>
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Project/Program<br/><span style="color:#64748b; font-size:12px;">(Fund Center)</span></th>
        <td style="padding:10px; vertical-align:top; border:none;"><span style="min-height:28px; display:block; border-bottom:1px dashed #cbd5e1; height:22px;"></span></td>
      </tr>
      <tr>
        <th style="width:36%; font-weight:600; text-align:left; background:#f9fafb; padding:10px; vertical-align:top; border:none;" scope="row">Fund <span style="color:#64748b; font-size:12px;">(Member State or Partner/Donor)</span></th>
        <td style="padding:10px; vertical-align:top; border:none;"><span style="min-height:28px; display:block; border-bottom:1px dashed #cbd5e1; height:22px;"></span></td>
      </tr>
    </table>

    <!-- Budget / Certification (table-only, borderless unless specified inline) -->
    <table style="margin:12px 0 18px; background:#fff; overflow:clip; border-radius:10px; border-collapse:collapse; width:100%;" role="table" aria-label="Budget and Certification">
      <tr>
        <td style="background:#f9fafb; font-weight:600; padding:10px; vertical-align:top; min-height:36px; border:none;">Strategic Axis Budget Balance (Certified by SFO)</td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">USD</td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;"></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">Date:</td>
      </tr>
      <tr>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;"></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;"></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;"></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">Signature:</td>
      </tr>
      <tr>
        <td style="background:#f9fafb; font-weight:600; padding:10px; vertical-align:top; min-height:36px; border:none;">Estimated cost</td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">USD</td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;"></td>
        <td style="padding:10px; vertical-align:top; min-height:36px; border:none;">Name: SFO</td>
      </tr>
    </table>

    <!-- Signatures (borderless by default). Last column adds ONLY a left border inline -->
    <table style="margin-top:18px; border-radius:10px; overflow:clip; border-collapse:collapse; width:100%;" role="table" aria-label="Approvals">
      <tr>
        <td style="width:40%; font-weight:600; background:#f9fafb; padding:14px 10px; vertical-align:bottom; height:90px; border:none;">Signed (Prepared By)</td>
        <td style="width:30%; padding:14px 10px; vertical-align:bottom; height:90px; border:none;"><span style="min-height:28px; display:block; border-bottom:1px dashed #cbd5e1; height:22px;"></span></td>
        <td style="border-left:1px solid #d8dee9; border-top:none; border-right:none; border-bottom:none; padding:14px 10px; vertical-align:bottom; height:90px;">
          <span style="min-height:28px; display:block;"></span>
        </td>
      </tr>
      <tr>
        <td style="width:40%; font-weight:600; background:#f9fafb; padding:14px 10px; vertical-align:bottom; height:90px; border:none;">Endorsed by</td>
        <td style="width:30%; padding:14px 10px; vertical-align:bottom; height:90px; border:none;"><span style="min-height:28px; display:block; border-bottom:1px dashed #cbd5e1; height:22px;"></span></td>
        <td style="border-left:1px solid #d8dee9; border-top:none; border-right:none; border-bottom:none; padding:14px 10px; vertical-align:bottom; height:90px;">
          <span style="min-height:28px; display:block;"></span>
        </td>
      </tr>
      <tr>
        <td style="width:40%; font-weight:600; background:#f9fafb; padding:14px 10px; vertical-align:bottom; height:90px; border:none;">Approved</td>
        <td style="width:30%; padding:14px 10px; vertical-align:bottom; height:90px; border:none;"><span style="min-height:28px; display:block; border-bottom:1px dashed #cbd5e1; height:22px;"></span></td>
        <td style="border-left:1px solid #d8dee9; border-top:none; border-right:none; border-bottom:none; padding:14px 10px; vertical-align:bottom; height:90px;">
          <span style="min-height:28px; display:block;"></span>
        </td>
      </tr>
    </table>
</body>
</html>