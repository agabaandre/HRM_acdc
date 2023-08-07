<?php
$session = $this->session->userdata('user');
$staff_id = $this->session->userdata('user')->staff_id;
$contract = Modules::run('auth/contract_info', $staff_id);
//dd($contract)
?>
<style>
  .overflow-content {
    overflow: auto;
    min-height: 800px;

  }
</style>
<form action="<?php echo base_url() ?>performance/save_ppa">
  <div class="container">

    <div cllass="row">
      <!-- SmartWizard html -->
      <div id="smartwizard">
        <ul class="nav">
          <li>
            <a class="nav-link" href="#step-1"> <strong>Step 1</strong>
              <br>Personal Information</a>
          </li>
          <li>
            <a class="nav-link" href="#step-2"> <strong>Step 2</strong>
              <br>Objectives</a>
          </li>
          <li>
            <a class="nav-link" href="#step-4"> <strong>Step 3</strong>
              <br>Training</a>
          </li>
          <li>
            <a class="nav-link" href="#step-5"> <strong>Step 4</strong>
              <br>Sign Off</a>
          </li>

        </ul>
        <div class="tab-content">
          <div id="step-1" class="tab-pane" role="tabpanel" aria-labelledby="step-1">
            <h3>Step 1:</h3>
            <h4>A. Staff Details</h4>
            <div class="row">
              <div class="col-md-6 col-lg-6">
                <div class="mb-3">
                  <label for="name" class="form-label">Name</label>
                  <input type="text" id="name" class="form-control" name="name" value="<?= $session->name ?>" disabled>
                </div>
                <div class="mb-3">
                  <label for="personnel-number" class="form-label">Personnel Number</label>
                  <input type="text" name="personnel_number" id="personnel-number" class="form-control" value="<?= $contract->tel_1 ?>" disabled>
                </div>
                <div class="mb-3">
                  <label for="position" class="form-label">Position</label>
                  <input type="text" name="position" id="position" class="form-control" value="<?= $contract->job_name ?>" disabled>
                </div>
                <div class="mb-3">
                  <label for="position-since" class="form-label">In this Position since</label>
                  <input type="text" id="position-since" class="form-control" value="<?= $contract->start_date ?>" disabled>
                </div>
              </div>
              <div class="col-md-6 col-lg-6">
                <div class="mb-3">
                  <label for="unit" class="form-label">Division</label>
                  <input type="text" id="division" name="dvision" class="form-control" value="<?php echo acdc_division($contract->division_id); ?>" disabled>
                </div>
                <div class="mb-3">
                  <label for="performance-period" class="form-label">Current performance period</label>
                  <select class="form-control" name="performance-period" readonly>
                    <?php echo periods(); ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label for="supervisor" class="form-label">Name of direct supervisor</label>
                  <input type="text" class="form-control" name="supervisor_id" id="supervisor_id" data='<?= get_supervisor(current_contract($session->staff_id))->first_supervisor ?>' value="<?php echo staff_name(get_supervisor(current_contract($session->staff_id))->first_supervisor) ?>" name="supervisor_id" readonly>
                </div>
                <div class="mb-3">
                  <label for="second-supervisor" class="form-label">Name of second supervisor</label>
                  <input type="text" class="form-control" name="supervisor2_id" data='<?= get_supervisor(current_contract($session->staff_id))->second_supervisor ?>' id="supervisor2_id" name="supervisor2_id" value="<?php echo @staff_name(get_supervisor(current_contract($session->staff_id))->second_supervisor) ?>" readonly>
                </div>
              </div>
            </div>
          </div>


          <div id="step-2" class="tab-pane" style="overflow-y: auto!important;" role="tabpanel" aria-labelledby="step-2">
            <h3>Step 2: </h3>
            <div class="mt-4">
              <button class="btn btn-primary" onclick="addObjective()">Create Objective</button>
            </div>

            <h4>B. Performance Objectives</h4>
            <div class="row new-objectives">

            </div>
          </div>

          <div id="step-4" class="tab-pane" role="tabpanel" aria-labelledby="step-4">
            <h3>Step 3: </h3>

            <h1>C. Personal Development Plan</h4>
              <div class="row">
                <div class="col-md-12">
                  <div class="mb-3">
                    <label class="form-label">Is training recommended for this staff member?</label>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="training_recommended" value="Yes">
                      <label class="form-check-label">Yes</label>
                    </div>
                  </div>
                  <section class="required_trainings">
                    <div class="mb-3">
                      <label for="skill-area" class="form-label">If yes, in what subject/ skill area(s) is the training recommended for this staff member?</label>
                      <select class="form-control multiple-select" name="required_skills">
                        <option value="HR and Management">HR and Management</option>
                        <option value="Leadership and Governance">Leadership and Governance</option>
                        <option value="ICT Essentails">Accounting</option>
                        <option value="Cyber Security">Cyber Security</option>
                        <option value="Cyber Security">Languages</option>

                      </select>
                    </div>
                    <div class="mb-3">
                      <label for="training-contribution" class="form-label">How will the recommended training(s) contribute to the staff member’s development and the department’s work?</label>
                      <textarea id="training-contribution" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Selection of courses in line with training needs. For Multiple Courses Separate using a Semi-colon (;)</label>

                    </div>
                  </section>
                </div>
              </div>
          </div>

          <div id="step-5" class="tab-pane" role="tabpanel" aria-labelledby="step-5">
            <h3>Step 4: </h3>
            <h1>D. Sign Off</h1>
            <div class="row">
              <div class="col-md-12 ">
                <div class="mb-3">
                  <label class="form-label">Staff</label>
                  <label class="form-label">I hereby confirm that this PPA has been developed in consultation with my supervisor and that it is aligned with the departmental objectives.

                    I fully understand my performance objectives and what I am expected to deliver during this performance period.

                    I am also aware of the competencies that I will be assessed on for the same period.

                  </label>
                  <input class="form-check-input" type="checkbox" id="staff_sign_off" name="staff_sign_off" value="1" required>
                  <label class="form-check-label" for="staff_sign_off">Confirm</label>
                </div>
                <div class="mb-3">
                  <label class="form-label">Staff Signature</label>
                  <?php if (isset($this->session->userdata('user')->signature)) { ?>
                    <img src="<?php echo base_url() ?>uploads/staff/signature/<?php echo $this->session->userdata('user')->signature; ?>" style="width:100px; height: 80px;">
                  <?php } ?>
                </div>
                <div class="mb-3">
                  <label class="form-label">Date</label>
                  <input type="text" class="form-control" disabled value="<?php echo date('j F, Y'); ?>" name="staff_sign_off_date">
                </div>
              </div>
              <div class="col-md-6">

              </div>
              <div class="col-md-12 col-lg-12">
                <div class="row">
                  <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Save</button>
                  </div>
                  <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Save & Submit</button>
                  </div>
                  <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Recall</button>
                  </div>

                </div>
              </div>

</form>

</div>
</div>

</form>
</div>
</div>
</div>
</div>