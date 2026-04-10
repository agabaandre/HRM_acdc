<?php
/** @var array $schedule */
/** @var string $schedule_path */
/** @var array $daily_jobs_meta */
/** @var array $instant_jobs */
$s = $schedule;
?>
<div class="row mb-3">
	<div class="col-md-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
		<div>
			<a href="<?= base_url('settings') ?>" class="text-decoration-none"><i class="fa fa-arrow-left"></i> Settings</a>
			<h4 class="mt-2 mb-0">Staff jobs</h4>
			<p class="text-muted small mb-0">Adjust cron times used by <code>jobs/run/tick</code> and run jobs once (requires server crontab every minute for scheduled runs).</p>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-7 mb-4">
		<div class="card card-default">
			<div class="card-header">
				<h5 class="card-title mb-0">Cron schedule</h5>
				<p class="small text-muted mb-0">Saved to <code><?= htmlspecialchars(str_replace(APPPATH, 'application/', $schedule_path), ENT_QUOTES, 'UTF-8') ?></code></p>
			</div>
			<div class="card-body">
				<?= form_open('settings/staff_jobs_save_schedule'); ?>
				<div class="form-check mb-3">
					<input class="form-check-input" type="checkbox" name="send_instant_mails" value="1" id="send_instant_mails" <?= !empty($s['send_instant_mails']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="send_instant_mails">Run instant mail queue every minute tick</label>
				</div>
				<div class="row mb-3">
					<div class="col-sm-6">
						<label class="form-label">Full mail queue interval (minutes)</label>
						<input type="number" name="send_mails_interval_minutes" class="form-control" min="0" max="1440" value="<?= (int) ($s['send_mails_interval_minutes'] ?? 0) ?>">
						<small class="text-muted">0 = disabled during tick.</small>
					</div>
					<div class="col-sm-6">
						<label class="form-label">Manage accounts (hourly at minute)</label>
						<select name="manage_accounts_hourly_minute" class="form-control">
							<option value="">Disabled</option>
							<?php for ($m = 0; $m <= 59; $m++) :
								$sel = isset($s['manage_accounts_hourly_minute']) && $s['manage_accounts_hourly_minute'] !== null && (int) $s['manage_accounts_hourly_minute'] === $m;
								?>
								<option value="<?= $m ?>" <?= $sel ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
							<?php endfor; ?>
						</select>
					</div>
				</div>
				<hr>
				<h6 class="text-muted">Daily jobs (once per day at local server time)</h6>
				<?php foreach ($daily_jobs_meta as $jobKey => $meta) :
					$spec = $s[$jobKey] ?? false;
					$enabled = $spec !== false && is_array($spec);
					$h = $enabled ? (int) $spec['hour'] : 8;
					$m = $enabled ? (int) $spec['minute'] : 0;
					?>
					<div class="border rounded p-3 mb-3">
						<div class="form-check mb-2">
							<input class="form-check-input" type="checkbox" name="<?= htmlspecialchars($jobKey, ENT_QUOTES, 'UTF-8') ?>_enabled" value="1" id="en_<?= htmlspecialchars($jobKey, ENT_QUOTES, 'UTF-8') ?>" <?= $enabled ? 'checked' : '' ?>>
							<label class="form-check-label fw-semibold" for="en_<?= htmlspecialchars($jobKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></label>
						</div>
						<p class="small text-muted mb-2"><?= htmlspecialchars($meta['help'], ENT_QUOTES, 'UTF-8') ?></p>
						<div class="row">
							<div class="col-4">
								<label class="form-label small">Hour</label>
								<select name="<?= htmlspecialchars($jobKey, ENT_QUOTES, 'UTF-8') ?>_hour" class="form-control form-control-sm">
									<?php for ($hh = 0; $hh <= 23; $hh++) : ?>
										<option value="<?= $hh ?>" <?= $hh === $h ? 'selected' : '' ?>><?= sprintf('%02d', $hh) ?></option>
									<?php endfor; ?>
								</select>
							</div>
							<div class="col-4">
								<label class="form-label small">Minute</label>
								<select name="<?= htmlspecialchars($jobKey, ENT_QUOTES, 'UTF-8') ?>_minute" class="form-control form-control-sm">
									<?php for ($mm = 0; $mm <= 59; $mm++) : ?>
										<option value="<?= $mm ?>" <?= $mm === $m ? 'selected' : '' ?>><?= sprintf('%02d', $mm) ?></option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
				<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save schedule</button>
				<?= form_close(); ?>
			</div>
		</div>
	</div>
	<div class="col-lg-5 mb-4">
		<div class="card card-default">
			<div class="card-header">
				<h5 class="card-title mb-0">Run now</h5>
				<p class="small text-muted mb-0">Executes the job once in this request (same code paths as CLI jobs).</p>
			</div>
			<div class="card-body">
				<ul class="list-group list-group-flush">
					<?php foreach ($instant_jobs as $runKey => $def) : ?>
						<li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 px-0">
							<span><?= htmlspecialchars($def['label'], ENT_QUOTES, 'UTF-8') ?></span>
							<?= form_open('settings/staff_jobs_run_now', ['class' => 'd-inline']); ?>
							<input type="hidden" name="job_key" value="<?= htmlspecialchars($runKey, ENT_QUOTES, 'UTF-8') ?>">
							<button type="submit" class="btn btn-sm btn-outline-primary">Run</button>
							<?= form_close(); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
</div>
