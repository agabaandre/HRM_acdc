<?php
// performance/views/staff_midterm_print.php
?>
<html>

<head>
  <style>
    * { box-sizing: border-box; }
    html, body { 
      margin: 0; 
      padding: 0; 
      color: #0f172a; 
    }
    
    body {
      font-size: 14px;
      font-family: "freesans", arial, sans-serif;
      background: #FFFFFF;
      margin: 40px;
      line-height: 1.8 !important;
      letter-spacing: 0.02em;
      word-spacing: 0.08em;
      margin-bottom: 1.2em;
    }

    .header img {
      width: 150px;
    }

    .section-title {
      font-size: 16px;
      font-weight: bold;
      margin-top: 25px;
      margin-bottom: 12px;
      border-bottom: 2px solid #e2e8f0;
      padding-bottom: 8px;
      color: #100f0f;
      letter-spacing: 0.3px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
      background: #fff;
    }

    td,
    th {
      border: 1px solid #e2e8f0;
      padding: 10px;
      text-align: left;
      vertical-align: top;
    }

    th {
      background-color: #f9fafb;
      font-weight: 600;
      color: #0f172a;
    }

    .no-border td {
      border: none;
    }

    .objective-table th small {
      display: block;
      font-weight: normal !important;
      font-style: italic !important;
      font-size: 12px;
      color: #64748b;
      margin-top: 4px;
    }

    .page-break {
      page-break-before: always;
    }

    small {
      font-weight: normal !important;
      font-size: 12px;
      color: #64748b;
    }

    b, strong {
      font-weight: 600;
      color: #0f172a;
    }

    .muted {
      color: #64748b;
      font-size: 12px;
    }
  </style>
</head>

<body>
  <div style="width: 100%; text-align: center; padding-bottom: 15px; margin-bottom: 20px;">
    <div style="width: 100%; padding-bottom: 10px;">
      <div style="width: 100%; padding: 15px 0;">
        <div style="display:flex; justify-content: space-between; align-items: center;">
          <div style="width: 60%; text-align: left; float:left;">
            <img src="<?= base_url('assets/images/AU_CDC_Logo-800.png') ?>" alt="Africa CDC Logo" style="height: 80px;">
          </div>
          <div style="text-align: right; width: 35%; float:right; margin-top:10px;">
            <span style="font-size: 14px; color: #911C39; font-weight: 500; letter-spacing: 0.3px;">Safeguarding Africa's Health</span>
          </div>
        </div>
        <div style="text-align: center; margin-top: 12px;">
          <h3 style="margin: 0; font-weight: bold; font-size: 18px; color: #100f0f; letter-spacing: 0.5px;">MIDTERM REVIEW</h3>
        </div>
      </div>
    </div>
  </div>

  <!-- Staff Details -->
  <table class="form-table table-bordered">
    <thead>
      <tr style="background-color: #f9fafb;">
        <th colspan="4" style="text-align: left; font-weight: bold; color: #0f172a; padding: 12px;"><strong>A. Staff Details</strong></th>
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
    <tr style="background-color: #f9fafb;">
      <td colspan="7" style="padding: 12px;">
        <div style="text-align: left; font-weight: bold; color: #0f172a; margin-bottom: 6px;"><strong>B. Midterm Objectives Review</strong></div>
        <p style="margin: 0; font-style: italic; color: #64748b; font-size: 12px;">Review of objectives and progress at midterm.</p>
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

  <!-- Appraiser's Comments (Midterm) -->
  <table class="form-table table-bordered" style="margin-bottom: 15px;">
    <thead>
      <tr style="background-color: #f9fafb;">
        <th colspan="2" style="text-align: left; font-weight: bold; color: #0f172a; padding: 12px;"><strong>C. Appraiser's Comments</strong></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="width: 30%; font-weight: bold; vertical-align: top;">
          1. What has been achieved in relation to the Performance Objectives?
        </td>
        <td style="width: 70%;">
          <?= nl2br(htmlspecialchars(trim($ppa->midterm_achievements ?? ''))) ?>
        </td>
      </tr>
      <tr>
        <td style="width: 30%; font-weight: bold; vertical-align: top;">
          2. Specify non-achievements in relation to Performance Objectives
        </td>
        <td style="width: 70%;">
          <?= nl2br(htmlspecialchars(trim($ppa->midterm_non_achievements ?? ''))) ?>
        </td>
      </tr>
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
      <tr style="background-color: #f9fafb;">
        <th colspan="3" style="text-align: left; font-weight: bold; color: #0f172a; padding: 12px;"><strong>D. Competencies (Midterm)</strong></th>
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

  <!-- Personal Development Plan – Progress Review -->
  <table>
    <thead>
      <tr style="background-color: #f9fafb;">
        <th colspan="2" style="text-align: left; font-weight: bold; color: #0f172a; padding: 12px;"><strong>E. Personal Development Plan – Progress Review</strong></th>
      </tr>
    </thead>
    <tbody>
    <tr>
        <td style="width: 30%; font-weight: bold; vertical-align: top;">
          1. Comments on progress made against employee's Personal Development Plan (PDP).
        </td>
        <td style="width: 70%;">
          <?= nl2br(htmlspecialchars($ppa->midterm_training_review ?? '')) ?>
        </td>
    </tr>
    <tr>
        <td style="width: 30%; font-weight: bold; vertical-align: top;">
          2. Is additional training recommended for this staff member?
        </td>
        <td style="width: 70%;">
          <?php
          $mid_skills = [];
          if (!empty($ppa->midterm_recommended_skills)) {
            $decoded = is_string($ppa->midterm_recommended_skills)
                ? json_decode($ppa->midterm_recommended_skills, true)
                : (is_array($ppa->midterm_recommended_skills) ? $ppa->midterm_recommended_skills : []);
            $mid_skills = is_array($decoded) ? $decoded : [];
          }
          $isMidtermRecommended = !empty($mid_skills) || ($ppa->midterm_training_recommended ?? '') === 'Yes';
          ?>
          [<?= $isMidtermRecommended ? 'X' : ' ' ?>] Yes &nbsp;&nbsp; [<?= !$isMidtermRecommended ? 'X' : ' ' ?>] No
        </td>
    </tr>
      <?php if ($isMidtermRecommended): ?>
    <tr>
        <td style="width: 30%; font-weight: bold; vertical-align: top;">
          3. If yes, in what subject/ skill area(s) is the training recommended for this staff member?
        </td>
        <td style="width: 70%;">
        <?php
          if (!empty($ppa->midterm_recommended_skills_text)) {
            echo nl2br(htmlspecialchars($ppa->midterm_recommended_skills_text));
          } else {
        $skills_map = [];
        foreach ($skills as $skill) {
          $skills_map[$skill->id] = $skill->skill;
        }
        $selected = [];
        if (!empty($ppa->midterm_recommended_skills)) {
          $selected = is_string($ppa->midterm_recommended_skills) ? json_decode($ppa->midterm_recommended_skills, true) : (array)$ppa->midterm_recommended_skills;
        }
        $skills_list = array_map(fn($id) => $skills_map[$id] ?? '', $selected);
            echo !empty($skills_list) ? implode(', ', $skills_list) : '';
          }
        ?>
      </td>
    </tr>
      <tr>
        <td style="width: 30%; font-weight: bold; vertical-align: top;">
          4. How will the recommended training(s) contribute to the staff member's development and the department's work?
        </td>
        <td style="width: 70%;">
          <?= nl2br(htmlspecialchars($ppa->midterm_training_contributions ?? '')) ?>
        </td>
      </tr>
      <tr>
        <td style="width: 30%; font-weight: bold; vertical-align: top;">
          5. Selection of courses in line with training needs.
        </td>
        <td style="width: 70%;">
          <div style="margin-bottom: 15px;">
            <strong>5.1</strong> With reference to the current AUC Learning and Development (L&D) Catalogue, please list the recommended course(s) for this staff member:
            <div style="margin-left: 20px; margin-top: 5px;">
              <div style="margin-bottom: 5px;">
                <strong>5.1.1</strong><br>
                <?= nl2br(htmlspecialchars($ppa->midterm_recommended_trainings_1 ?? '')) ?>
              </div>
              <div style="margin-bottom: 5px;">
                <strong>5.1.2</strong><br>
                <?= nl2br(htmlspecialchars($ppa->midterm_recommended_trainings_2 ?? '')) ?>
              </div>
              <?php if (!empty($ppa->midterm_recommended_trainings)): ?>
              <div style="margin-top: 10px;">
                <?= nl2br(htmlspecialchars($ppa->midterm_recommended_trainings)) ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div>
            <strong>5.2</strong> Where applicable, please provide details of highly recommendable course(s) for this staff member that are not listed in the AUC L&D Catalogue.<br>
            <?= nl2br(htmlspecialchars($ppa->midterm_recommended_trainings_details ?? '')) ?>
          </div>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div style="margin-top: 20px; page-break-before: always;">
<table width="100%" border="1" cellspacing="0" cellpadding="10" style="border-collapse: collapse;">
  <thead>
    <tr style="background-color: #f9fafb;">
      <th colspan="2" style="text-align: left; font-weight: bold; color: #0f172a; padding: 12px;">
        <strong>F. Staff and Supervisor Sign Off</strong>
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
             SIGNATURE ID: <?= strtoupper($sup_hash) ?>
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
            SIGNATURE ID: <?= strtoupper($staff_hash) ?>
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
    <div class="section-title">G. Approval Trail</div>
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