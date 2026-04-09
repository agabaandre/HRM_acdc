<?php
$kin_name_by_id = isset($kin_name_by_id) && is_array($kin_name_by_id) ? $kin_name_by_id : [];
$rows = isset($rows) ? $rows : [];

if (!function_exists('staff_nok_report_normalize_row')) {
	function staff_nok_report_normalize_row($row) {
		$out = ['name' => '', 'relationship_id' => 0, 'phone' => '', 'email' => ''];
		if (!is_array($row)) {
			return $out;
		}
		$out['name'] = trim((string) ($row['name'] ?? ''));
		$out['relationship_id'] = (int) ($row['relationship_id'] ?? 0);
		$out['phone'] = trim((string) ($row['phone'] ?? ''));
		$out['email'] = trim((string) ($row['email'] ?? ''));
		if ($out['phone'] === '' && $out['email'] === '' && !empty($row['contact'])) {
			$c = trim((string) $row['contact']);
			if ($c !== '' && strpos($c, '@') !== false) {
				$out['email'] = $c;
			} elseif ($c !== '') {
				$out['phone'] = $c;
			}
		}
		return $out;
	}
}

if (empty($rows)) : ?>
	<p class="text-muted mb-0">No matching staff records.</p>
<?php else :
foreach ($rows as $s) :
	$nok_raw = json_decode(isset($s->next_of_kin_json) ? $s->next_of_kin_json : '[]', true);
	if (!is_array($nok_raw)) {
		$nok_raw = [];
	}
	$nok_rows = [];
	foreach ($nok_raw as $nr) {
		$nok_rows[] = staff_nok_report_normalize_row($nr);
	}
	$display_nok = array_values(array_filter($nok_rows, function ($r) {
		return $r['name'] !== '' || $r['phone'] !== '' || $r['email'] !== '' || $r['relationship_id'] > 0;
	}));
	$full_name = trim(($s->title ?? '') . ' ' . ($s->fname ?? '') . ' ' . ($s->lname ?? '') . ' ' . ($s->oname ?? ''));
	?>
	<div class="card border mb-3 shadow-sm">
		<div class="card-header bg-light py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
			<div>
				<strong>
					<a href="<?= base_url('staff/staff_contracts/' . (int) $s->staff_id) ?>"><?= htmlspecialchars(trim($full_name)) ?></a>
				</strong>
				<?php if (!empty($s->SAPNO)) : ?>
					<span class="text-muted ms-2">SAP: <?= htmlspecialchars((string) $s->SAPNO) ?></span>
				<?php endif; ?>
			</div>
			<span class="badge bg-secondary"><?= htmlspecialchars((string) ($s->contract_status_label ?? '')) ?></span>
		</div>
		<div class="card-body">
			<div class="row g-3 small mb-3">
				<div class="col-md-4">
					<div class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Assignment</div>
					<div><?= htmlspecialchars((string) ($s->job_name ?? '—')) ?></div>
					<div class="text-muted"><?= htmlspecialchars((string) ($s->division_name ?? '—')) ?></div>
					<div class="text-muted"><?= htmlspecialchars((string) ($s->duty_station_name ?? '—')) ?></div>
					<?php if (!empty($s->grade)) : ?>
						<div class="text-muted">Grade: <?= htmlspecialchars((string) $s->grade) ?></div>
					<?php endif; ?>
				</div>
				<div class="col-md-4">
					<div class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Contact</div>
					<div><span class="text-muted">Work email:</span> <?= htmlspecialchars((string) ($s->work_email ?? '—')) ?></div>
					<div><span class="text-muted">Tel 1:</span> <?= htmlspecialchars((string) ($s->tel_1 ?? '—')) ?></div>
					<div><span class="text-muted">Tel 2:</span> <?= htmlspecialchars((string) ($s->tel_2 ?? '—')) ?></div>
					<div><span class="text-muted">WhatsApp:</span> <?= htmlspecialchars((string) ($s->whatsapp ?? '—')) ?></div>
					<div><span class="text-muted">Private email:</span> <?= htmlspecialchars((string) ($s->private_email ?? '—')) ?></div>
					<?php if (!empty($s->physical_location)) : ?>
						<div><span class="text-muted">Physical / office:</span> <?= nl2br(htmlspecialchars((string) $s->physical_location)) ?></div>
					<?php endif; ?>
				</div>
				<div class="col-md-4">
					<div class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Address &amp; household</div>
					<?php if (property_exists($s, 'residential_address_duty_station')) : ?>
						<div class="mb-1"><?= nl2br(htmlspecialchars(trim((string) ($s->residential_address_duty_station ?? ''))) ?: '—') ?></div>
					<?php else : ?>
						<div class="text-muted">—</div>
					<?php endif; ?>
					<?php if (property_exists($s, 'number_of_dependants')) : ?>
						<div><span class="text-muted">Dependants:</span>
							<?= isset($s->number_of_dependants) && $s->number_of_dependants !== null && $s->number_of_dependants !== '' ? (int) $s->number_of_dependants : '—' ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="text-uppercase text-muted fw-bold small mb-2">Next of kin</div>
			<?php if (empty($display_nok)) : ?>
				<p class="text-muted small mb-0">No next-of-kin details on file.</p>
			<?php else : ?>
				<div class="table-responsive">
					<table class="table table-sm table-bordered mb-0">
						<thead class="table-light">
							<tr>
								<th>Name</th>
								<th>Relationship</th>
								<th>Phone</th>
								<th>Email</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($display_nok as $k) :
								$rel = $k['relationship_id'] > 0 && isset($kin_name_by_id[$k['relationship_id']])
									? $kin_name_by_id[$k['relationship_id']]
									: '—';
								?>
								<tr>
									<td><?= htmlspecialchars($k['name'] ?: '—') ?></td>
									<td><?= htmlspecialchars($rel) ?></td>
									<td><?= htmlspecialchars($k['phone'] ?: '—') ?></td>
									<td><?= htmlspecialchars($k['email'] ?: '—') ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
<?php endforeach;
endif; ?>
