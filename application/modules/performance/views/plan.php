<?php 
$session = $this->session->userdata('user');
$staff_id = $session->staff_id;
$contract = Modules::run('auth/contract_info', $staff_id);
$readonly = isset($ppa) && $ppa->draft_status == 0 ? 'readonly disabled' : '';

$selected_skills = is_string($ppa->required_skills ?? null) ? json_decode($ppa->required_skills, true) : ($ppa->required_skills ?? []);
$objectives_raw = $ppa->objectives ?? [];

if (is_string($objectives_raw)) {
    $decoded = json_decode($objectives_raw, true);
} elseif (is_object($objectives_raw)) {
    $decoded = json_decode(json_encode($objectives_raw), true);
} elseif (is_array($objectives_raw)) {
    $decoded = $objectives_raw;
} else {
    $decoded = [];
}

$objectives = [];
foreach ($decoded as $item) {
    $objectives[] = [
        'objective' => $item['objective'] ?? '',
        'timeline' => $item['timeline'] ?? '',
        'indicator' => $item['indicator'] ?? '',
        'weight' => $item['weight'] ?? ''
    ];
}
?>

<style>
input[type="text"],
input[type="number"] {
  border: none !important; 
  padding: 18px;
  box-shadow: none !important;
  background-color: transparent;
}
.is-invalid {
  border: 1px solid red !important;
}

  .form-table { width: 100%; border-collapse: collapse; }
  .form-table td { padding: 8px; vertical-align: top; }
  .form-table label { font-weight: bold; }
  .objective-table th, .objective-table td { text-align: left; padding: 8px; border: 1px solid #ccc; }
</style>



<?php echo form_open_multipart(base_url('performance/save_ppa'), ['id' => 'staff_ppa']); ?>


<h4>A. Staff Details</h4>
<table class="form-table table-bordered">
  <tr>
    <td><label>Name</label></td>
    <td><input type="text" name="name" class="form-control" value="<?= $session->name ?>" readonly></td>
    <td><label>SAP NO</label></td>
    <td><input type="text" class="form-control" value="<?= $contract->SAPNO ?>" readonly></td>
  </tr>
  <tr>
    <td><label>Position</label></td>
    <td><input type="text" class="form-control" value="<?= $contract->job_name ?>" readonly></td>
    <td><label>In this Position Since</label></td>
    <td><input type="text" class="form-control" value="<?= $contract->start_date ?>" readonly></td>
  </tr>
  <tr>
    <td><label>Division/Directorate</label></td>
    <td><input type="text" class="form-control" value="<?= acdc_division($contract->division_id) ?>" readonly></td>
    <td><label>Performance Period</label></td>
    <td><input type="text" class="form-control" name="performance-period" value="<?= current_period(); ?>" readonly></td>
  </tr>
  <tr>
    <td><label>First Supervisor</label></td>
    <td colspan="1">
      <input type="text" class="form-control" name="supervisor_name"
        value="<?= staff_name(get_supervisor(current_contract($staff_id))->first_supervisor) ?>" readonly>
      <input type="hidden" name="supervisor_id"
        value="<?= get_supervisor(current_contract($staff_id))->first_supervisor ?>">
    </td>
    <td><label>Second Supervisor</label></td>
    <td colspan="">
      <input type="text" class="form-control" name="supervisor2_id"
        value="<?= @staff_name(get_supervisor(current_contract($staff_id))->second_supervisor) ?>" readonly>
        <input type="hidden" name="supervisor2_id"
        value="<?= get_supervisor(current_contract($staff_id))->second_supervisor ?>">
    </td>
  </tr>
</table>

<hr>

<h4>B. Performance Objectives</h4>
<small>Individual objectives should be derived from the Departmental Work Plan. There must be a cascading correlation between the two</small>
<div class="table-responsive"> 
  <table class="table objective-table table-bordered">
    <thead class="table-light">
      <tr>
        <th style="width: 5%;">#</th>
        <th style="width: 20%; white-space: normal;">Objective<br><small class="fw-light d-block text-wrap">Statement of the result that needs to be achieved</small></th>
        <th style="width: 15%; white-space: normal;">Timeline<br><small class="fw-light d-block text-wrap">Timeframe within which the result is to be achieved</small></th>
        <th style="width: 40%; white-space: normal;">Deliverables and KPI’s<br><small class="fw-light d-block text-wrap">Deliverables - the evidence that the result has been achieved; KPI’s give an indication of how well the result was achieved</small></th>
        <th style="width: 10%; white-space: normal;">Weight<br><small class="fw-light d-block text-wrap">The total weight of all objectives should be 100%</small></th>
      </tr>
    </thead>
    <tbody id="objectives-table-body">
    <?php for ($i = 1; $i <= 5; $i++): 
        $val = $objectives[$i - 1] ?? ['objective'=>'', 'timeline'=>'', 'indicator'=>'', 'weight'=>''];
      ?>
        <tr>
          <td><?= $i ?></td>
          <td><textarea name="objectives[<?= $i ?>][objective]" class="form-control objective-input" <?= $readonly ?> required><?= $val['objective'] ?></textarea></td>
          <td><input type="text" name="objectives[<?= $i ?>][timeline]" class="form-control datepicker objective-input" <?= $readonly ?> value="<?= $val['timeline'] ?>" required></td>
          <td><textarea name="objectives[<?= $i ?>][indicator]" class="form-control objective-input" <?= $readonly ?> required><?= $val['indicator'] ?></textarea></td>
          <td><input type="number" name="objectives[<?= $i ?>][weight]" class="form-control objective-input" <?= $readonly ?> value="<?= $val['weight'] ?>" required></td>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>
</div>

<hr>

<h4>C. Personal Development Plan</h4>

<table class="form-table table-bordered" style="width:100%;">
  <tr>
    <td style="width: 30%;"><label class="form-label">Is training recommended for this staff member?</label></td>
    <td>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="training_recommended" id="training_yes" value="Yes" onchange="toggleTrainingSection(true)" <?= $readonly ?> <?= ($ppa->training_recommended ?? '') == 'Yes' ? 'checked' : '' ?>>
        <label class="form-check-label" for="training_yes">Yes</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="training_recommended" id="training_no" value="No" onchange="toggleTrainingSection(false)" <?= $readonly ?> <?= ($ppa->training_recommended ?? '') == 'No' ? 'checked' : '' ?>>
        <label class="form-check-label" for="training_no">No</label>
      </div>
    </td>
  </tr>
</table>

<section class="required_trainings" id="training-section" style="display: <?= ($ppa->training_recommended ?? '') == 'Yes' ? 'block' : 'none' ?>; margin-top: 15px;">
  <table class="form-table table-bordered" style="width:100%;">
    <tr>
      <td style="width: 30%;"><label for="skill-area" class="form-label">If yes, in what subject/ skill area(s) is the training recommended for this staff member?</label></td>
      <td>
        <select class="form-control select2" name="required_skills[]" multiple <?= $readonly ?>>
          <?php foreach ($skills as $skill): ?>
            <option value="<?= $skill->id ?>" <?= in_array($skill->id, $selected_skills) ? 'selected' : '' ?>><?= $skill->skill ?></option>
          <?php endforeach; ?>
        </select>
        <small>Select one or more skill areas.</small>
      </td>
    </tr>
    <tr>
      <td><label for="training-contribution" class="form-label"> Explain how the training will contribute to the staff member’s development and the department’s work.</label></td>
      <td>
        <textarea id="training-contribution" class="form-control" rows="3" name="training_contributions" <?= $readonly ?>><?= $ppa->training_contributions ?? '' ?></textarea>
      </td>
    </tr>
    <tr>
      <td><label class="form-label">Selection of courses in line with training needs</label></td>
      <td>
      <small>Separate multiple courses using a semicolon (;).	With reference to the current AUC Learning and Development (L&D) Catalogue, please list the recommended course(s) for this staff member:</small>
        <textarea id="training_courses" class="form-control" rows="3" name="recommended_trainings" <?= $readonly ?>><?= $ppa->recommended_trainings ?? '' ?></textarea>
 
        <small>Where applicable, please provide details of highly <b>recommendable course(s)</b> for this staff member that are not listed in the AUC L&D Catalogue</small>
        <textarea id="training_courses" class="form-control" rows="3" name="recommended_trainings_details" <?= $readonly ?>><?= $ppa->recommended_trainings_details ?? '' ?></textarea>
        
      </td>
    </tr>
  </table>
</section>

<hr>

<h4>D. Sign Off</h4>
<table class="form-table">
  <tr>
    <td colspan="4">
      <p>
        I hereby confirm that this PPA has been developed in consultation with my supervisor
        and that it is aligned with the departmental objectives.
        I fully understand my performance objectives and what I am expected to deliver during this performance period.
        I am also aware of the competencies that I will be assessed on for the same period.
      </p>
      <input type="checkbox" id="staff_sign_off" name="staff_sign_off" value="1" <?= $readonly ?> <?= ($ppa->staff_sign_off ?? 0) ? 'checked' : '' ?> required>
      <label for="staff_sign_off">Confirm</label>
    </td>
  </tr>
  <tr>
    <td><label>Staff Signature</label><br>
    <?php if (!empty($session->signature)): ?>
        <img src="<?= base_url('uploads/staff/signature/' . $session->signature) ?>" style="width: 100px; height: 80px;">
      <?php endif; ?>
  </td>
    
  </tr>
  <tr>
    <td><label>Date</label>
    <br>
    <?php
    $created = !empty($ppa->created_at) ? date('j F, Y', strtotime($ppa->created_at)) : date('j F, Y');
    ?>
    <input type="text" class="form-control" value="<?= $created ?>" readonly>
  </td>
  </tr>

  <tr>
    <td><label>Comments for Approval</label>
    <br>
    <textarea id="comments" class="form-control" rows="3" name="comments" ></textarea>
       
  </td>
   
  </tr>
  <tr>
  <td colspan="4" class="text-center">

    <?php if (!$readonly): ?>
      <!-- Staff Submission Buttons -->
      <button type="submit" name="submit_action" value="draft" class="btn btn-warning px-5">Save as Draft</button>
      <button type="submit" name="submit_action" value="submit" class="btn btn-success px-5">Submit</button>
    <?php endif; ?>

    <?php
      $user = $this->session->userdata('user');
      $staff_id = $user->staff_id ?? null;
      $isSupervisor1 = isset($ppa->supervisor_id) && $ppa->supervisor_id == $staff_id;
      $isSupervisor2 = isset($ppa->supervisor2_id) && $ppa->supervisor2_id == $staff_id;

      $approval_trail = $approval_trail ?? [];
      $last_action = count($approval_trail) > 0 ? end($approval_trail)->action ?? null : null;

      $supervisor1Approved = false;
      if (!empty($approval_trail)) {
          foreach ($approval_trail as $log) {
              if (
                  isset($log->action, $log->staff_id) &&
                  $log->action === 'Approved' &&
                  $log->staff_id == $ppa->supervisor_id
              ) {
                  $supervisor1Approved = true;
                  break;
              }
          }
      }

      $showApprovalBtns = false;
      if ($last_action === 'Submitted') {
          if ($isSupervisor1) {
              $showApprovalBtns = true;
          } elseif ($isSupervisor2 && $supervisor1Approved) {
              $showApprovalBtns = true;
          }
      }
    ?>

    <?php if ($showApprovalBtns): ?>
      <form method="post" action="<?= base_url('performance/approve_ppa/' . $ppa->entry_id) ?>" style="display:inline;">
        <input type="hidden" name="action" value="approve">
        <button type="submit" class="btn btn-success px-5">Approve</button>
      </form>

      <form method="post" action="<?= base_url('performance/approve_ppa/' . $ppa->entry_id) ?>" style="display:inline;">
        <input type="hidden" name="action" value="return">
        <button type="submit" class="btn btn-danger px-5">Return for Revision</button>
      </form>
    <?php endif; ?>

  </td>
</tr>

</table>

<?php echo form_close(); ?>

<hr>
<h4>Approval Trail</h4>
<table class="table table-bordered">
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
  <?php if (!empty($approval_trail)): ?>
    <?php foreach ($approval_trail as $log): 
      $logged = Modules::run('auth/contract_info', $log->staff_id);

      // Determine role
      if ($log->staff_id == $ppa->staff_id) {
          $role = 'Staff';
      } elseif ($log->staff_id == $ppa->supervisor_id) {
          $role = 'First Supervisor';
      } elseif ($ppa->supervisor2_id && $log->staff_id == $ppa->supervisor2_id) {
          $role = 'Second Supervisor';
      } else {
          $role = 'Other';
      }
    ?>
      <tr>
        <td><?php echo $logged->title.' '.$logged->fname.' '.$logged->lname.' '.$logged->oname; ?></td>
        <td><?= $role; ?></td>
        <td><?= $log->action; ?></td>
        <td><?= date('d M Y H:i', strtotime($log->created_at)); ?></td>
        <td><?= $log->comments; ?></td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr>
      <td colspan="5" class="text-center">No approval activity yet.</td>
    </tr>
  <?php endif; ?>
</tbody>

</table>




