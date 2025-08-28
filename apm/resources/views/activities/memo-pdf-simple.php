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
      margin: -4px 0; 
      margin-bottom: 40px;
      text-transform: uppercase;
      color: #000000; 
    }
    .section-title { 
      font-size: 14px; 
      font-weight: bold; 
      margin-top: 20px; 
      border-bottom: 1px solid #ccc; 
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
      return substr(md5(sha1($activityId . $staffId . date('Y-m-d'))), 0, 12);
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

  <table class="memo-table" style="width: 100%;">
    <!-- TO: Director General -->
    <tr>
      <td style="width: 15%; border: none; padding: 5px;"><strong style="color: #006633;">TO:</strong></td>
      <td style="width: 30%; border: none; padding: 5px;">
        <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
          <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
            <?php if (stripos($step['role'] ?? '', 'DG') !== false): ?>
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff'])): ?>
                    <?php echo htmlspecialchars($approver['staff']['name']); ?>
                    <?php if (isset($approver['staff']['job_title']) && !empty($approver['staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Director General of Africa CDC</small>
                    <?php endif; ?>
                  <?php elseif (isset($approver['oic_staff'])): ?>
                    <?php echo htmlspecialchars($approver['oic_staff']['name']); ?> (OIC)
                    <?php if (isset($approver['oic_staff']['job_title']) && !empty($approver['oic_staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Director General of Africa CDC</small>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                Director General of Africa CDC
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          Director General of Africa CDC
        <?php endif; ?>
      </td>
      <td style="width: 25%; border: none; padding: 5px;">
        <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
          <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
            <?php if (stripos($step['role'] ?? '', 'DG') !== false): ?>
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff']) && isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                    <img src="<?php echo asset('uploads/staff/signature/' . $approver['staff']['signature']); ?>" 
                         alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                    <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                    <img src="<?php echo asset('uploads/staff/signature/' . $approver['oic_staff']['signature']); ?>" 
                         alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                    <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php else: ?>
                    <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
                    <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </td>
      <td style="width: 15%; border: none; padding: 5px;" rowspan="6">
        <div style="text-align: right; padding-left: 10px;">
          <div style="margin-bottom: 15px;">
            <strong style="color: #006633;">DATE:</strong> <br>           <?php echo date('d/m/Y'); ?>
          </div>
          <div><br>
            <strong style="color: #006633;">FILE NO:</strong>
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
                $year = date('Y', strtotime($activity->created_at ?? 'now'));
                $activityId = $activity->id ?? 'N/A';
                echo htmlspecialchars("AU/CDC/{$shortCode}/IM/{$matrix->quarter}/{$year}/{$activityId}");
              } else {
                echo 'N/A';
              }
            ?>
          </div>
        </div>
      </td>
    </tr>
    
    <!-- THROUGH: Chief of Staff -->
    <tr>
      <td style="border: none; padding: 5px;"><strong style="color: #006633;">THROUGH:</strong></td>
      <td style="border: none; padding: 5px;">
        <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
          <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
            <?php if (stripos($step['role'] ?? '', 'COS') !== false): ?>
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff'])): ?>
                    <?php echo htmlspecialchars($approver['staff']['name']); ?>
                    <?php if (isset($approver['staff']['job_title']) && !empty($approver['staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Chief of Staff</small>
                    <?php endif; ?>
                  <?php elseif (isset($approver['oic_staff'])): ?>
                    <?php echo htmlspecialchars($approver['oic_staff']['name']); ?> (OIC)
                    <?php if (isset($approver['oic_staff']['job_title']) && !empty($approver['oic_staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Chief of Staff</small>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                Chief of Staff
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          Chief of Staff
        <?php endif; ?>
      </td>
      <td>
        <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
          <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
            <?php if (stripos($step['role'] ?? '', 'COS') !== false): ?>
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff']) && isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                    <img src="<?php echo asset('uploads/staff/signature/' . $approver['staff']['signature']); ?>" 
                         alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                    <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                    <img src="<?php echo asset('uploads/staff/signature/' . $approver['oic_staff']['signature']); ?>" 
                         alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                    <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php else: ?>
                    <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
                    <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </td>
    </tr>
    
    <!-- THROUGH: DDG -->
    <tr>
      <td></td>
      <td>
        <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
          <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
            <?php if (stripos($step['role'] ?? '', 'DDG') !== false): ?>
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff'])): ?>
                    <?php echo htmlspecialchars($approver['staff']['name']); ?>
                    <?php if (isset($approver['staff']['job_title']) && !empty($approver['staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Ag. Deputy Director General</small>
                    <?php endif; ?>
                  <?php elseif (isset($approver['oic_staff'])): ?>
                    <?php echo htmlspecialchars($approver['oic_staff']['name']); ?> (OIC)
                    <?php if (isset($approver['oic_staff']['job_title']) && !empty($approver['oic_staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Ag. Deputy Director General</small>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                Ag. Deputy Director General
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          Ag. Deputy Director General
        <?php endif; ?>
      </td>
      <td>
        <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
          <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
            <?php if (stripos($step['role'] ?? '', 'DDG') !== false): ?>
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff']) && isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                    <img src="<?php echo asset('uploads/staff/signature/' . $approver['staff']['signature']); ?>" 
                         alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                    <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                    <img src="<?php echo asset('uploads/staff/signature/' . $approver['oic_staff']['signature']); ?>" 
                         alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                    <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php else: ?>
                    <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
                    <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                    <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </td>
    </tr>
    
    <!-- THROUGH: Head of Operations/Programs (Conditional) -->
    <tr>
      <td></td>
      <td>
        <?php
          // Check if activity division is Operations or Programs and display appropriate head
          $divisionCategory = $matrix->division->category ?? '';
          if (stripos($divisionCategory, 'Operations') !== false): ?>
            <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
              <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
                <?php if (stripos($step['role'] ?? '', 'Head of Operations') !== false): ?>
                  <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                    <?php foreach ($step['approvers'] as $approver): ?>
                                        <?php if (isset($approver['staff'])): ?>
                    <?php echo htmlspecialchars($approver['staff']['name']); ?>
                    <?php if (isset($approver['staff']['job_title']) && !empty($approver['staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Head of Operations</small>
                    <?php endif; ?>
                  <?php elseif (isset($approver['oic_staff'])): ?>
                    <?php echo htmlspecialchars($approver['oic_staff']['name']); ?> (OIC)
                    <?php if (isset($approver['oic_staff']['job_title']) && !empty($approver['oic_staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Head of Operations</small>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                Andrew Agaba<br><small style="color: #666;">Head of Operations</small>
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          Andrew Agaba<br><small style="color: #666;">Head of Operations</small>
        <?php endif; ?>
      <?php elseif (stripos($divisionCategory, 'Programs') !== false): ?>
        <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
          <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
            <?php if (stripos($step['role'] ?? '', 'Head of Programs') !== false): ?>
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff'])): ?>
                    <?php echo htmlspecialchars($approver['staff']['name']); ?>
                    <?php if (isset($approver['staff']['job_title']) && !empty($approver['staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Head of Programs</small>
                    <?php endif; ?>
                  <?php elseif (isset($approver['oic_staff'])): ?>
                    <?php echo htmlspecialchars($approver['oic_staff']['name']); ?> (OIC)
                    <?php if (isset($approver['oic_staff']['job_title']) && !empty($approver['oic_staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Head of Programs</small>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                Amare Meselu<br><small style="color: #666;">Head of Programs</small>
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          Amare Meselu<br><small style="color: #666;">Head of Programs</small>
        <?php endif; ?>
      <?php else: ?>
        <!-- Default fallback -->
        <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
          <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
            <?php if (stripos($step['role'] ?? '', 'Head of Operations') !== false): ?>
              <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                <?php foreach ($step['approvers'] as $approver): ?>
                  <?php if (isset($approver['staff'])): ?>
                    <?php echo htmlspecialchars($approver['staff']['name']); ?>
                    <?php if (isset($approver['staff']['job_title']) && !empty($approver['staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Head of Operations</small>
                    <?php endif; ?>
                  <?php elseif (isset($approver['oic_staff'])): ?>
                    <?php echo htmlspecialchars($approver['oic_staff']['name']); ?> (OIC)
                    <?php if (isset($approver['oic_staff']['job_title']) && !empty($approver['oic_staff']['job_title'])): ?>
                      <br><small style="color: #666;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></small>
                    <?php else: ?>
                      <br><small style="color: #666;">Head of Operations</small>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                Head of Operations
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          Head of Operations
        <?php endif; ?>
      <?php endif; ?>
      </td>
      <td>
        <?php
          // Check if activity division is Operations or Programs and display appropriate signature
          if (stripos($divisionName, 'Operations') !== false): ?>
            <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
              <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
                <?php if (stripos($step['role'] ?? '', 'Head of Operations') !== false): ?>
                  <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                    <?php foreach ($step['approvers'] as $approver): ?>
                      <?php if (isset($approver['staff']) && isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                        <img src="<?php echo asset('uploads/staff/signature/' . $approver['staff']['signature']); ?>" 
                             alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                        <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                        <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                      <?php elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                        <img src="<?php echo asset('uploads/staff/signature/' . $approver['oic_staff']['signature']); ?>" 
                             alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                        <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                        <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                      <?php else: ?>
                        <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
                        <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                        <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php elseif (stripos($divisionName, 'Programs') !== false): ?>
            <?php if (isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
              <?php foreach ($workflow_info['workflow_steps'] as $step): ?>
                <?php if (stripos($step['role'] ?? '', 'Head of Programs') !== false): ?>
                  <?php if (isset($step['approvers']) && count($step['approvers']) > 0): ?>
                    <?php foreach ($step['approvers'] as $approver): ?>
                      <?php if (isset($approver['staff']) && isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                        <img src="<?php echo asset('uploads/staff/signature/' . $approver['staff']['signature']); ?>" 
                             alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                        <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                        <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                      <?php elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                        <img src="<?php echo asset('uploads/staff/signature/' . $approver['oic_staff']['signature']); ?>" 
                             alt="Signature" style="height: 25px; max-width: 80px; object-fit: contain;">
                        <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                      <?php else: ?>
                        <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
                        <br><small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y H:i'); ?></small>
                        <br><small style="color: #999; font-size: 9px;">Hash: <?php echo generateVerificationHash($activity->id, getStaffId($approver)); ?></small>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endif; ?>
      </td>
    </tr>
    
    <!-- FROM: Head of Division -->
    <tr>
      <td><strong style="color: #006633;">FROM:</strong></td>
      <td>Head of Division</td>
      <td></td>
    </tr>
  </table>



  <!-- Subject -->
  <div class="section-title">SUBJECT: <?php echo htmlspecialchars($activity->activity_title ?? 'N/A'); ?></div>

  <!-- Activity Request Remarks -->
  <?php if (isset($activity->activity_request_remarks) && !empty($activity->activity_request_remarks)): ?>
    <div style="margin: 20px 0; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #006633;">
      <div style="font-weight: bold; color: #006633; margin-bottom: 10px;">REQUEST REMARKS:</div>
      <div style="line-height: 1.6; text-align: justify;">
        <?php echo nl2br(htmlspecialchars($activity->activity_request_remarks)); ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Content -->
  <div>
    <p>This memorandum is to inform you of the following activity details:</p>
    
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

    <?php if (isset($internal_participants) && $internal_participants->count() > 0): ?>
    <div class="section-title">Internal Participants</div>
    <table>
      <tr>
        <th>Name</th>
        <th>Division</th>
        <th>Days</th>
      </tr>
      <?php foreach ($internal_participants as $participant): ?>
      <tr>
        <td><?php echo htmlspecialchars($participant->staff->name ?? 'N/A'); ?></td>
        <td><?php echo htmlspecialchars($participant->staff->division->division_name ?? 'N/A'); ?></td>
        <td><?php echo htmlspecialchars($participant->no_of_days ?? 'N/A'); ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

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
  <table>
    <tr>
      <th style="width: 30%;">Position</th>
      <th style="width: 40%;">Name & Title</th>
      <th style="width: 30%;">Signature</th>
    </tr>
    
    <!-- First Approver -->
    <?php if(isset($workflow_info['workflow_steps']) && $workflow_info['workflow_steps']->count() > 0): ?>
      <?php 
      $firstStep = $workflow_info['workflow_steps']->first();
      if(isset($firstStep['approvers']) && count($firstStep['approvers']) > 0):
        $approver = $firstStep['approvers']->first();
        if(isset($approver['staff'])): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($firstStep['role'] ?? 'Endorsed by'); ?></strong></td>
            <td>
              <?php echo htmlspecialchars($approver['staff']['name']); ?>
              <?php if (isset($approver['staff']['job_title'])): ?>
                <br><small style="color: #666;"><?php echo htmlspecialchars($approver['staff']['job_title']); ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php if (isset($approver['staff']['signature']) && !empty($approver['staff']['signature'])): ?>
                <img src="<?php echo asset('uploads/staff/signature/' . $approver['staff']['signature']); ?>" 
                     alt="Signature" style="height: 40px; max-width: 120px; object-fit: contain;">
              <?php else: ?>
                <small style="color: #666; font-style: italic;">Signed: <?php echo htmlspecialchars(getStaffEmail($approver) ?? 'Email not available'); ?></small>
              <?php endif; ?>
            </td>
          </tr>
        <?php elseif(isset($approver['oic_staff'])): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($firstStep['role'] ?? 'Endorsed by'); ?></strong></td>
            <td>
              <?php echo htmlspecialchars($approver['oic_staff']['name']); ?> (OIC)
              <?php if (isset($approver['oic_staff']['job_title'])): ?>
                <br><small style="color: #666;"><?php echo htmlspecialchars($approver['oic_staff']['job_title']); ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php if (isset($approver['oic_staff']['signature']) && !empty($approver['oic_staff']['signature'])): ?>
                <img src="<?php echo asset('uploads/staff/signature/' . $approver['oic_staff']['signature']); ?>" 
                     alt="Signature" style="height: 40px; max-width: 120px; object-fit: contain;">
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
      <td><strong>Division Head</strong></td>
      <td>Head of Division, Africa CDC</td>
      <td><?php echo date('M j, Y | H:i'); ?> EAST</td>
    </tr>
  </table>
</body>
</html>
