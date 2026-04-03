<?php
defined('BASEPATH') or exit('No direct script access allowed');
$vals = isset($cbp_vals) && is_array($cbp_vals) ? $cbp_vals : [];
$p = preg_replace('/[^a-z0-9_-]/', '', (string) ($cbp_field_prefix ?? 'f'));
$v = function (string $key, $default = '') use ($vals) {
	return htmlspecialchars((string) ($vals[$key] ?? $default));
};
?>
<div class="col-12">
  <label class="form-label fw-semibold">Link target</label>
  <select name="target_resolver" class="form-select js-cbp-resolver-select" required>
    <?php foreach ($resolver_options as $val => $lab) : ?>
      <option value="<?= htmlspecialchars($val) ?>" <?= ($vals['target_resolver'] ?? '') === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div class="col-12 cbp-resolver-panel border rounded p-3 mb-2" data-cbp-for="codeigniter staff_app_token external_microservice">
  <div class="fw-semibold small text-muted mb-2">Path or URL (Staff host or external fallback)</div>
  <label class="form-label mb-0"><code>base_url</code> / main segment</label>
  <input type="text" name="base_url" class="form-control" maxlength="512" value="<?= $v('base_url') ?>"
         placeholder="dashboard, apm, or https://service.example.org">
  <div class="form-text small">
    <span class="cbp-hint" data-cbp-hint="codeigniter">Internal route under this Staff app (e.g. <code>dashboard</code>). No leading slash.</span>
    <span class="cbp-hint d-none" data-cbp-hint="staff_app_token">Segment under the Staff base URL; a session token is appended (same idea as APM).</span>
    <span class="cbp-hint d-none" data-cbp-hint="external_microservice">Optional <strong>single full URL</strong> for every environment, or leave empty and use development / production URLs below.</span>
  </div>
</div>

<div class="col-12 cbp-resolver-panel border rounded p-3 mb-2" data-cbp-for="codeigniter">
  <div class="fw-semibold small text-muted mb-2">Optional alternate path by role</div>
  <div class="row g-2">
    <div class="col-md-6">
      <label class="form-label mb-0">Alternate path</label>
      <input type="text" name="alternate_base_url" class="form-control" maxlength="255" value="<?= $v('alternate_base_url') ?>" placeholder="e.g. auth/profile">
    </div>
    <div class="col-md-6">
      <label class="form-label mb-0">Only for role ID</label>
      <input type="number" name="alternate_for_role_id" class="form-control" min="0" value="<?= isset($vals['alternate_for_role_id']) && $vals['alternate_for_role_id'] !== '' && $vals['alternate_for_role_id'] !== null ? (int) $vals['alternate_for_role_id'] : '' ?>" placeholder="e.g. 17">
    </div>
  </div>
</div>

<div class="col-12 cbp-resolver-panel border rounded p-3 mb-2" data-cbp-for="finance_host external_microservice">
  <div class="fw-semibold small text-muted mb-2 cbp-env-urls-title">Environment-specific URLs</div>
  <p class="small text-muted mb-2 cbp-env-finance">When users open the Staff portal on <strong>localhost</strong> or <strong>127.0.0.1</strong>, the development URL is used; otherwise production rules apply.</p>
  <p class="small text-muted mb-2 cbp-env-ext d-none">Same host detection: local Staff portal → development microservice URL; production Staff → production microservice URL.</p>
  <div class="row g-2">
    <div class="col-md-6">
      <label class="form-label mb-0 cbp-lbl-dev-finance">Finance — development base URL</label>
      <label class="form-label mb-0 cbp-lbl-dev-ext d-none">Microservice — development URL</label>
      <input type="text" name="base_url_development" class="form-control" maxlength="512" value="<?= $v('base_url_development') ?>" placeholder="http://localhost:3002 or https://dev-api.example.org">
    </div>
    <div class="col-md-6">
      <label class="form-label mb-0 cbp-lbl-prod-finance">Finance — production path or URL</label>
      <label class="form-label mb-0 cbp-lbl-prod-ext d-none">Microservice — production URL</label>
      <input type="text" name="base_url_production" class="form-control" maxlength="512" value="<?= $v('base_url_production') ?>" placeholder="finance or https://ms.example.org">
      <div class="form-text small cbp-help-finance">Leave production empty to use <code>/finance</code> on the current host (Finance only).</div>
      <div class="form-text small cbp-help-ext d-none">Full <code>https://</code> URL recommended for production microservices.</div>
    </div>
  </div>
</div>

<div class="col-12 cbp-resolver-panel" data-cbp-for="codeigniter staff_app_token finance_host external_microservice">
  <div class="form-check">
    <input class="form-check-input" type="checkbox" name="uses_staff_portal_token" value="1" id="cbp-tok-<?= $p ?>" <?= !empty($vals['uses_staff_portal_token']) ? 'checked' : '' ?>>
    <label class="form-check-label small" for="cbp-tok-<?= $p ?>">Append Staff portal session token (SSO)</label>
  </div>
  <div class="form-text small text-muted">Adds <code>?token=…</code> or <code>&amp;token=…</code> with the same payload as APM/Finance. The target service must validate it. For <strong>Staff portal path</strong>, leave unchecked unless the route expects a token. External microservices must implement verification on their side.</div>
</div>
