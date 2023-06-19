

<div class="card">

  <div class="card-body">
    <!-- leave_form.php -->
    <form method="POST" action="<?php echo base_url('leave/request'); ?>">
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label for="staff_id" class="form-label">Staff ID</label>
            <input type="text" class="form-control" id="staff_id" name="staff_id" required>
          </div>
          <div class="form-group">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" class="form-control" id="start_date" name="start_date" required>
          </div>
          <div class="form-group">
            <label for="leave_id" class="form-label">Leave Type</label>
            <select class="form-select" id="leave_id" name="leave_id" required>
              <option value="">Select Leave Type</option>
              <!-- Populate options dynamically from leave_types table in the database -->
              <?php foreach ($leave_types as $leave_type) : ?>
                <option value="<?php echo $leave_type['leave_id']; ?>"><?php echo $leave_type['leave_name']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Additional fields as per your requirements -->
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label for="end_date" class="form-label">End Date</label>
            <input type="date" class="form-control" id="end_date" name="end_date" required>
          </div>
          <div class="form-group">
            <label for="requested_days" class="form-label">Requested Days</label>
            <input type="number" class="form-control" id="requested_days" name="requested_days" required>
          </div>
          <div class="form-group">
            <label for="remarks" class="form-label">Remarks</label>
            <textarea class="form-control" id="remarks" name="remarks"></textarea>
          </div>
          <!-- Additional fields as per your requirements -->
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Submit</button>
    </form>

  </div>
</div>