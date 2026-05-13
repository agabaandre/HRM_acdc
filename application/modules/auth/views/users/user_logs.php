<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$extended_audit = !empty($extended_audit);
$integrity_audit = !empty($integrity_audit);
$iso_retention_guidance_days = isset($iso_retention_guidance_days) ? (int) $iso_retention_guidance_days : 365;
$total_filtered = isset($total_filtered) ? (int) $total_filtered : 0;
$log_filter_lists = isset($log_filter_lists) && is_array($log_filter_lists) ? $log_filter_lists : array('http_methods' => array(), 'event_types' => array());
$revert_whitelist_tables = isset($revert_whitelist_tables) && is_array($revert_whitelist_tables) ? $revert_whitelist_tables : array();
$CI =& get_instance();
$get = $CI->input->get();
if (!is_array($get)) {
  $get = array();
}

function staff_logs_ts($row) {
  if (!empty($row->created_at)) {
    return strtotime($row->created_at);
  }
  if (!empty($row->date_loged_in)) {
    return strtotime($row->date_loged_in);
  }
  return false;
}

function staff_logs_method_badge($m) {
  $m = strtoupper((string) $m);
  $cls = 'bg-secondary';
  if ($m === 'GET') $cls = 'bg-info';
  elseif ($m === 'POST') $cls = 'bg-primary';
  elseif ($m === 'PUT' || $m === 'PATCH') $cls = 'bg-warning text-dark';
  elseif ($m === 'DELETE') $cls = 'bg-danger';
  return '<span class="badge ' . $cls . '">' . htmlspecialchars($m ?: '—', ENT_QUOTES, 'UTF-8') . '</span>';
}

function staff_logs_event_badge($ev) {
  $ev = strtolower((string) $ev);
  $cls = 'bg-secondary';
  if (strpos($ev, 'record_') === 0) $cls = 'bg-dark';
  elseif ($ev === 'access') $cls = 'bg-info';
  elseif ($ev === 'submit') $cls = 'bg-primary';
  elseif ($ev === 'update') $cls = 'bg-warning text-dark';
  elseif ($ev === 'delete') $cls = 'bg-danger';
  elseif ($ev === 'audit_repository') $cls = 'bg-secondary';
  return '<span class="badge ' . $cls . '">' . htmlspecialchars($ev ?: '—', ENT_QUOTES, 'UTF-8') . '</span>';
}
?>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-header bg-white border-bottom py-3">
    <h4 class="mb-0 text-dark"><?php echo htmlspecialchars($title); ?></h4>
    <p class="text-muted small mb-0">Staff portal activity and access log.</p>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border-primary h-100">
          <div class="card-body text-center py-3">
            <div class="text-primary small text-uppercase">Matching rows</div>
            <h3 class="mb-0 text-primary"><?php echo number_format($total_filtered); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-success h-100">
          <div class="card-body text-center py-3">
            <div class="text-success small text-uppercase">This page</div>
            <h3 class="mb-0 text-success"><?php echo isset($logs) ? count($logs) : 0; ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-6 d-flex align-items-center">
        <?php if (!$extended_audit): ?>
          <div class="alert alert-warning mb-0 w-100">
            <strong>Limited mode:</strong> Extended columns are not installed yet. Filters for method/event and structured revert require the SQL upgrade.
          </div>
        <?php else: ?>
          <div class="alert alert-info mb-0 w-100">
            <strong>POST / PUT / PATCH / DELETE:</strong> redacted request fields are stored under <code>new_values._http_request</code>. User admin updates log <code>old_values</code> / <code>new_values</code> for revert when the table is whitelisted.
            <?php if ($integrity_audit): ?>
              <span class="d-block mt-2"><strong>Integrity:</strong> new rows are sealed with a SHA-256 hash chain (MySQL <code>GET_LOCK</code> serializes writers). Retain logs for at least <strong><?php echo (int) $iso_retention_guidance_days; ?></strong> days per configured guidance; purge only via controlled DBA/backup policy.</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header bg-light">
        <h6 class="mb-0"><i class="bx bx-filter-alt me-1"></i> Filters</h6>
      </div>
      <div class="card-body">
        <form class="row g-3" method="get" action="<?php echo base_url('auth/logs'); ?>">
          <div class="col-md-3">
            <label class="form-label">Search</label>
            <input type="text" class="form-control" name="search" placeholder="Action, URI, table…" value="<?php echo isset($get['search']) ? htmlspecialchars($get['search']) : ''; ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">User name</label>
            <input type="text" class="form-control" name="name" value="<?php echo isset($get['name']) ? htmlspecialchars($get['name']) : ''; ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Email</label>
            <input type="text" class="form-control" name="email" value="<?php echo isset($get['email']) ? htmlspecialchars($get['email']) : ''; ?>">
          </div>
          <?php if ($extended_audit): ?>
          <div class="col-md-2">
            <label class="form-label">HTTP method</label>
            <select class="form-select" name="http_method">
              <option value="">All</option>
              <?php foreach ($log_filter_lists['http_methods'] as $hm): ?>
                <option value="<?php echo htmlspecialchars($hm); ?>" <?php echo (isset($get['http_method']) && $get['http_method'] === $hm) ? 'selected' : ''; ?>><?php echo htmlspecialchars($hm); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Event</label>
            <select class="form-select" name="event_type">
              <option value="">All</option>
              <?php foreach ($log_filter_lists['event_types'] as $et): ?>
                <option value="<?php echo htmlspecialchars($et); ?>" <?php echo (isset($get['event_type']) && $get['event_type'] === $et) ? 'selected' : ''; ?>><?php echo htmlspecialchars($et); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-2">
            <label class="form-label">From</label>
            <input type="text" class="form-control datepicker" name="date_from" value="<?php echo isset($get['date_from']) ? htmlspecialchars($get['date_from']) : ''; ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">To</label>
            <input type="text" class="form-control datepicker" name="date_to" value="<?php echo isset($get['date_to']) ? htmlspecialchars($get['date_to']) : ''; ?>">
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bx bx-search"></i> Apply</button>
            <a href="<?php echo base_url('auth/logs'); ?>" class="btn btn-secondary">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <?php echo isset($links) ? $links : ''; ?>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">ID</th>
            <?php if ($extended_audit): ?>
              <th style="width:90px;">Method</th>
              <th style="width:110px;">Event</th>
              <th>URI</th>
              <th>Target</th>
            <?php endif; ?>
            <th>User</th>
            <th>When</th>
            <th style="width:120px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="<?php echo $extended_audit ? 8 : 4; ?>" class="text-center text-muted py-4">No logs found.</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $logEntry): ?>
              <?php
                $ts = staff_logs_ts($logEntry);
                $dt = $ts ? date('Y-m-d H:i', $ts) : '—';
                $canRevert = $extended_audit
                  && !empty($logEntry->old_values)
                  && empty($logEntry->reverted_at)
                  && !empty($logEntry->target_table)
                  && in_array($logEntry->target_table, $revert_whitelist_tables, true);
              ?>
              <tr>
                <td><span class="badge bg-secondary">#<?php echo (int) $logEntry->id; ?></span></td>
                <?php if ($extended_audit): ?>
                  <td><?php echo staff_logs_method_badge(isset($logEntry->http_method) ? $logEntry->http_method : ''); ?></td>
                  <td><?php echo staff_logs_event_badge(isset($logEntry->event_type) ? $logEntry->event_type : ''); ?></td>
                  <td class="small text-break"><code><?php echo htmlspecialchars(isset($logEntry->request_uri) ? $logEntry->request_uri : '—', ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td class="small">
                    <?php if (!empty($logEntry->target_table)): ?>
                      <code><?php echo htmlspecialchars($logEntry->target_table, ENT_QUOTES, 'UTF-8'); ?></code>
                      <?php if ($logEntry->target_id !== null && $logEntry->target_id !== ''): ?>
                        <span class="text-muted">#<?php echo htmlspecialchars((string) $logEntry->target_id, ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td>
                  <div class="fw-semibold"><?php echo htmlspecialchars(isset($logEntry->name) ? $logEntry->name : '—', ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="small text-muted"><?php echo htmlspecialchars(isset($logEntry->work_email) ? $logEntry->work_email : '', ENT_QUOTES, 'UTF-8'); ?></div>
                </td>
                <td class="small"><?php echo htmlspecialchars($dt, ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#logModal<?php echo (int) $logEntry->id; ?>">Details</button>
                  <?php if ($canRevert): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-revert-log" data-log-id="<?php echo (int) $logEntry->id; ?>">Revert</button>
                  <?php endif; ?>
                </td>
              </tr>

              <div class="modal fade" id="logModal<?php echo (int) $logEntry->id; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Log #<?php echo (int) $logEntry->id; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <p class="small text-muted mb-1">IP: <?php echo htmlspecialchars(isset($logEntry->ip_address) ? $logEntry->ip_address : '', ENT_QUOTES, 'UTF-8'); ?></p>
                      <p class="small"><strong>User-Agent:</strong> <?php echo htmlspecialchars(isset($logEntry->user_agent) ? $logEntry->user_agent : '', ENT_QUOTES, 'UTF-8'); ?></p>
                      <hr>
                      <h6>Action</h6>
                      <p class="mb-3"><?php echo nl2br(htmlspecialchars(isset($logEntry->action) ? $logEntry->action : '', ENT_QUOTES, 'UTF-8')); ?></p>
                      <?php if ($extended_audit && (!empty($logEntry->old_values) || !empty($logEntry->new_values))): ?>
                        <div class="row g-2">
                          <div class="col-md-6">
                            <h6 class="text-danger">old_values (JSON)</h6>
                            <pre class="bg-light border rounded p-2 small" style="max-height:240px;overflow:auto;"><?php echo htmlspecialchars($logEntry->old_values ?: '—', ENT_QUOTES, 'UTF-8'); ?></pre>
                          </div>
                          <div class="col-md-6">
                            <h6 class="text-success">new_values (JSON)</h6>
                            <pre class="bg-light border rounded p-2 small" style="max-height:240px;overflow:auto;"><?php echo htmlspecialchars($logEntry->new_values ?: '—', ENT_QUOTES, 'UTF-8'); ?></pre>
                          </div>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($logEntry->reverted_at)): ?>
                        <div class="alert alert-secondary mt-2 mb-0 small">Reverted at <?php echo htmlspecialchars($logEntry->reverted_at, ENT_QUOTES, 'UTF-8'); ?>
                          <?php if (!empty($logEntry->reverted_by_user_id)): ?> by user #<?php echo (int) $logEntry->reverted_by_user_id; ?><?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                      <?php if ($canRevert): ?>
                        <button type="button" class="btn btn-danger btn-revert-log" data-log-id="<?php echo (int) $logEntry->id; ?>">Revert from snapshot</button>
                      <?php endif; ?>
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-end">
      <?php echo isset($links) ? $links : ''; ?>
    </div>
  </div>
</div>

<input type="hidden" id="revertCsrfName" value="<?php echo htmlspecialchars($CI->security->get_csrf_token_name(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" id="revertCsrfHash" value="<?php echo htmlspecialchars($CI->security->get_csrf_hash(), ENT_QUOTES, 'UTF-8'); ?>">

<script>
(function () {
  var revertUrl = '<?php echo base_url('auth/revert_log'); ?>';
  function postRevert(logId) {
    if (!confirm('Revert this change using the stored old_values snapshot? This updates the live database row.')) {
      return;
    }
    var nameEl = document.getElementById('revertCsrfName');
    var hashEl = document.getElementById('revertCsrfHash');
    var body = new URLSearchParams();
    body.append('log_id', String(logId));
    if (nameEl && hashEl) {
      body.append(nameEl.value, hashEl.value);
    }
    fetch(revertUrl, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Invalid JSON response' }; }); })
      .then(function (data) {
        if (data && data.ok) {
          alert(data.message || 'Reverted');
          window.location.reload();
        } else {
          alert((data && data.message) ? data.message : 'Revert failed');
        }
      }).catch(function () { alert('Network error'); });
  }
  document.querySelectorAll('.btn-revert-log').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-log-id');
      if (id) postRevert(id);
    });
  });
})();
</script>
