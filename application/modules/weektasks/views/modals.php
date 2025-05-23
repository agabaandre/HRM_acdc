
<!-- Edit Task Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <?= form_open('weektasks/update', [
      'id' => 'editActivityForm',
      'class' => 'modal-content needs-validation',
      'novalidate' => 'novalidate'
    ]) ?>
    <div class="modal-header">
      <h5 class="modal-title">Edit Task</h5>
      <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
    </div>

    <input type="hidden"
       name="<?= $this->security->get_csrf_token_name(); ?>"
       value="<?= $this->security->get_csrf_hash(); ?>">

    <div class="modal-body">
      <input type="hidden" name="activity_id" id="edit_id">

      <!-- Activity Name -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Activity Name</label>
        <input type="text" name="activity_name" id="edit_name" class="form-control" required>
        <div class="invalid-feedback">Activity name is required.</div>
      </div>

      <!-- Assigned Staff -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Assign Staff</label>
        <div class="row">
          <?php foreach ($staff_list as $staff): ?>
            <div class="col-md-6">
              <div class="form-check mb-2">
                <input class="form-check-input edit-staff-checkbox"
                  type="checkbox"
                  name="staff_ids[]"
                  value="<?= $staff->staff_id ?>"
                  id="staff_<?= $staff->staff_id ?>">
                <label class="form-check-label" for="staff_<?= $staff->staff_id ?>">
                  <?= $staff->title . ' ' . $staff->fname . ' ' . $staff->lname ?>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

   

      <!-- Status -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Status</label>
        <select name="status" id="edit_status" class="form-select" required>
          <option value="1">Pending</option>
          <option value="2">Completed</option>
          <option value="3">Carried Forward</option>
          <option value="4">Cancelled</option>
        </select>
        <div class="invalid-feedback">Please select a status.</div>
      </div>

      <!-- Comments -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Comments</label>
        <textarea name="comments" id="edit_comments" class="form-control" rows="3"></textarea>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-primary" type="submit">
        <i class="fa fa-check-circle me-1"></i> Update Task
      </button>
    </div>
    <?= form_close(); ?>

  </div>
</div>

<!-- Add Weekly Task Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <?= form_open('weektasks/save', [
      'id' => 'addActivityForm',
      'class' => 'modal-content needs-validation',
      'novalidate' => 'novalidate'
    ]) ?>
    <div class="modal-header">
      <h5 class="modal-title">Add Weekly Tasks</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <input type="hidden"
       name="<?= $this->security->get_csrf_token_name(); ?>"
       value="<?= $this->security->get_csrf_hash(); ?>">

    <div class="modal-body">

        <!-- Team Lead -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Unit Team Lead</label>
        <select name="team_lead" id="team_lead_select" class="form-select select2" required>
          <option value="">Select</option>
          <?php foreach ($team_leads as $leader): ?>
            <option value="<?= $leader->staff_id ?>"><?= $leader->fname . ' ' . $leader->lname ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Sub Activity -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Sub-Activity</label>
        <select name="work_planner_tasks_id" id="sub_activity_select" class="form-select select2" required>
          <option value="">Select</option>
          <!-- Will be populated dynamically -->
        </select>
      </div>
      <!-- Assigned Staff -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Assign Staff</label>
        <div class="row">
          <?php foreach ($staff_list as $staff): ?>
            <div class="col-md-6">
              <div class="form-check mb-2">
                <input class="form-check-input edit-staff-checkbox"
                  type="checkbox"
                  name="staff_ids[]"
                  value="<?= $staff->staff_id ?>"
                  id="staff_<?= $staff->staff_id ?>">
                <label class="form-check-label" for="staff_<?= $staff->staff_id ?>">
                  <?= $staff->title . ' ' . $staff->fname . ' ' . $staff->lname ?>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="invalid-feedback">Please assign at least one staff.</div>
      </div>


      <!-- Start & End Dates -->
      <div class="row mb-3">
        <div class="col">
          <label>Start Date</label>
          <input type="text" name="start_date" class="form-control activity-dates" required>
        </div>
        <div class="col">
          <label>End Date</label>
          <input type="text" name="end_date" class="form-control activity-dates" required>
        </div>
      </div>

      <!-- Activities -->
    <div id="activityContainer">
      <div class="activity-row row g-3 mb-2">
        <div class="col">
          <input type="text" name="activity_name[]" class="form-control" placeholder="Activity Name" required>
        </div>
        <div class="col">
          <input type="text" name="comments[]" class="form-control" placeholder="Comments">
        </div>
        <div class="col-auto">
          <button type="button" class="btn btn-success btn-sm add-activity"><i class="fa fa-plus"></i></button>
          <button type="button" class="btn btn-danger btn-sm remove-activity"><i class="fa fa-trash"></i></button>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-success">Save</button>
    </div>
    <?= form_close(); ?>
  </div>
</div>