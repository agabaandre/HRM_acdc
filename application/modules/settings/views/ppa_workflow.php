<?php
$s = $setting;
$entryId = $entry_id ?? '';
$preview = $preview ?? null;
?>
<div class="row mb-3">
  <div class="col-md-12">
    <a href="<?= base_url('settings') ?>" class="text-decoration-none"><i class="fa fa-arrow-left"></i> Settings</a>
    <h4 class="mt-2 mb-1">PPA workflow</h4>
          <p class="text-muted small mb-0">
      PPA and midterm use only the first supervisor unless you turn second-supervisor approval on.
      Endterm: first supervisor, then employee consent on that rating, then second supervisor (on by default).
      Deadlines remain in
      <a href="<?= rtrim(base_url(), '/') ?>/staff-portal/settings/performance">staff-portal performance settings</a>.
    </p>
  </div>
</div>

<div class="row">
  <div class="col-lg-6 mb-4">
    <div class="card border shadow-sm">
      <div class="card-header bg-light">
        <h5 class="mb-0">Approvers</h5>
      </div>
      <div class="card-body">
        <?= form_open('settings/ppa_workflow_save') ?>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="ppa_requires_second_supervisor" value="1" id="ppa_requires_second_supervisor" <?= !empty($s->ppa_requires_second_supervisor) ? 'checked' : '' ?>>
          <label class="form-check-label" for="ppa_requires_second_supervisor">PPA requires second supervisor</label>
          <div class="form-text">Default off. First-supervisor approval is the final PPA status.</div>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="midterm_requires_second_supervisor" value="1" id="midterm_requires_second_supervisor" <?= !empty($s->midterm_requires_second_supervisor) ? 'checked' : '' ?>>
          <label class="form-check-label" for="midterm_requires_second_supervisor">Midterm requires second supervisor</label>
          <div class="form-text">Default off. First-supervisor approval is the final midterm status.</div>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="endterm_requires_second_supervisor" value="1" id="endterm_requires_second_supervisor" <?= !empty($s->endterm_requires_second_supervisor) ? 'checked' : '' ?>>
          <label class="form-check-label" for="endterm_requires_second_supervisor">Endterm requires second supervisor</label>
          <div class="form-text">Default on. Second supervisor acts only after the employee consents.</div>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="endterm_requires_employee_consent" value="1" id="endterm_requires_employee_consent" <?= !empty($s->endterm_requires_employee_consent) ? 'checked' : '' ?>>
          <label class="form-check-label" for="endterm_requires_employee_consent">Endterm requires employee consent</label>
          <div class="form-text">Default on. After the first supervisor approves, the employee must accept or reject that rating before the second supervisor.</div>
        </div>
        <button class="btn btn-success" type="submit"><i class="fa fa-save me-1"></i> Save</button>
        <?= form_close() ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6 mb-4">
    <div class="card border shadow-sm">
      <div class="card-header bg-light">
        <h5 class="mb-0">Correct a stuck approval</h5>
      </div>
      <div class="card-body">
        <p class="small text-muted">
          Enter the PPA entry ID from the URL (for example <code>57acb619fc074dee9c9cb7663b7e02c4</code>).
          If the first supervisor already approved and second-supervisor approval is off, overall status becomes Approved.
        </p>
        <?= form_open('settings/ppa_workflow', ['method' => 'get', 'class' => 'mb-3']) ?>
        <label class="form-label">PPA entry ID</label>
        <div class="input-group">
          <input type="text" name="entry_id" class="form-control" value="<?= htmlspecialchars($entryId, ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn btn-outline-secondary" type="submit">Preview</button>
        </div>
        <?= form_close() ?>

        <?php if ($entryId !== '' && !$preview): ?>
          <div class="alert alert-warning mb-0">No PPA entry found for that ID.</div>
        <?php elseif ($preview): ?>
          <p class="mb-2">
            <strong><?= htmlspecialchars($preview['staff_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span class="text-muted">· <?= htmlspecialchars($preview['entry']->performance_period ?? '', ENT_QUOTES, 'UTF-8') ?></span>
          </p>
          <table class="table table-sm table-bordered">
            <thead>
              <tr>
                <th>Phase</th>
                <th>Status</th>
                <th>First supervisor</th>
                <th>Second required</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($preview['phases'] as $phase): ?>
                <tr>
                  <td><?= htmlspecialchars($phase['label'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($phase['exists'] ? $phase['state'] : 'Not started', ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($phase['s1_action'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= !empty($phase['requires_second']) ? 'Yes' : 'No' ?></td>
                  <td><?= !empty($phase['can_correct']) ? 'Will mark approved' : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?= form_open('settings/ppa_workflow_correct') ?>
            <input type="hidden" name="entry_id" value="<?= htmlspecialchars($entryId, ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn btn-primary" type="submit" <?= empty($preview['can_correct']) ? 'disabled' : '' ?>>
              Apply correction
            </button>
          <?= form_close() ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
