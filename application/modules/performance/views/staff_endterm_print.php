<?php
// performance/views/staff_endterm_print.php
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
          <h3 style="margin: 0; font-weight: bold; font-size: 18px; color: #100f0f; letter-spacing: 0.5px;">ENDTERM REVIEW</h3>
        </div>
      </div>
    </div>
  </div>

  <!-- Staff Details -->
  <table class="form-table table-bordered">
    <thead>
      <tr style="background-color: #f9fafb;">
        <th colspan="4" style="text-align: left; font-weight: 600; color: #0f172a; padding: 12px;">A. Staff Details</th>
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

  <!-- Endterm Objectives -->
  <table class="objective-table">
    <tr style="background-color: #f9fafb;">
      <td colspan="7" style="padding: 12px;">
        <div style="text-align: left; font-weight: 600; color: #0f172a; margin-bottom: 6px;">B. Endterm Objectives Review</div>
        <p style="margin: 0; font-style: italic; color: #64748b; font-size: 12px;">Review of objectives and progress at endterm.</p>
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
      // First try endterm objectives
      if (!empty($ppa->endterm_objectives)) {
        $objectives = is_string($ppa->endterm_objectives) ? json_decode($ppa->endterm_objectives, true) : (array) $ppa->endterm_objectives;
      }
      // If endterm objectives are empty, fallback to midterm objectives
      if (empty($objectives) && !empty($ppa->midterm_objectives)) {
        $objectives = is_string($ppa->midterm_objectives) ? json_decode($ppa->midterm_objectives, true) : (array) $ppa->midterm_objectives;
      }
      // If still empty, fallback to original PPA objectives
      if (empty($objectives) && !empty($ppa->objectives)) {
        $objectives = is_string($ppa->objectives) ? json_decode($ppa->objectives, true) : (array) $ppa->objectives;
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

  <!-- Appraiser's Comments (Endterm) -->
  <table class="form-table table-bordered" style="margin-bottom: 15px;">
    <thead>
      <tr style="background-color: #f9fafb;">
        <th colspan="2" style="text-align: left; font-weight: 600; color: #0f172a; padding: 12px;">C. Appraiser's Comments</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="width: 30%; font-weight: bold; vertical-align: top;">
          1. What has been achieved in relation to the Performance Objectives?
        </td>
        <td style="width: 70%;">
          <?= nl2br(htmlspecialchars(trim($ppa->endterm_achievements ?? ''))) ?>
        </td>
      </tr>
      <tr>
        <td style="width: 30%; font-weight: bold; vertical-align: top;">
          2. Specify non-achievements in relation to Performance Objectives
        </td>
        <td style="width: 70%;">
          <?= nl2br(htmlspecialchars(trim($ppa->endterm_non_achievements ?? ''))) ?>
        </td>
      </tr>
    </tbody>
  </table>

  <!-- Competencies (Endterm) -->
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
  // First try endterm competencies, then fallback to midterm competencies if empty
  $endterm_competency = [];
  if (!empty($ppa->endterm_competency)) {
    if (is_string($ppa->endterm_competency)) {
      $endterm_competency = json_decode($ppa->endterm_competency, true);
    } elseif (is_object($ppa->endterm_competency)) {
      $endterm_competency = (array) $ppa->endterm_competency;
    } elseif (is_array($ppa->endterm_competency)) {
      $endterm_competency = $ppa->endterm_competency;
    }
  }
  
  // If endterm competencies are empty, fallback to midterm competencies
  if (empty($endterm_competency) && !empty($ppa->midterm_competency)) {
    if (is_string($ppa->midterm_competency)) {
      $endterm_competency = json_decode($ppa->midterm_competency, true);
    } elseif (is_object($ppa->midterm_competency)) {
      $endterm_competency = (array) $ppa->midterm_competency;
    } elseif (is_array($ppa->midterm_competency)) {
      $endterm_competency = $ppa->midterm_competency;
    }
  }
  
  // Ensure it's an array
  if (!is_array($endterm_competency)) {
    $endterm_competency = [];
  }
  ?>

  <table width="100%" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
    <thead>
      <tr style="background-color: #f9fafb;">
        <th colspan="3" style="text-align: left; font-weight: 600; color: #0f172a; padding: 12px;">D. Competencies (Endterm)</th>
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
            $selected = $endterm_competency[$key] ?? '';
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

  <!-- Endterm Comments, Achievements, Training Review -->
  <table>
    <thead>
      <tr style="background-color: #f9fafb;">
        <th colspan="2" style="text-align: left; font-weight: 600; color: #0f172a; padding: 12px;">E. Endterm Review Comments & Training</th>
      </tr>
    </thead>
    <tr>
      <td><b>Staff Comments</b></td>
      <td><?= $ppa->endterm_comments ?? '' ?></td>
    </tr>
    <tr>
      <td><b>Achievements</b></td>
      <td><?= trim($ppa->endterm_achievements ?? '') ?></td>
    </tr>
    <tr>
      <td><b>Non-Achievements</b></td>
      <td><?= trim($ppa->endterm_non_achievements ?? '') ?></td>
    </tr>
    <tr>
      <td><b>Training Review</b></td>
      <td><?= $ppa->endterm_training_review ?? '' ?></td>
    </tr>
  </table>

  <div style="margin-top: 20px;">
    <div class="page-break"></div>
    
    <?php
    // Get supervisor and rating data
    $supervisor = staff_details($ppa->endterm_supervisor_1);
    $endterm_approval = $this->db->query("SELECT * FROM ppa_approval_trail_end_term WHERE entry_id = ? AND staff_id = ? ORDER BY id DESC LIMIT 1", [$ppa->entry_id, $ppa->endterm_supervisor_1])->row();
    $sup_signed_at = $endterm_approval->created_at ?? $ppa->endterm_created_at ?? $ppa->created_at;
    $sup_hash = substr(md5(sha1($ppa->endterm_supervisor_1 . $sup_signed_at)), 0, 15);
    
    // Calculate overall rating
    $objectives = [];
    if (!empty($ppa->endterm_objectives)) {
      $objectives = is_string($ppa->endterm_objectives) ? json_decode($ppa->endterm_objectives, true) : (array) $ppa->endterm_objectives;
    } elseif (!empty($ppa->midterm_objectives)) {
      $objectives = is_string($ppa->midterm_objectives) ? json_decode($ppa->midterm_objectives, true) : (array) $ppa->midterm_objectives;
    }
    $rating_data = calculate_endterm_overall_rating($objectives);
    
    // Determine selected rating
    $selected_rating = '';
    if ($rating_data['score'] >= 80) {
      $selected_rating = 'outstanding';
    } elseif ($rating_data['score'] >= 51) {
      $selected_rating = 'satisfactory';
    } elseif ($rating_data['score'] > 0) {
      $selected_rating = 'poor';
    } else {
      $selected_rating = 'not_rated';
    }
    
    // Get staff data
    $staff = staff_details($ppa->staff_id);
    $staff_consent_trail = $this->db->query("SELECT * FROM ppa_approval_trail_end_term WHERE entry_id = ? AND staff_id = ? AND action = 'Employee Consent' ORDER BY id DESC LIMIT 1", [$ppa->entry_id, $ppa->staff_id])->row();
    $staff_signed_at = $staff_consent_trail->created_at ?? $ppa->endterm_staff_consent_at ?? $ppa->endterm_created_at ?? $ppa->created_at;
    $staff_hash = substr(md5(sha1($ppa->staff_id . $staff_signed_at)), 0, 15);
    
    // Get second supervisor data
    $second_supervisor = null;
    $second_sup_signed_at = null;
    $second_sup_hash = null;
    if (!empty($ppa->endterm_supervisor_2)) {
      $second_supervisor = staff_details($ppa->endterm_supervisor_2);
      $second_sup_approval = $this->db->query("SELECT * FROM ppa_approval_trail_end_term WHERE entry_id = ? AND staff_id = ? ORDER BY id DESC LIMIT 1", [$ppa->entry_id, $ppa->endterm_supervisor_2])->row();
      $second_sup_signed_at = $second_sup_approval->created_at ?? null;
      if ($second_sup_signed_at) {
        $second_sup_hash = substr(md5(sha1($ppa->endterm_supervisor_2 . $second_sup_signed_at)), 0, 15);
      }
    }
    ?>
    
    <!-- F. Overall Rating and Supervisor Signoff -->
    <table width="100%" border="1" cellspacing="0" cellpadding="10" style="border-collapse: collapse; margin-bottom: 20px;">
      <thead>
        <tr style="background-color: #f9fafb;">
          <th colspan="2" style="text-align: left; font-weight: 600; color: #0f172a; padding: 12px;">
            F. Overall Rating and Supervisor Signoff
          </th>
        </tr>
      </thead>
      <tr>
        <td style="width: 40%; padding: 15px; vertical-align: top;">
          <div style="font-weight: bold; margin-bottom: 10px;">Rating [The overall rating is based on performance against Performance Objectives and Assessment of Competences]</div>
          
          <!-- Overall Rating Display -->
          <?php if (!empty($objectives) && $rating_data['score'] > 0): ?>
          <div style="margin-bottom: 15px; padding: 10px;  border-radius: 5px;">
            <div style="font-weight: bold; font-size: 13px; margin-bottom: 5px; color: #333;">
              Overall Rating: <?= number_format($rating_data['score'], 2) ?>%
            </div>
            <div style="font-size: 12px; color: #555; margin-top: 5px; font-weight: 600;">
              <?= htmlspecialchars($rating_data['label']) ?>
            </div>
          </div>
          <?php endif; ?>
        
        </td>
        <td style="width: 60%; padding: 15px; vertical-align: top;">
          <div style="font-weight: bold; margin-bottom: 10px;">Supervisor Overall Comments</div>
          <div style="min-height: 100px; padding: 10px;">
            <?php
            // Get all supervisor comments from approval trail
            $supervisor_comments = [];
            if (!empty($approval_trail)) {
              foreach ($approval_trail as $trail) {
                if ($trail->staff_id == $ppa->endterm_supervisor_1 && !empty($trail->comments)) {
                  // Filter out acceptance text
                  $comment = trim($trail->comments);
                  $comment = preg_replace('/\s*I hereby confirm that I formally discussed the results of this review with the staff member\.?\s*/i', '', $comment);
                  $comment = trim($comment);
                  if (!empty($comment)) {
                    $supervisor_comments[] = [
                      'comment' => $comment,
                      'date' => $trail->created_at,
                      'action' => $trail->action
                    ];
                  }
                }
              }
            }
            // Comments are stored in approval trail, not in separate fields
            
            if (!empty($supervisor_comments)):
              foreach ($supervisor_comments as $idx => $comment_data):
                if ($idx > 0) echo '<div style="margin-top: 15px;"></div>';
                echo nl2br(htmlspecialchars(trim($comment_data['comment'])));
                echo '<div style="font-size: 9px; color: #999; margin-top: 6px;">';
                echo date('d M Y H:i', strtotime($comment_data['date']));
                echo '</div>';
              endforeach;
            else:
              echo '<em style="color: #999;">No comments</em>';
            endif;
            ?>
          </div>
        </td>
      </tr>
      <tr>
        <td colspan="2" style="padding: 15px;">
          <input type="checkbox" <?= ((int)($ppa->endterm_supervisor1_discussion_confirmed ?? 0) === 1) ? 'checked="checked"' : '' ?> disabled style="width: 15px; height: 15px; margin-right: 5px; vertical-align: middle;">
          I hereby confirm that I formally discussed the results of this review with the staff member.
        </td>
      </tr>
      <tr>
        <td colspan="2" style="padding: 25px; text-align: center;">
          <div style="margin-bottom: 15px; font-weight: bold;">Supervisor Signature</div>
          <div style="margin-top: 20px; margin-bottom: 20px; min-height: 60px;">
            <?php if (!empty($supervisor->signature)): ?>
              <img src="<?= base_url('uploads/staff/signature/' . $supervisor->signature) ?>" 
                   style="max-width: 200px; max-height: 70px; object-fit: contain; display: block; margin: 0 auto;">
            <?php else: ?>
              <div style=" margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                <span style="color: #999; font-style: italic; font-size: 11px;"><?= $supervisor->work_email; ?></span>
              </div>
            <?php endif; ?>
          </div>
          <div style="margin-top: 15px;">
            <div style="font-weight: bold; color: #333; font-size: 13px; margin-bottom: 5px;">
              <?= $supervisor->fname . ' ' . $supervisor->lname ?>
            </div>
            <div style="color: #666; font-size: 11px; margin-bottom: 8px;">
              <?= $supervisor->position ?? 'Supervisor' ?>
            </div>
            <div style="color: #888; font-size: 10px; margin-bottom: 5px;">
              Date: <?= date('M j, Y | H:i', strtotime($sup_signed_at)) ?> EAST
            </div>
            <div style="color: #aaa; font-size: 9px; font-family: monospace;">
              SIGNATURE ID: <?= strtoupper($sup_hash) ?>
            </div>
          </div>
        </td>
      </tr>
    </table>
    
    <!-- G. Staff Sign Off -->
    <table width="100%" border="1" cellspacing="0" cellpadding="10" style="border-collapse: collapse; margin-bottom: 20px;">
      <thead>
        <tr style="background-color: #f9fafb;">
          <th colspan="2" style="text-align: left; font-weight: 600; color: #0f172a; padding: 12px;">
            G. Staff Sign Off
          </th>
        </tr>
      </thead>
      <tr>
        <td style="width: 40%; padding: 15px; vertical-align: top;">
          <div style="margin-bottom: 10px;">
            <input type="checkbox" <?= ((int)($ppa->endterm_staff_discussion_confirmed ?? 0) === 1) ? 'checked="checked"' : '' ?> disabled style="width: 15px; height: 15px; margin-right: 5px; vertical-align: middle;">
            I hereby confirm that I formally discussed the results of this review with my supervisor.
          </div>
          <div style="margin-top: 15px; margin-bottom: 8px;">
            <input type="radio" name="staff_rating_acceptance" <?= ((int)($ppa->endterm_staff_rating_acceptance ?? -1) === 1) ? 'checked="checked"' : '' ?> disabled style="width: 15px; height: 15px; margin-right: 5px; vertical-align: middle;">
            I accept the overall rating assigned by my supervisor
          </div>
          <div style="margin-bottom: 8px;">
            <input type="radio" name="staff_rating_acceptance" <?= ((int)($ppa->endterm_staff_rating_acceptance ?? -1) === 0) ? 'checked="checked"' : '' ?> disabled style="width: 15px; height: 15px; margin-right: 5px; vertical-align: middle;">
            I reject the overall rating assigned by my supervisor
          </div>
        </td>
        <td style="width: 60%; padding: 15px; vertical-align: top;">
          <div style="font-weight: bold; margin-bottom: 10px;">Staff Comments</div>
          <div style="padding: 10px;">
            <?php
            // Get all staff comments from approval trail
            $staff_comments = [];
            if (!empty($approval_trail)) {
              foreach ($approval_trail as $trail) {
                if ($trail->staff_id == $ppa->staff_id && !empty($trail->comments)) {
                  // Filter out acceptance text
                  $comment = trim($trail->comments);
                  $comment = preg_replace('/\s*Staff confirmed discussion and (accepted|rejected) the overall rating\.?\s*/i', '', $comment);
                  $comment = preg_replace('/\s*I hereby confirm that I formally discussed the results of this review with my supervisor\.?\s*/i', '', $comment);
                  $comment = trim($comment);
                  if (!empty($comment)) {
                    $staff_comments[] = [
                      'comment' => $comment,
                      'date' => $trail->created_at,
                      'action' => $trail->action
                    ];
                  }
                }
              }
            }
            // Also include the endterm_comments field if it exists (from initial submission)
            if (!empty($ppa->endterm_comments)) {
              // Check if this comment is already in the trail to avoid duplicates
              $already_in_trail = false;
              foreach ($staff_comments as $existing) {
                if (trim($existing['comment']) === trim($ppa->endterm_comments)) {
                  $already_in_trail = true;
                  break;
                }
              }
              if (!$already_in_trail) {
                $staff_comments[] = [
                  'comment' => $ppa->endterm_comments,
                  'date' => $staff_signed_at,
                  'action' => 'Submission Comments'
                ];
              }
            }
            
            if (!empty($staff_comments)):
              foreach ($staff_comments as $idx => $comment_data):
                if ($idx > 0) echo '<div style="margin-top: 15px;"></div>';
                echo nl2br(htmlspecialchars(trim($comment_data['comment'])));
                echo '<div style="font-size: 9px; color: #999; margin-top: 5px;">';
                echo date('d M Y H:i', strtotime($comment_data['date']));
                echo '</div>';
              endforeach;
            else:
              echo '<em style="color: #999;">No comments</em>';
            endif;
            ?>
          </div>
        </td>
      </tr>
      <tr>
        <td colspan="2" style="padding: 25px; text-align: center;">
          <div style="margin-bottom: 15px; font-weight: bold;">Staff Signature</div>
          <div style="margin-top: 20px; margin-bottom: 20px; min-height: 60px;">
            <?php if (!empty($staff->signature)): ?>
              <img src="<?= base_url('uploads/staff/signature/' . $staff->signature) ?>" 
                   style="max-width: 200px; max-height: 70px; object-fit: contain; display: block; margin: 0 auto;">
            <?php else: ?>
              <div style="border-bottom: 2px solid #ccc; width: 250px; height: 60px; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                <span style="color: #999; font-style: italic; font-size: 11px;"><?= $staff->work_email; ?></span>
              </div>
            <?php endif; ?>
          </div>
          <div style="margin-top: 15px;">
            <div style="font-weight: bold; color: #333; font-size: 13px; margin-bottom: 5px;">
              <?= $staff->fname . ' ' . $staff->lname ?>
            </div>
            <div style="color: #666; font-size: 11px; margin-bottom: 8px;">
              Staff
            </div>
            <div style="color: #888; font-size: 10px; margin-bottom: 5px;">
              Date: <?= date('M j, Y | H:i', strtotime($staff_signed_at)) ?> EAST
            </div>
            <div style="color: #aaa; font-size: 9px; font-family: monospace;">
              SIGNATURE ID: <?= strtoupper($staff_hash) ?>
            </div>
          </div>
        </td>
      </tr>
    </table>
    
    <!-- H. Second Supervisor Sign Off -->
    <?php if (!empty($ppa->endterm_supervisor_2)): ?>
    <table width="100%" border="1" cellspacing="0" cellpadding="10" style="border-collapse: collapse; margin-bottom: 20px;">
      <thead>
        <tr style="background-color: #f9fafb;">
          <th colspan="2" style="text-align: left; font-weight: 600; color: #0f172a; padding: 12px;">
            H. Second Supervisor Sign Off
          </th>
        </tr>
      </thead>
      <tr>
        <td style="width: 40%; padding: 15px; vertical-align: top;">
          <div style="margin-top: 15px; margin-bottom: 8px;">
            <input type="radio" name="supervisor2_agreement" <?= ((int)($ppa->endterm_supervisor2_agreement ?? -1) === 1) ? 'checked="checked"' : '' ?> disabled style="width: 15px; height: 15px; margin-right: 5px; vertical-align: middle;">
            I agree with the evaluation of the supervisor
          </div>
          <div style="margin-bottom: 8px;">
            <input type="radio" name="supervisor2_agreement" <?= ((int)($ppa->endterm_supervisor2_agreement ?? -1) === 0) ? 'checked="checked"' : '' ?> disabled style="width: 15px; height: 15px; margin-right: 5px; vertical-align: middle;">
            I disagree with the evaluation of the supervisor
          </div>
        </td>
        <td style="width: 60%; padding: 15px; vertical-align: top;">
          <div style="font-weight: bold; margin-bottom: 10px;">Second Supervisor Comments</div>
          <div style="min-height: 100px;padding: 10px;">
            <?php
            // Get all second supervisor comments from approval trail
            $second_supervisor_comments = [];
            if (!empty($approval_trail) && !empty($ppa->endterm_supervisor_2)) {
              foreach ($approval_trail as $trail) {
                if ($trail->staff_id == $ppa->endterm_supervisor_2 && !empty($trail->comments)) {
                  // Filter out acceptance text
                  $comment = trim($trail->comments);
                  $comment = preg_replace('/\s*Second supervisor (agrees|disagrees) with the evaluation\.?\s*/i', '', $comment);
                  $comment = trim($comment);
                  if (!empty($comment)) {
                    $second_supervisor_comments[] = [
                      'comment' => $comment,
                      'date' => $trail->created_at,
                      'action' => $trail->action
                    ];
                  }
                }
              }
            }
            // Comments are stored in approval trail, not in separate fields
            
            if (!empty($second_supervisor_comments)):
              foreach ($second_supervisor_comments as $idx => $comment_data):
                if ($idx > 0) echo '<div style="margin-top: 15px;"></div>';
                echo nl2br(htmlspecialchars(trim($comment_data['comment'])));
                echo '<div style="font-size: 9px; color: #999; margin-top: 5px;">';
                echo date('d M Y H:i', strtotime($comment_data['date']));
                echo '</div>';
              endforeach;
            else:
              echo '<em style="color: #999;">No comments</em>';
            endif;
            ?>
          </div>
        </td>
      </tr>
      <tr>
        <td colspan="2" style="padding: 25px; text-align: center;">
          <div style="margin-bottom: 15px; font-weight: bold;">Second Supervisor Signature</div>
          <div style="margin-top: 20px; margin-bottom: 20px; min-height: 60px;">
            <?php if (!empty($second_supervisor->signature)): ?>
              <img src="<?= base_url('uploads/staff/signature/' . $second_supervisor->signature) ?>" 
                   style="max-width: 200px; max-height: 70px; object-fit: contain; display: block; margin: 0 auto;">
            <?php else: ?>
              <div style="border-bottom: 2px solid #ccc; width: 250px; height: 60px; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                <span style="color: #999; font-style: italic; font-size: 11px;"><?= $second_supervisor->work_email ?? 'N/A'; ?></span>
              </div>
            <?php endif; ?>
          </div>
          <div style="margin-top: 15px;">
            <div style="font-weight: bold; color: #333; font-size: 13px; margin-bottom: 5px;">
              <?= $second_supervisor->fname . ' ' . $second_supervisor->lname ?? 'N/A' ?>
            </div>
            <div style="color: #666; font-size: 11px; margin-bottom: 8px;">
              <?= $second_supervisor->position ?? 'Second Supervisor' ?>
            </div>
            <?php if ($second_sup_signed_at): ?>
            <div style="color: #888; font-size: 10px; margin-bottom: 5px;">
              Date: <?= date('M j, Y | H:i', strtotime($second_sup_signed_at)) ?> EAST
            </div>
            <div style="color: #aaa; font-size: 9px; font-family: monospace;">
              SIGNATURE ID: <?= strtoupper($second_sup_hash) ?>
            </div>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    </table>
    <?php endif; ?>

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
          elseif ($log->staff_id == $ppa->endterm_supervisor_1) $role = 'First Supervisor';
          elseif ($ppa->endterm_supervisor_2 && $log->staff_id == $ppa->endterm_supervisor_2) $role = 'Second Supervisor';
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

