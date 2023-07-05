<div class="card">

  <div class="card-body">
    <!-- leave_form.php -->
    <?php echo form_open_multipart(base_url('leave/request_leave'), array('id' => 'leave', 'class' => 'leave')); ?>
    <div class="row">
      <div class="col-md-12">
      </div>
      <div class="col-md-6">

        <div class="form-group">
          <label for="staff_id" class="form-label">Staff Name</label>
          <input type="text" class="form-control" value="<?php echo $this->session->userdata('user')->name ?>" id="staff_name" name="name" readonly>
          <input type="hidden" class="form-control" value="<?php echo $this->session->userdata('user')->staff_id ?>" id="staff_id" name="staff_id" readonly>
          <input type="hidden" class="form-control" value="<?php echo current_contract($this->session->userdata('user')->staff_id) ?>" id="staff_id" name="contract_id" readonly>
          <input type="hidden" class="form-control" value="<?php echo current_head_of_departmemnt($this->session->userdata('user')->division_id) ?>" id="division_id" name="division_head" readonly>
        </div>


        <div class="form-group">
          <label for="start_date" class="form-label">Start Date *</label>
          <input type="text" class="form-control datepicker" id="start_date" name="start_date" required>
        </div>
        <div class="form-group">
          <label for="end_date" class="form-label">End Date *</label>
          <input type="text" class="form-control datepicker" id="end_date" name="end_date" required>
        </div>
        <div class="form-group">
          <label for="requested_days" class="form-label">Requested Days</label>
          <input type="number" class="form-control" id="requested_days" name="requested_days" readonly>
        </div>
        <div class="form-group">
          <label for="mobile_leave" class="form-label">Phone Contact while on Leave *</label>
          <input type="text" class="form-control" id="mobile_leave" name="mobile_leave" required>
        </div>
        <!-- Additional fields as per your requirements -->
        <input type="hidden" class="form-control" id="supervisor_id" value="<?php echo get_supervisor(current_contract($this->session->userdata('user')->staff_id))->first_supervisor ?>" name="supervisor_id" required>
        <input type="hidden" class="form-control" id="supervisor2_id" name="supervisor2_id" value="<?php echo get_supervisor(current_contract($this->session->userdata('user')->staff_id))->second_supervisor ?>" required>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label for="email_leave" class="form-label">Email Contact while on Leave *</label>
          <input type="text" class="form-control" id="email_leave" name="email_leave" required>
        </div>

        <div class="form-group">
          <label for="leave_id" class="form-label">Leave Type <b>(Sick leave with or without pay should be accompanied by Doctors’ recommendation</b>)</label>
          <select class="form-select select2" id="leave_id" name="leave_id" required>
            <option value="">Select Leave Type *</option>
            <!-- Populate options dynamically from leave_types table in the database -->
            <?php $lists = Modules::run('lists/leave');
            foreach ($lists as $list) :
            ?>
              <option value="<?php echo $list->leave_id; ?>"><?php echo $list->leave_name; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="requested_days" class="form-label">Supporting Officer during Leave *</label>
          <select class="form-select select2" id="staff_id" name="supporting_staff" required>
            <option value="">Select Officer</option>
            <!-- Populate options dynamically from leave_types table in the database -->
            <?php $lists = Modules::run('lists/supervisor');
            foreach ($lists as $list) :
            ?>
              <option value="<?php echo $list->staff_id; ?>"><?php echo $list->lname . ' ' . $list->fname . ' ' . $list->oname; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="file" class="form-label">Supporting File (File should be less than 2MBs) </label>
          <input type="file" class="form-control" id="document" name="document">
        </div>
        <div class="form-group">
          <label for="remarks" class="form-label">Remarks *</label>
          <textarea class="form-control" id="remarks" name="remarks" required></textarea>
        </div>
        <!-- Additional fields as per your requirements -->
      </div>
    </div>
    <div class="col-md-12" style="margin-top:5px;">
      <button type="submit" class="btn btn-primary">Submit</button>
    </div>
    </form>

  </div>
</div>