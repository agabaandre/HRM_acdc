<?php
$page = isset($page) ? (int) $page : 0;
$per_page = isset($per_page) ? (int) $per_page : 20;
$row_number = ($page * $per_page) + 1;
?>
<?php if (empty($rows)) : ?>
<tr>
	<td colspan="7" class="text-center text-muted">No staff found for the selected filters.</td>
</tr>
<?php else : ?>
	<?php foreach ($rows as $row) : ?>
	<?php
	$staff_id = (int) ($row->staff_id ?? 0);
	$status = (string) ($row->signature_status ?? 'missing');
	$is_valid = ($status === 'valid');
	$sig_url = $is_valid ? staff_secure_upload_url('signature', $row->signature ?? '') : '';
	$sig_text = htmlspecialchars((string) ($row->signature_text ?? ''), ENT_QUOTES, 'UTF-8');
	?>
	<tr
		class="sig-manager-row"
		data-staff-id="<?= $staff_id ?>"
		data-signature-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
		data-signature-text="<?= $sig_text ?>"
	>
		<td>
			<?php if (!$is_valid) : ?>
				<input type="checkbox" class="form-check-input sig-manager-select" value="<?= $staff_id ?>" aria-label="Select for bulk generate">
			<?php else : ?>
				<input type="checkbox" class="form-check-input sig-manager-select sig-manager-select-override d-none" value="<?= $staff_id ?>" aria-label="Select for bulk replace" disabled>
			<?php endif; ?>
		</td>
		<td><?= $row_number++ ?></td>
		<td>
			<?php
			$surname = $row->lname ?? '';
			$other_name = $row->fname ?? '';
			$image_path = staff_secure_upload_url('photo', $row->photo ?? '');
			echo generate_user_avatar($surname, $other_name, $image_path, $row->photo ?? null);
			?>
		</td>
		<td>
			<a href="<?= base_url('staff/staff_contracts/' . $staff_id) ?>">
				<?= htmlspecialchars((string) ($row->full_name ?? 'Unknown')) ?>
			</a>
			<?php if (!empty($row->SAPNO)) : ?>
				<div class="small text-muted">SAP: <?= htmlspecialchars((string) $row->SAPNO) ?> · ID: <?= $staff_id ?></div>
			<?php else : ?>
				<div class="small text-muted">Staff ID: <?= $staff_id ?></div>
			<?php endif; ?>
		</td>
		<td>
			<?php if ($status === 'valid') : ?>
				<span class="badge bg-success">Valid</span>
			<?php elseif ($status === 'broken') : ?>
				<span class="badge bg-warning text-dark">File missing</span>
			<?php else : ?>
				<span class="badge bg-danger">Missing</span>
			<?php endif; ?>
		</td>
		<td class="sig-manager-preview-cell">
			<?php if ($is_valid && $sig_url !== '') : ?>
				<div class="sig-manager-preview-box sig-manager-preview-saved" data-saved-preview-for="<?= $staff_id ?>">
					<img src="<?= htmlspecialchars($sig_url) ?>" alt="Signature" loading="lazy">
				</div>
				<div class="sig-manager-preview-box sig-manager-preview-generated d-none" data-preview-for="<?= $staff_id ?>">
					<img src="" alt="Generated signature preview" class="sig-manager-generated-img">
				</div>
			<?php else : ?>
				<div class="sig-manager-preview-box sig-manager-preview-generated d-none" data-preview-for="<?= $staff_id ?>">
					<img src="" alt="Generated signature preview" class="sig-manager-generated-img">
				</div>
				<span class="text-muted small sig-manager-preview-placeholder" data-placeholder-for="<?= $staff_id ?>">Not generated</span>
			<?php endif; ?>
		</td>
		<td>
			<div class="d-flex flex-column gap-1">
				<?php if ($is_valid) : ?>
					<div class="form-check mb-0">
						<input type="checkbox" class="form-check-input sig-manager-override" id="sigOverride<?= $staff_id ?>" data-staff-id="<?= $staff_id ?>" aria-label="Replace existing signature">
						<label class="form-check-label small" for="sigOverride<?= $staff_id ?>">Replace existing</label>
					</div>
				<?php endif; ?>
				<button type="button" class="btn btn-sm btn-outline-primary sig-manager-generate-one" data-staff-id="<?= $staff_id ?>"<?= $is_valid ? ' disabled' : '' ?>>
					<i class="fa fa-pen-nib me-1"></i> Generate
				</button>
				<button type="button" class="btn btn-sm btn-outline-secondary sig-manager-upload-btn" data-staff-id="<?= $staff_id ?>"<?= $is_valid ? ' disabled' : '' ?>>
					<i class="fa fa-file-image me-1"></i> Upload file
				</button>
				<input type="file" class="d-none sig-manager-upload-input" accept="image/*" data-staff-id="<?= $staff_id ?>"<?= $is_valid ? ' disabled' : '' ?>>
			</div>
		</td>
	</tr>
	<?php endforeach; ?>
<?php endif; ?>
