<?php 
$session = $this->session->userdata('user');

if($this->uri->segment(2)=='view_ppa'){
  $staff_id= $this->uri->segment(4);
$contract = Modules::run('auth/contract_info', $staff_id);
}
else{
   $staff_id = $session->staff_id;
$contract = Modules::run('auth/contract_info', $staff_id);

}
//dd($contract);
//dd($this->uri->segment(2));
$readonly = isset($ppa) && $ppa->draft_status == 0 ? 'readonly disabled' : '';

@$showApprovalBtns = show_ppa_approval_action(@$ppa, @$approval_trail, $this->session->userdata('user'));
//sdd($showApprovalBtns);


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

  box-shadow: none !important;
  background-color: transparent;
}
.is-invalid {
  border: 1px solid red !important;
}

  .form-table { width: 100%; border-collapse: collapse; }
  .form-table td { padding-left: 2px; }

  .objective-table th, .objective-table td { text-align: left; padding: 0px; border: 1px solid #ccc; }
</style>
<?php $this->load->view('ppa_tabs')?>
<?php if($showApprovalBtns!='show'){
  echo $showApprovalBtns;
} ?>

<?php echo form_open_multipart(base_url('performance/save_ppa'), ['id' => 'staff_ppa']); ?>


<h4>A. Staff Details</h4>
<table class="form-table table-bordered">
  <tr>
    <td><b>Name</b></td>
    <td><input type="text" name="name" class="form-control" value="<?= $contract->fname.' '.$contract->lname ?>" readonly></td>
    <td><b>SAP NO</b></td>
    <td><input type="text" class="form-control" value="<?= $contract->SAPNO ?>" readonly></td>
  </tr>
  <tr>
    <td><b>Position</b></td>
    <td><input type="text" class="form-control" value="<?= $contract->job_name ?>" readonly></td>
    <td><b>In this Position Since</b></td>
    <td><input type="text" class="form-control" value="<?= $contract->start_date ?>" readonly></td>
  </tr>
  <tr>
    <td><b>Division/Directorate</b></td>
    <td><input type="text" class="form-control" value="<?= acdc_division($contract->division_id) ?>" readonly></td>
    <td><b>Performance Period</b></td>
    <td><input type="text" class="form-control" name="performance-period" value="<?php if(!empty($ppa->performance_period)){ echo $ppa->performance_period; } else { echo current_period();} ?>" readonly></td>
  </tr>
  <tr>
    <td><b>First Supervisor</b></td>
    <td colspan="1">
      <input type="text" class="form-control" name="supervisor_name"
        value="<?= staff_name(get_supervisor(current_contract($staff_id))->first_supervisor) ?>" readonly>
      <input type="hidden" name="supervisor_id"
        value="<?= get_supervisor(current_contract($staff_id))->first_supervisor ?>">
    </td>
    <td><b>Second Supervisor</b></td>
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
          $isRequired = $i <= 3 ? 'required' : ''; // Only required for the first 3
        ?>
          <tr>
            <td><?= $i ?></td>
            <td><textarea name="objectives[<?= $i ?>][objective]" class="form-control objective-input" <?= $readonly ?> <?= $isRequired ?>><?= $val['objective'] ?></textarea></td>
            <td><input type="text" name="objectives[<?= $i ?>][timeline]" class="form-control datepicker objective-input" <?= $readonly ?> value="<?php if(empty($val['timeline'])){ echo date ('Y-m-d');}else{ echo $val['timeline']; } ?>" <?= $isRequired ?>></td>
            <td><textarea name="objectives[<?= $i ?>][indicator]" class="form-control objective-input" <?= $readonly ?> <?= $isRequired ?>><?= $val['indicator'] ?></textarea></td>
            <td><input type="number" name="objectives[<?= $i ?>][weight]" class="form-control objective-input" <?= $readonly ?> value="<?php if(empty($val['weight'])){ echo 0;}else{ echo $val['weight']; } ?>" <?= $isRequired ?>></td>
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
    <?php if (!empty(staff_details($staff_id)->signature)): ?>
        <img src="<?= base_url('uploads/staff/signature/' . staff_details($staff_id)->signature) ?>" style="width: 100px; height: 80px;">
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
  <td colspan="4" class="text-center">

    <?php if ((!$readonly)&&($ppa->staff_id==$this->session->userdata('user')->staff_id)): ?>
      <br>
      <label>Comments for Approval</label>
      <textarea name="comments" class="form-control" rows="3" placeholder="Enter approval comments..."></textarea>
      <br>
      <!-- Staff Submission Buttons -->
      <button type="submit" name="submit_action" value="draft" class="btn btn-warning px-5">Save as Draft</button>
      <button type="submit" name="submit_action" value="submit" class="btn btn-success px-5">Submit</button>
    <?php endif; ?>

    <?php echo form_close(); ?>


<?php if ($showApprovalBtns == 'show'): ?>
  <form method="post" action="<?= base_url('performance/approve_ppa/' . $ppa->entry_id) ?>">
    <div class="mb-3">
      <label for="comments">Comments for Approval/Return</label>
      <textarea id="comments" name="comments" class="form-control" rows="3" required></textarea>
    </div>

    <input type="hidden" name="action" id="approval_action" value="">

    <div class="text-center">
      <button type="submit" class="btn btn-success px-5 me-2" onclick="document.getElementById('approval_action').value = 'approve';">
        Approve
      </button>
      <button type="submit" class="btn btn-danger px-5" onclick="document.getElementById('approval_action').value = 'return';">
        Return
      </button>
    </div>
  </form>
<?php endif; ?>


  </td>
</tr>

</table>



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




