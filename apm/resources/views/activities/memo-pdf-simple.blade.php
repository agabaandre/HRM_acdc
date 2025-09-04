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
    <td style="width: 12%;"><strong style="color: #006633; font-style: italic;">Background:</strong></td>
  </tr>
  <tr>
   <td style="width: 100%; text-align: justify;"><?=$activity->background;?></td>
  </tr>
 </table>
  
  <div>
    
  
    <table class="no-border">
    <tr>
    <td style="width:50% ;"><strong style="color: #006633; font-style: italic;">Activity Information:</strong></td>
      </tr>
      <tr>
        <td><strong>Division:</strong></td>
        <td><?php echo htmlspecialchars($matrix->division->division_name ?? 'N/A'); ?></td>
      </tr>
       <tr>
        <td><strong>Activity Type:</strong></td>
        <td><?php echo htmlspecialchars($activity->requestType->name ?? 'N/A'); ?></td>
      </tr>
  
      <tr>
        <td><strong>Activity Start Date:</strong></td>
        <td><?php echo isset($activity->date_from) ? date('d/m/Y', strtotime($activity->date_from)) : 'N/A'; ?></td>
      </tr>
      <tr>
        <td><strong>Activity End Date:</strong></td>
        <td><?php echo isset($activity->date_to) ? date('d/m/Y', strtotime($activity->date_to)) : 'N/A'; ?></td>
      </tr>
      <tr>
        <td><strong>Location (s):</strong></td>
        <td>
     
        @foreach($locations as $loc)
                        <span class="badge bg-info">{{ $loc->name }}</span>
        @endforeach
           
          
        </td>
      </tr>
      <tr>
        <td><strong>Budget Type:</strong></td>
        <td><?php echo htmlspecialchars($activity->fundType->name ?? 'N/A'); ?></td>
      </tr>
     
    </table>

      <div class="mb-3">
                   
                    <div class="table-responsive mt-2">
                     <table class="no-border">
    <tr>
    <td style="width:50% ;"><strong style="color: #006633; font-style: italic;">Internal Participants:</strong></td>
      </tr>
      </table>
                  
                        <table class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>#</td>
                                    <th>Name</th>
                                    <th>Division</th>
                                    <th>Job Title</th>
                                    <th>Duty Station</th>
                                  
                                    <th>Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $count = 1;
                                @endphp
                                @foreach($internalParticipants as $entry)
                                    <tr><td>{{$count}}</td>
                                            <td>{{ $entry['staff']->name ?? 'N/A' }}</td>
                                             <td>{{ $entry['staff']->division_name ?? 'N/A' }}</td>
                                            <td>{{ $entry['staff']->job_name ?? 'N/A' }}</td>
                                          <td>{{ $entry['staff']->duty_station_name ?? 'N/A' }}</td>
                                        <td>{{ $entry['participant_days'] ?? '-' }}</td>
                                    </tr>
                                    @php
                                        $count++;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>


              <div class="mb-0" style="color: #006633; font-style: italic;"><strong>Budget Details</strong></div>
         
             @foreach($fundCodes ?? [] as $fundCode )

           
             
                 <h6  style="color: #911C39; font-weight: 600;"> {{ $fundCode->activity }} - {{ $fundCode->code }} - ({{ $fundCode->fundType->name }}) </h6>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cost Item</th>
                                <th>Unit Cost</th>
                                <th>Units</th>
                                <th>Days</th>
                                <th>Total</th>
                                <th>Description</th>
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
                                    <td>{{$count}}</td>
                                    <td class="text-end">{{ $item->cost }}</td>
                                    <td class="text-end">{{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="text-end">{{ $item->units }}</td>
                                    <td class="text-end">{{ $item->days }}</td>
                                    <td class="text-end">{{ number_format($item->total, 2) }}</td>
                                    <td>{{ $item->description }}</td>
                                </tr>
                            @endforeach

                            @php
                                $count++;
                            @endphp
                            
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Grand Total</th>
                                
                                <th class="text-end" cols>{{  number_format($grandTotal?? 0, 2)}}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                     @endforeach
                </div>
     <div class="mb-0" style="color: #006633; font-style: italic;"><strong>Request for Approval</strong></div>
     <table class="no-border">
      <tr>
        <td><?php echo htmlspecialchars($activity->activity_request_remarks ?? 'N/A'); ?></td>
      </tr>
     </table>
         
    <div style="page-break-before: always;"></div>


    <style>
  :root{
    --au-red:#911C39;
    --au-green:#119A48;
    --au-gold:#C3A366;
    --ink:#001011;
    --muted:#7A7A7A;
    --line:#444;
    --bg:#f7f7f7;
  }
  *{box-sizing:border-box}
  body{margin:0; color:var(--ink); font:14px/1.35 Arial,Helvetica,sans-serif; background:#fff;}
  .sheet{max-width:940px; margin:16px auto;}

  table.form, table.sig-grid{width:100%; border-collapse:collapse;}
  table.form td, table.form th,
  table.sig-grid td, table.sig-grid th{
    border:1px solid var(--line); padding:8px; vertical-align:top;
  }
  table.form th.label{width:270px; font-weight:700;}
  table.form .tick{width:26px;}
  table.form small{color:var(--muted); font-weight:600;}
  .muted{color:var(--muted); font-weight:normal;}
  .currency{width:90px; text-align:center; font-weight:700;}
  .amount{width:280px;}
  .rightbox div{margin:3px 0;}
  .nowrap{white-space:nowrap;}
  .sign-box{height:72px; border:1px dashed #999; margin-top:6px;}
  .section-head{background:var(--bg); font-weight:700;}
  .sig-title{background:var(--bg); font-weight:700; color:var(--au-red);}
  .chip{display:inline-block; padding:2px 6px; border:1px solid var(--au-red); border-radius:4px; font-size:12px;}
  @media print {.sheet{max-width:190mm}}
</style>
</head>
<body>
<div class="sheet">

  <!-- MAIN FORM TABLE -->
  <table class="form">
    <colgroup>
      <col style="width:26px">
      <col style="width:270px">
      <col>
      <col style="width:280px">
    </colgroup>
    <tbody>
      <tr>
        <td class="tick"></td>
        <th class="label">Payee/Staff <small>(Vendors)</small></th>
        <td colspan="2">Africa CDC</td>
      </tr>

      <tr>
        <td class="tick"></td>
        <th class="label">Purpose of Payment</th>
        <td colspan="2"><strong>Request for PHEOC Infrastructure Inspection and Installation of ICT Equipment and Accessories</strong></td>
      </tr>

      <tr>
        <td class="tick"></td>
        <th class="label">Department Name <small>(Cost Center)</small></th>
        <td>Africa CDC</td>
        <td>Africa CDC</td>
      </tr>

      <tr>
        <td class="tick"></td>
        <th class="label">Project/Program <small>(Fund Center)</small></th>
        <td>CDC0603015MB</td>
        <td></td>
      </tr>

      <tr>
        <td class="tick"></td>
        <th class="label">Fund <small>(Member State or Name of Partner/Donor)</small></th>
        <td>PBCOVADB01</td>
        <td></td>
      </tr>

      <tr>
        <td class="tick"></td>
        <th class="label">Strategic Axis Budget Balance <small>(Certified by SFO)</small></th>
        <td class="currency">USD</td>
        <td class="rightbox">
          <div>US $ <strong>205,270</strong></div>
          <div class="nowrap">Date: <span>12/03/2025</span></div>
          <div>Signature:<div class="sign-box"></div></div>
          <div class="nowrap">Name: <strong>SFO</strong></div>
        </td>
      </tr>

      <tr>
        <td class="tick"></td>
        <th class="label">Estimated cost</th>
        <td class="currency">USD</td>
        <td class="amount"><strong>205,270 US $</strong></td>
      </tr>
    </tbody>
  </table>

  <!-- SIGNATORIES BLOCK -->
  <table class="sig-grid" style="margin-top:14px;">
    <colgroup>
      <col>
      <col style="width:240px">
    </colgroup>
    <tbody>
      <tr><th class="sig-title" colspan="2">Signed (Prepared by)</th></tr>
      <tr>
        <td>
          <div><strong>Dr Wessam Mankoula</strong></div>
          <div>Ag Head of Emergency Preparedness and Response Division, Africa CDC</div>
        </td>
        <td>
          <div>Signed by:</div>
          <div class="sign-box"></div>
        </td>
      </tr>

      <tr><th class="sig-title" colspan="2">Endorsed by</th></tr>
      <tr>
        <td>
          <div><strong>Adedayo Akinwale</strong></div>
          <div>Charles OIC Director of Finance, Africa CDC</div>
        </td>
        <td>
          <div>Signature / DocuSign:</div>
          <div class="sign-box"></div>
        </td>
      </tr>

      <tr><th class="sig-title" colspan="2">Approved by</th></tr>
      <tr>
        <td>
          <div><strong>Dr. Raji Tajudeen</strong></div>
          <div>Ag. Deputy Director General, Africa CDC</div>
        </td>
        <td>
          <div>Signed by:</div>
          <div class="sign-box"></div>
        </td>
      </tr>
    </tbody>
  </table>


    
   
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