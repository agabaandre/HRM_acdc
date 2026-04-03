<?php
$next_sort_order = isset($next_sort_order) ? (int) $next_sort_order : 100;
$next_permission_id_hint = isset($next_permission_id_hint) ? (int) $next_permission_id_hint : 1;
?>
<style>
  /*
   * Add module modal: form wraps header/body/footer, so flex must apply to the form (same idea as tall
   * staff contract edit modals — body scrolls, header/footer stay visible).
   */
  #cbpModuleCreateModal .modal-dialog {
    max-height: calc(100vh - 2rem);
    margin: 1rem auto;
  }
  #cbpModuleCreateModal .modal-content {
    max-height: calc(100vh - 2rem);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  #cbpModuleCreateModal .modal-content > form {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
  }
  #cbpModuleCreateModal .modal-body {
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    flex: 1 1 auto;
    min-height: 0;
  }
  #cbpModuleCreateModal .modal-header,
  #cbpModuleCreateModal .modal-footer {
    flex-shrink: 0;
  }
</style>
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h5 class="mb-0">CBP modules</h5>
    <div class="d-flex gap-2">
      <?php if (!empty($table_exists)) : ?>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#cbpModuleCreateModal">
          <i class="fa fa-plus me-1"></i>Add module
        </button>
      <?php endif; ?>
      <a href="<?= base_url('settings') ?>" class="btn btn-sm btn-outline-secondary">Back to settings</a>
    </div>
  </div>

  <p class="text-muted small">
    Home tiles (<code>home/index</code>) and optional APM menu links. Use <strong>External system</strong> for APIs and UIs on other hosts; set dev/prod URLs or one shared URL. Enable <strong>Append Staff portal session token</strong> only if that system validates the same token as APM/Finance.
    <strong>Production visibility</strong>: when unchecked, only <strong>role ID 10</strong> sees the tile (still requires the permission).
  </p>

  <?php if (empty($table_exists)) : ?>
    <div class="alert alert-warning">
      The <code>cbp_modules</code> table is not installed. Run the migration script on your Staff database:
      <code class="d-block mt-2">application/sql/create_cbp_modules_table.sql</code>
    </div>
  <?php else : ?>
    <?php if (empty($modules)) : ?>
      <div class="alert alert-info mb-3">No modules yet. Use <strong>Add module</strong> or open the home page once to seed defaults from the application.</div>
    <?php else : ?>
      <div class="accordion mb-3" id="cbpModulesAccordion">
        <?php foreach ($modules as $m) : ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="cbp-h-<?= (int) $m->id ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cbp-c-<?= (int) $m->id ?>">
                <span class="me-2"><i class="fas <?= htmlspecialchars($m->icon_class) ?>"></i></span>
                <?= htmlspecialchars($m->system_name) ?>
                <span class="badge bg-secondary ms-2"><?= htmlspecialchars($m->module_key) ?></span>
              </button>
            </h2>
            <div id="cbp-c-<?= (int) $m->id ?>" class="accordion-collapse collapse" data-bs-parent="#cbpModulesAccordion">
              <div class="accordion-body">
                <?= form_open('settings/cbp_modules_save', ['class' => 'cbp-module-form']) ?>
                  <input type="hidden" name="id" value="<?= (int) $m->id ?>">

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">System name</label>
                      <input type="text" name="system_name" class="form-control" required maxlength="191" value="<?= htmlspecialchars($m->system_name) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Module key</label>
                      <input type="text" class="form-control" value="<?= htmlspecialchars($m->module_key) ?>" disabled title="Module key cannot be changed after creation">
                      <div class="form-text">Fixed identifier (not editable).</div>
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

                    <?php
                    $cbp_vals = [
                      'target_resolver' => $m->target_resolver,
                      'base_url' => (string) $m->base_url,
                      'base_url_development' => (string) ($m->base_url_development ?? ''),
                      'base_url_production' => (string) ($m->base_url_production ?? ''),
                      'alternate_base_url' => (string) ($m->alternate_base_url ?? ''),
                      'alternate_for_role_id' => $m->alternate_for_role_id,
                      'uses_staff_portal_token' => !empty($m->uses_staff_portal_token),
                    ];
                    $cbp_field_prefix = 'e' . (int) $m->id;
                    $this->load->view('partials/cbp_module_resolver_fields', [
                      'cbp_vals' => $cbp_vals,
                      'cbp_field_prefix' => $cbp_field_prefix,
                      'resolver_options' => $resolver_options,
                    ]);
                    ?>

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

    <div class="modal fade" id="cbpModuleCreateModal" tabindex="-1" aria-labelledby="cbpModuleCreateModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <?= form_open('settings/cbp_modules_create', ['class' => 'cbp-module-form']) ?>
            <div class="modal-header">
              <h5 class="modal-title" id="cbpModuleCreateModalLabel">Add CBP module</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p class="text-muted small">Unique <strong>module key</strong> (e.g. <code>learning_hub</code>) — cannot be changed later.</p>
              <div class="alert alert-light border small mb-3 mb-md-0 py-2">
                A new row in <code>permissions</code> will be created (next ID is typically <strong><?= (int) $next_permission_id_hint ?></strong>, based on current max).
                It will be linked to this module and <strong>automatically assigned to the admin group</strong> (role / group ID <strong>10</strong>).
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Module key <span class="text-danger">*</span></label>
                  <input type="text" name="module_key" class="form-control" required maxlength="64" pattern="[A-Za-z][A-Za-z0-9_]*"
                         placeholder="e.g. learning_hub" title="Letter, then letters, digits, or underscores (stored lowercase)">
                </div>
                <div class="col-md-6">
                  <label class="form-label">System name <span class="text-danger">*</span></label>
                  <input type="text" name="system_name" class="form-control" required maxlength="191" placeholder="Display name">
                </div>
                <div class="col-12">
                  <label class="form-label">Description</label>
                  <textarea name="description" class="form-control" rows="2" placeholder="Home tile / menu tooltip"></textarea>
                </div>

                <?php
                $cbp_vals = [
                  'target_resolver' => 'codeigniter',
                  'base_url' => '',
                  'base_url_development' => '',
                  'base_url_production' => '',
                  'alternate_base_url' => '',
                  'alternate_for_role_id' => '',
                  'uses_staff_portal_token' => false,
                ];
                $cbp_field_prefix = 'new';
                $this->load->view('partials/cbp_module_resolver_fields', [
                  'cbp_vals' => $cbp_vals,
                  'cbp_field_prefix' => $cbp_field_prefix,
                  'resolver_options' => $resolver_options,
                ]);
                ?>

                <div class="col-md-6">
                  <label class="form-label">Icon (Font Awesome)</label>
                  <select name="icon_class" class="form-select">
                    <?php foreach ($icon_options as $ic => $ilab) : ?>
                      <option value="<?= htmlspecialchars($ic) ?>" <?= $ic === 'fa-th' ? 'selected' : '' ?>><?= htmlspecialchars($ilab . ' (' . $ic . ')') ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Sort order</label>
                  <input type="number" name="sort_order" class="form-control" value="<?= (int) $next_sort_order ?>">
                </div>
                <div class="col-12">
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="is_production" value="1" id="create-prod" checked>
                    <label class="form-check-label" for="create-prod">Visible to all permitted users (unchecked = role 10 only)</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="create-en" checked>
                    <label class="form-check-label" for="create-en">Enabled</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="show_in_apm_menu" value="1" id="create-apm">
                    <label class="form-check-label" for="create-apm">Show in APM top menu</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i>Create module</button>
            </div>
          <?= form_close() ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  function applyCbpResolver(form) {
    var sel = form.querySelector('.js-cbp-resolver-select');
    if (!sel) return;
    var v = sel.value;
    form.querySelectorAll('[data-cbp-for]').forEach(function (el) {
      var keys = (el.getAttribute('data-cbp-for') || '').split(/\s+/).filter(Boolean);
      el.classList.toggle('d-none', keys.indexOf(v) === -1);
    });
    form.querySelectorAll('.cbp-hint').forEach(function (h) {
      var hintFor = h.getAttribute('data-cbp-hint');
      h.classList.toggle('d-none', hintFor !== v);
    });
    var isFin = v === 'finance_host';
    var isExt = v === 'external_microservice';
    form.querySelectorAll('.cbp-lbl-dev-finance').forEach(function (el) { el.classList.toggle('d-none', !isFin); });
    form.querySelectorAll('.cbp-lbl-dev-ext').forEach(function (el) { el.classList.toggle('d-none', !isExt); });
    form.querySelectorAll('.cbp-lbl-prod-finance').forEach(function (el) { el.classList.toggle('d-none', !isFin); });
    form.querySelectorAll('.cbp-lbl-prod-ext').forEach(function (el) { el.classList.toggle('d-none', !isExt); });
    form.querySelectorAll('.cbp-env-finance').forEach(function (el) { el.classList.toggle('d-none', !isFin); });
    form.querySelectorAll('.cbp-env-ext').forEach(function (el) { el.classList.toggle('d-none', !isExt); });
    form.querySelectorAll('.cbp-help-finance').forEach(function (el) { el.classList.toggle('d-none', !isFin); });
    form.querySelectorAll('.cbp-help-ext').forEach(function (el) { el.classList.toggle('d-none', !isExt); });
  }
  function bindForm(form) {
    var sel = form.querySelector('.js-cbp-resolver-select');
    if (sel) {
      sel.addEventListener('change', function () { applyCbpResolver(form); });
    }
    applyCbpResolver(form);
  }
  document.querySelectorAll('.cbp-module-form').forEach(bindForm);
  document.getElementById('cbpModuleCreateModal') && document.getElementById('cbpModuleCreateModal').addEventListener('shown.bs.modal', function () {
    var f = this.querySelector('.cbp-module-form');
    if (f) applyCbpResolver(f);
  });
})();
</script>
