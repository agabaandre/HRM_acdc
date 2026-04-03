<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">CBP modules</h5>
    <a href="<?= base_url('settings') ?>" class="btn btn-sm btn-outline-secondary">Back to settings</a>
  </div>

  <p class="text-muted small">
    Configure tiles on the Staff portal home (<code>home/index</code>) and optional links in the APM primary menu.
    <strong>Uses staff portal token for authentication</strong> means the session is passed like APM or Finance; otherwise the target is a normal Staff portal path (no token).
    <strong>Production visibility</strong>: when unchecked, the module is shown only to users with <strong>role ID 10</strong> (in addition to the permission below).
  </p>

  <?php if (empty($table_exists)) : ?>
    <div class="alert alert-warning">
      The <code>cbp_modules</code> table is not installed. Run the migration script on your Staff database:
      <code class="d-block mt-2">application/sql/create_cbp_modules_table.sql</code>
    </div>
  <?php elseif (empty($modules)) : ?>
    <div class="alert alert-info">No modules found. The table is empty; open the home page once to seed defaults, or re-run the SQL file.</div>
  <?php else : ?>
    <div class="accordion" id="cbpModulesAccordion">
      <?php foreach ($modules as $i => $m) : ?>
        <div class="accordion-item">
          <h2 class="accordion-header" id="cbp-h-<?= (int) $m->id ?>">
            <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#cbp-c-<?= (int) $m->id ?>">
              <span class="me-2"><i class="fas <?= htmlspecialchars($m->icon_class) ?>"></i></span>
              <?= htmlspecialchars($m->system_name) ?>
              <span class="badge bg-secondary ms-2"><?= htmlspecialchars($m->module_key) ?></span>
            </button>
          </h2>
          <div id="cbp-c-<?= (int) $m->id ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#cbpModulesAccordion">
            <div class="accordion-body">
              <?= form_open('settings/cbp_modules_save') ?>
                <input type="hidden" name="id" value="<?= (int) $m->id ?>">

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">System name</label>
                    <input type="text" name="system_name" class="form-control" required maxlength="191" value="<?= htmlspecialchars($m->system_name) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Permission code</label>
                    <input type="text" name="permission_code" class="form-control" required maxlength="32" value="<?= htmlspecialchars($m->permission_code) ?>"
                           title="Permission id as in the user session, e.g. 84">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars((string) $m->description) ?></textarea>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Base URL / path</label>
                    <input type="text" name="base_url" class="form-control" maxlength="512" value="<?= htmlspecialchars((string) $m->base_url) ?>"
                           placeholder="dashboard, apm, or leave empty for Finance resolver">
                    <div class="form-text">Staff portal segment for <em>Staff portal path</em> or APM segment for token links.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Link target</label>
                    <select name="target_resolver" class="form-select">
                      <?php foreach ($resolver_options as $val => $lab) : ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $m->target_resolver === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Finance — development base URL</label>
                    <input type="text" name="base_url_development" class="form-control" maxlength="512" value="<?= htmlspecialchars((string) ($m->base_url_development ?? '')) ?>"
                           placeholder="http://localhost:3002">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Finance — production path or URL</label>
                    <input type="text" name="base_url_production" class="form-control" maxlength="512" value="<?= htmlspecialchars((string) ($m->base_url_production ?? '')) ?>"
                           placeholder="finance or https://example.org/finance">
                    <div class="form-text">Leave empty to use <code>/finance</code> on the current host.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Alternate path (Staff portal)</label>
                    <input type="text" name="alternate_base_url" class="form-control" maxlength="255" value="<?= htmlspecialchars((string) ($m->alternate_base_url ?? '')) ?>"
                           placeholder="auth/profile">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Alternate path — role ID</label>
                    <input type="number" name="alternate_for_role_id" class="form-control" min="0" value="<?= $m->alternate_for_role_id !== null && $m->alternate_for_role_id !== '' ? (int) $m->alternate_for_role_id : '' ?>"
                           placeholder="e.g. 17">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Icon (Font Awesome)</label>
                    <select name="icon_class" class="form-select">
                      <?php foreach ($icon_options as $ic => $ilab) : ?>
                        <option value="<?= htmlspecialchars($ic) ?>" <?= $m->icon_class === $ic ? 'selected' : '' ?>><?= htmlspecialchars($ilab . ' (' . $ic . ')') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Sort order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= (int) $m->sort_order ?>">
                  </div>
                  <div class="col-12">
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" name="uses_staff_portal_token" value="1" id="tok-<?= (int) $m->id ?>" <?= !empty($m->uses_staff_portal_token) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="tok-<?= (int) $m->id ?>">Uses staff portal token for authentication</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" name="is_production" value="1" id="prod-<?= (int) $m->id ?>" <?= !empty($m->is_production) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="prod-<?= (int) $m->id ?>">Visible to all permitted users (unchecked = role 10 only)</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="en-<?= (int) $m->id ?>" <?= !empty($m->is_enabled) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="en-<?= (int) $m->id ?>">Enabled</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" name="show_in_apm_menu" value="1" id="apm-<?= (int) $m->id ?>" <?= !empty($m->show_in_apm_menu) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="apm-<?= (int) $m->id ?>">Show in APM top menu</label>
                    </div>
                  </div>
                </div>
                <div class="mt-3">
                  <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save me-1"></i>Save module</button>
                </div>
              <?= form_close() ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
