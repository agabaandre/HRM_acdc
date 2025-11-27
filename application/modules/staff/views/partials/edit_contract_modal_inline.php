<!-- edit employee contract -->
<div class="modal fade" id="renew_contract<?=$contract->staff_contract_id?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="add_item_label">Edit Contract: <?= ($this_staff->lname ?? '') . ' ' . ($this_staff->fname ?? '') . ' ' . @$this_staff->oname ?> </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <?php echo validation_errors(); ?>
        <?php echo form_open('staff/update_contract'); 
        $readonly='';
        ?>
        
        <input type="hidden" name="staff_contract_id" value="<?php echo $contract->staff_contract_id; ?>">
        <input type="hidden" name="staff_id" value="<?php echo $contract->staff_id; ?>">

        <div class="row">
          <div class="col-md-6">
            <h4>Contract Information</h4>
            <div class="form-group">
              <label for="job_id">Job: <?php echo asterik()?></label>
              <select class="form-control select2" name="job_id" id="job_id" required <?=$readonly?>>
                <option value="">Select Job </option>
                <?php $jobs = Modules::run('lists/jobs');
                foreach ($jobs as $job) : ?>
                  <option value="<?php echo $job->job_id; ?>" <?php if ($job->job_id == $contract->job_id) { echo "selected"; } ?>><?php echo $job->job_name; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="job_acting_id">Job Acting:</label>
              <select class="form-control select2" name="job_acting_id" id="job_acting_id" <?=$readonly?>>
                <option value="">Select Job Acting</option>
                <?php $jobsacting = Modules::run('lists/jobsacting');
                foreach ($jobsacting as $joba) : ?>
                  <option value="<?php echo $joba->job_acting_id; ?>" <?php if ($joba->job_acting_id == $contract->job_acting_id) { echo "selected"; } ?>><?php echo $joba->job_acting; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="grade_id">Grade: <?php echo asterik()?></label>
              <select class="form-control select2" name="grade_id" id="grade_id" required <?=$readonly?>>
                <option value="">Select Grade</option>
                <?php $lists = Modules::run('lists/grades');
                foreach ($lists as $list) : ?>
                  <option value="<?php echo $list->grade_id; ?>" <?php if ($list->grade_id == $contract->grade_id) { echo "selected"; } ?>><?php echo $list->grade; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="contracting_institution_id">Contracting Institution: <?php echo asterik()?></label>
              <select class="form-control select2" name="contracting_institution_id" id="contracting_institution_id" required <?=$readonly?>>
                <option value="">Select Contracting Institution</option>
                <?php $lists = Modules::run('lists/contractors');
                foreach ($lists as $list) : ?>
                  <option value="<?php echo $list->contracting_institution_id; ?>" <?php if ($list->contracting_institution_id == $contract->contracting_institution_id) { echo "selected"; } ?>><?php echo $list->contracting_institution; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="funder_id">Funder: <?php echo asterik()?></label>
              <select class="form-control select2" name="funder_id" id="funder_id" required <?=$readonly?>>
                <option value="">Select Funder</option>
                <?php $lists = Modules::run('lists/funder');
                foreach ($lists as $list) : ?>
                  <option value="<?php echo $list->funder_id; ?>" <?php if ($list->funder_id == $contract->funder_id) { echo "selected"; } ?>><?php echo $list->funder; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="first_supervisor">First Supervisor: <?php echo asterik()?></label>
              <select class="form-control select2" name="first_supervisor" id="first_supervisor" required <?=$readonly?>>
                <option value="">Select First Supervisor</option>
                <?php $lists = Modules::run('lists/supervisor');
                foreach ($lists as $list) : ?>
                  <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $contract->first_supervisor) { echo "selected"; } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="second_supervisor">Second Supervisor:</label>
              <select class="form-control select2" name="second_supervisor" id="second_supervisor" <?=$readonly?>>
                <option value="">Select Second Supervisor</option>
                <?php $lists = Modules::run('lists/supervisor');
                foreach ($lists as $list) : ?>
                  <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $contract->second_supervisor) { echo "selected"; } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="contract_type_id">Contract Type: <?php echo asterik()?></label>
              <select class="form-control select2" name="contract_type_id" id="contract_type_id" required <?=$readonly?>>
                <?php $lists = Modules::run('lists/contracttype');
                foreach ($lists as $list) : ?>
                  <option value="<?php echo $list->contract_type_id; ?>" <?php if ($list->contract_type_id == $contract->contract_type_id) { echo "selected"; } ?>><?php echo $list->contract_type; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          
          <div class="col-md-6" style="margin-top:35px;">
            <div class="form-group">
              <label for="duty_station_id">Duty Station: <?php echo asterik()?></label>
              <select class="form-control select2" name="duty_station_id" id="duty_station_id" required <?=$readonly?>>
                <?php $lists = Modules::run('lists/stations');
                foreach ($lists as $list) : ?>
                  <option value="<?php echo $list->duty_station_id; ?>" <?php if ($list->duty_station_id == $contract->duty_station_id) { echo "selected"; } ?>><?php echo $list->duty_station_name; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="division_id">Division: <?php echo asterik()?></label>
              <select class="form-control select2" name="division_id" id="division_id" required <?=$readonly?>>
                <?php $lists = Modules::run('lists/divisions');
                foreach ($lists as $list) : ?>
                  <option value="<?php echo $list->division_id; ?>" <?php if ($list->division_id == $contract->division_id) { echo "selected"; } ?>><?php echo $list->division_name; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="other_associated_divisions">Other Associated Divisions:</label>
              <select class="form-control select2" name="other_associated_divisions[]" id="other_associated_divisions" multiple <?=$readonly?>>
                <option value="">Select Associated Divisions</option>
                <?php 
                $lists = Modules::run('lists/divisions');
                $current_divisions = [];
                if (!empty($contract->other_associated_divisions)) {
                  $current_divisions = json_decode($contract->other_associated_divisions, true);
                  if (!is_array($current_divisions)) {
                    $current_divisions = [];
                  }
                }
                foreach ($lists as $list) : ?>
                  <option value="<?php echo $list->division_id; ?>" <?php if (in_array($list->division_id, $current_divisions)) { echo "selected"; } ?>><?php echo $list->division_name; ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">You can select multiple divisions. Leave empty if none.</small>
            </div>

            <div class="form-group">
              <label for="start_date">Start Date: <?php echo asterik()?></label>
              <input type="text" class="form-control datepicker" value="<?php echo $contract->start_date; ?>" name="start_date" id="start_date" required <?=$readonly?>>
            </div>

            <div class="form-group">
              <label for="end_date">End Date: <?php echo asterik()?></label>
              <input type="text" class="form-control datepicker" value="<?php echo $contract->end_date; ?>" name="end_date" id="end_date" required <?=$readonly?>>
            </div>

            <div class="form-group">
              <label for="status_id">Contract Status: <?php echo asterik()?></label>
              <select class="form-control select2" name="status_id" id="status_id" required>
                <?php 
                $lists = Modules::run('lists/status');
                $current_status_id = isset($contract->status_id) ? (int)$contract->status_id : null;
                foreach ($lists as $list) :
                  // Allow editing: Active (1), Separated (4), Under Renewal (7), and also show Expired (3) if it's the current status
                  $is_allowed = in_array($list->status_id, [1, 4, 7]);
                  $is_selected = ($current_status_id !== null && (int)$list->status_id === $current_status_id);
                  // Always show the current status, even if it's not in the allowed list (e.g., expired/separated)
                  // This ensures expired (3) and separated (4) contracts can be viewed and edited
                  $should_show = $is_allowed || $is_selected;
                ?>
                  <?php if ($should_show): ?>
                    <option value="<?= $list->status_id ?>" <?= $is_selected ? 'selected' : '' ?> <?= !$is_allowed && !$is_selected ? 'disabled' : '' ?>>
                      <?= htmlspecialchars($list->status) ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="comments">Comments:</label>
              <textarea class="form-control" name="comments" id="comments" rows="3"><?php echo $contract->comments; ?></textarea>
            </div>

            <div class="form-group" style="float:right;">
              <br>
              <label for="submit"></label>
              <input type="submit" class="btn btn-dark" value="Save">
            </div>
          </div>
        </div>
        <?php echo form_close(); ?>
      </div>
    </div>
  </div>
</div>

