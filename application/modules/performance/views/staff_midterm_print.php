<?php
// performance/views/staff_midterm_print.php
?>
<html>

<head>
  <style>
    body {
      font-family: serif;
      font-size: 12px;
      margin: 10px;
    }

    .header img {
      width: 150px;
    }

    .section-title {
      font-size: 14px;
      font-weight: bold;
      margin-top: 20px;
      border-bottom: 1px solid #ccc;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 15px;
    }

    td,
    th {
      border: 1px solid #ccc;
      padding: 3px;
      text-align: left;
    }

    .no-border td {
      border: none;
    }

    .objective-table th small {
      display: block;
      font-weight: normal !important;
      font-style: italic !important;
      font-size: 10px;
      color: #555;
    }

    .page-break {
      page-break-before: always;
    }

    small {
      font-weight: normal !important;
    }
  </style>
</head>

<body>
  <div style="width: 100%; text-align: center; padding-bottom: 5px;">
    <div style="width: 100%; padding-bottom: 5px;">
      <div style="width: 100%; padding: 10px 0;">
        <div style="display:flex; justify-content: space-between; align-items: center;">
          <div style="width: 60%; text-align: left; float:left;">
            <img src="<?= base_url('assets/images/AU_CDC_Logo-800.png') ?>" alt="Africa CDC Logo" style="height: 80px;">
          </div>
          <div style="text-align: right; width: 35%; float:right; margin-top:10px;">
            <span style="font-size: 14px; color: #911C39;">Safeguarding Africa's Health</span>
          </div>
        </div>
        <div style="text-align: center; margin-top: 5px;">
          <h3 style="margin: 0; font-weight: bold;">MIDTERM REVIEW</h3>
        </div>
      </div>
    </div>
  </div>

  <!-- Staff Details -->
  <table class="form-table table-bordered">
    <thead>
      <tr style="background-color: #f2f2f2;">
        <th colspan="4" style="text-align: left; font-weight: bold;">A. Staff Details</th>
      </tr>
    </thead>
    <tr>
      <td><b>Name</b></td>
      <td><?= $contract->fname . ' ' . $contract->lname ?></td>
      <td><b>SAP NO</b></td>
      <td><?= $contract->SAPNO ?></td>
    </tr>
    <tr>
      <td><b>Position</b></td>
      <td><?= $contract->job_name ?></td>
      <td><b>In this Position Since</b></td>
      <td><?= $contract->start_date ?></td>
    </tr>
    <tr>
      <td><b>Division/Directorate</b></td>
      <td><?= acdc_division($contract->division_id) ?></td>
      <td><b>Performance Period</b></td>
      <td><?= str_replace('-', ' ', $ppa->performance_period); ?></td>
    </tr>
    <tr>
      <td><b>First Supervisor</b></td>
      <td><?= @staff_name($contract->first_supervisor) ?></td>
      <td><b>Second Supervisor</b></td>
      <td><?= @staff_name($contract->second_supervisor)  ?></td>
    </tr>
  </table>

  <!-- Midterm Objectives -->
  <table class="objective-table">
    <tr style="background-color: #f2f2f2;">
      <td colspan="7">
        <div style="text-align: left; font-weight: bold;">B. Midterm Objectives Review</div>
        <p><i>Review of objectives and progress at midterm.</i></p>
      </td>
    </tr>
    <thead>
      <tr>
        <th>#</th>
        <th>Objective</th>
        <th>Timeline</th>
        <th>Deliverables/KPIs</th>
        <th>Weight</th>
        <th>Self Appraisal</th>
        <th>Appraiser's Rating</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $objectives = [];
      if (!empty($ppa->midterm_objectives)) {
        $objectives = is_string($ppa->midterm_objectives) ? json_decode($ppa->midterm_objectives, true) : (array) $ppa->midterm_objectives;
      }
      $i = 1;
      foreach ($objectives as $obj): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= $obj['objective'] ?? '' ?></td>
          <td><?= $obj['timeline'] ?? '' ?></td>
          <td><?= $obj['indicator'] ?? '' ?></td>
          <td><?= $obj['weight'] ?? '' ?></td>
          <td><?= $obj['self_appraisal'] ?? '' ?></td>
          <td><?= $obj['appraiser_rating'] ?? '' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Competencies (Midterm) -->
  <?php
  // Fetch and group competencies by section/category
  $competencies = Modules::run('performance/get_competencies_by_version');
  $grouped = [];
  foreach ($competencies as $row) {
    $grouped[$row['category']][] = $row;
  }
  $categories = [
    'values' => 'AU Values',
    'core' => 'Core Competencies',
    'functional' => 'Functional Competencies',
    'leadership' => 'Leadership Competencies'
  ];
  $midterm_competency = [];
  if (!empty($ppa->midterm_competency)) {
    if (is_string($ppa->midterm_competency)) {
      $midterm_competency = json_decode($ppa->midterm_competency, true);
    } elseif (is_object($ppa->midterm_competency)) {
      $midterm_competency = (array) $ppa->midterm_competency;
    } elseif (is_array($ppa->midterm_competency)) {
      $midterm_competency = $ppa->midterm_competency;
    }
  }
  ?>

  <table width="100%" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
    <thead>
      <tr style="background-color: #f2f2f2;">
        <th colspan="3" style="text-align: left; font-weight: bold;">C. Competencies (Midterm)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td colspan="3" style="text-align: left;">
          All staff members shall be rated against <strong>AU Values</strong> and <strong>Core/Functional Competencies</strong>.<br>
          Staff with managerial responsibilities will also be rated on <strong>Leadership Competencies</strong>.
        </td>
      </tr>
      <?php foreach ($categories as $catKey => $catLabel): ?>
        <?php if (isset($grouped[$catKey])): ?>
          <tr style="background-color: #f9f9f9; font-weight: bold;">
            <td colspan="3"><?= $catLabel ?></td>
          </tr>
          <tr style="background-color: #f9f9f9;">
            <th style="width:45%;">Competency</th>
            <th style="width:45%;">Annotation</th>
            <th style="width:10%;">Rating</th>
          </tr>
          <?php foreach ($grouped[$catKey] as $item):
            $key = 'competency_' . $item['id'];
            $selected = $midterm_competency[$key] ?? '';
          ?>
            <tr>
              <td><strong><?= $item['id'] . '. ' . $item['description'] ?></strong></td>
              <td><small><?= $item['annotation'] ?></small></td>
              <td style="text-align:center; font-weight:bold;">
                <?= htmlspecialchars($selected) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Midterm Comments, Achievements, Training Review -->
  <table>
    <thead>
      <tr style="background-color: #f2f2f2;">
        <th colspan="2" style="text-align: left; font-weight: bold;">D. Midterm Review Comments & Training</th>
      </tr>
    </thead>
    <tr>
      <td><b>Staff Comments</b></td>
      <td><?= $ppa->midterm_comments ?? '' ?></td>
    </tr>
    <tr>
      <td><b>Achievements</b></td>
      <td><?= $ppa->midterm_achievements ?? '' ?></td>
    </tr>
    <tr>
      <td><b>Non-Achievements</b></td>
      <td><?= $ppa->midterm_non_achievements ?? '' ?></td>
    </tr>
    <tr>
      <td><b>Training Review</b></td>
      <td><?= $ppa->midterm_training_review ?? '' ?></td>
    </tr>
    <tr>
      <td><b>Recommended Skills</b></td>
      <td>
        <?php
        $skills_map = [];
        foreach ($skills as $skill) {
          $skills_map[$skill->id] = $skill->skill;
        }
        $selected = [];
        if (!empty($ppa->midterm_recommended_skills)) {
          $selected = is_string($ppa->midterm_recommended_skills) ? json_decode($ppa->midterm_recommended_skills, true) : (array)$ppa->midterm_recommended_skills;
        }
        $skills_list = array_map(fn($id) => $skills_map[$id] ?? '', $selected);
        echo implode(', ', $skills_list);
        ?>
      </td>
    </tr>
  </table>

  <div style="margin-top: 20px;">
    <div class="page-break"></div>
<table width="100%" border="1" cellspacing="0" cellpadding="10" style="border-collapse: collapse; font-size: 10pt;">
  <thead>
    <tr style="background-color: #f2f2f2;">
      <th colspan="2" style="text-align: left; font-weight: bold;">
        E. Staff and Supervisor Sign Off
      </th>
    </tr>
  </thead>
  <tr style="background-color: #f9f9f9; font-weight: bold;">
    <td width="50%" style="text-align: center;">Supervisor</td>
    <td width="50%" style="text-align: center;">Staff</td>
  </tr>
  <tr>
    <td style="padding: 15px;">
      I hereby confirm that I formally discussed the results of this review with the staff member.
      The staff member was given feedback on the progress towards the completion of their performance objectives.
      We discussed areas requiring performance improvement, where applicable.
    </td>
    <td style="padding: 15px;">
      I hereby confirm that I formally discussed the results of this review with my supervisor.
    </td>
  </tr>
      <tr>
      <!-- Supervisor Signature -->
      <td style="padding: 25px; text-align: center; vertical-align: top;">
        <?php
          $supervisor = staff_details($ppa->midterm_supervisor_1);
          $sup_signed_at = get_last_ppa_approval_action_midterm($ppa->entry_id, $ppa->midterm_supervisor_1)->created_at ?? $ppa->created_at;
          $sup_hash = substr(md5(sha1($ppa->midterm_supervisor_1 . $sup_signed_at)), 0, 15);
        ?>
            SIGNED BY
          </div>
          
          <!-- Signature Area -->
          <div style="margin-top: 25px; margin-bottom: 20px; padding:45px;">
            <?php if (!empty($supervisor->signature)): ?>
              <img src="<?= base_url('uploads/staff/signature/' . $supervisor->signature) ?>" 
                   style="max-width: 180px; max-height: 70px; object-fit: contain; display: block; margin: 0 auto;  padding:45px;">
            <?php else: ?>
              <div style="border-bottom: 2px solid #ccc; width: 200px; height: 60px; margin: 0 auto; display: flex; align-items: center;  padding:45px; justify-content: center;">
                <span style="color: #999; font-style: italic; font-size: 11px;"><?= $supervisor->work_email; ?></span>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- Signature Metadata -->
          <div style="text-align: center; margin-top: 15px;">
            <div style="font-weight: bold; color: #333; font-size: 13px; margin-bottom: 5px;">
              <?= $supervisor->fname . ' ' . $supervisor->lname ?>
            </div>
            <div style="color: #666; font-size: 11px; margin-bottom: 8px;">
              <?= $supervisor->position ?? 'Supervisor' ?>
            </div>
            <div style="color: #888; font-size: 10px; margin-bottom: 5px;">
              <?= date('M j, Y | H:i', strtotime($sup_signed_at)) ?> EAST
            </div>
            <div style="color: #aaa; font-size: 9px; font-family: monospace;">
              ID: <?= strtoupper($sup_hash) ?>
            </div>
          </div>
        </div>
      </td>

      <!-- Staff Signature -->
      <td style="padding: 25px; text-align: center; vertical-align: top;">
        <?php
          $staff = staff_details($ppa->staff_id);
          $staff_signed_at = $ppa->midterm_created_at;
          $staff_hash = substr(md5(sha1($ppa->staff_id . $staff_signed_at)), 0, 15);
        ?>

            SIGNED BY
          </div>
          
          <!-- Signature Area -->
          <div style="margin-top: 25px; margin-bottom: 20px; padding:45px;">
            <?php if (!empty($staff->signature)): ?>
              <img src="<?= base_url('uploads/staff/signature/' . $staff->signature) ?>" 
                   style="max-width: 180px; max-height: 70px; object-fit: contain; display: block; margin: 0 auto;">
            <?php else: ?>
  
                <span style="color: #999; font-style: italic; font-size: 11px;"><?= $staff->work_email; ?></span>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- Signature Metadata -->
          <div style="text-align: center; margin-top: 15px;">
            <div style="font-weight: bold; color: #333; font-size: 13px; margin-bottom: 5px;">
              <?= $staff->fname . ' ' . $staff->lname ?>
            </div>
            <div style="color: #666; font-size: 11px;">
              <?= 'Staff' ?>
            </div>
            <div style="color: #888; font-size: 10px; ">
              <?= date('M j, Y | H:i', strtotime($staff_signed_at)) ?> EAST
            </div>
            <div style="color: #aaa; font-size: 9px; font-family: monospace;">
              ID: <?= strtoupper($staff_hash) ?>
            </div>
          </div>
        </div>
      </td>
    </tr>
</table>

  </div>
  <?php if ($this->uri->segment(7) == 1) { ?>
    <!-- Approval Trail -->
    <div class="page-break"></div>
    <div class="section-title">F. Approval Trail</div>
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Role</th>
          <th>Action</th>
          <th>Date</th>
          <th>Comment</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($approval_trail as $log):
          $logged = Modules::run('auth/contract_info', $log->staff_id);
          if ($log->staff_id == $ppa->staff_id) $role = 'Staff';
          elseif ($log->staff_id == $ppa->supervisor_id) $role = 'First Supervisor';
          elseif ($ppa->supervisor2_id && $log->staff_id == $ppa->supervisor2_id) $role = 'Second Supervisor';
          else $role = 'Other';
        ?>
          <tr>
            <td><?= $logged->fname . ' ' . $logged->lname ?></td>
            <td><?= $role ?></td>
            <td><?= $log->action ?></td>
            <td><?= date('d M Y H:i', strtotime($log->created_at)) ?></td>
            <td><?= $log->comments ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php } ?>
</body>

</html>