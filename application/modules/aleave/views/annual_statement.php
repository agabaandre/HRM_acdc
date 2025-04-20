<?php
$user = $this->session->userdata('user');
$is_hr = ($user->role == 20);
?>

<div class="container-fluid mt-3">
  <div class="card shadow-sm border">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Annual Leave Statements</h5>
      <?php if ($is_hr): ?>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#leaveEntryModal">
          <i class="fa fa-plus"></i> Add Leave Transaction
        </button>
      <?php endif; ?>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Staff</th>
              <th>Contract</th>
              <th>Type</th>
              <th>Days</th>
              <th>Created At</th>
              <th>Created By</th>
              <th>Updated At</th>
              <th>Updated By</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; foreach ($statements as $row): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= staff_name($row->staff_id) ?></td>
                <td>#<?= $row->staff_contract_id ?></td>
                <td><span class="badge bg-<?= $row->leave_transaction == 'cr' ? 'success' : 'danger' ?>">
                    <?= strtoupper($row->leave_transaction) ?>
                  </span>
                </td>
                <td><?= number_format($row->days, 2) ?></td>
                <td><?= date('M d, Y', strtotime($row->created_at)) ?></td>
                <td><?= staff_name($row->created_by) ?></td>
                <td><?= $row->updated_at ? date('M d, Y', strtotime($row->updated_at)) : '-' ?></td>
                <td><?= staff_name($row->updated_by) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- HR Modal: Add Transaction -->
<?php if ($is_hr): ?>
<div class="modal fade" id="leaveEntryModal" tabindex="-1" aria-labelledby="leaveEntryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <?= form_open('leave/add_annual_transaction', ['id' => 'leaveEntryForm']) ?>
      <div class="modal-header">
        <h5 class="modal-title">Add Leave Credit/Debit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Staff</label>
          <select name="staff_id" class="form-select select2" required>
            <option value="">Select Staff</option>
            <?php foreach ($staff_list as $s): ?>
              <option value="<?= $s->staff_id ?>"><?= staff_name($s->staff_id) ?></option>
            <?php endforeach; ?>
          </select>
        </div>


        <div class="col-md-6 mb-3">
          <label class="form-label">Transaction Type</label>
          <select name="leave_transaction" class="form-select" required>
            <option value="cr">Credit</option>
            <option value="db">Debit</option>
          </select>
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">Days</label>
          <input type="number" step="0.01" name="days" class="form-control" required>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" type="submit"><i class="fa fa-save me-1"></i> Submit</button>
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
      </div>
      <?= form_close() ?>
    </div>
  </div>
</div>
<?php endif; ?>
