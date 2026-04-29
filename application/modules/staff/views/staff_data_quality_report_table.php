<?php
$page = isset($page) ? (int) $page : 0;
$per_page = isset($per_page) ? (int) $per_page : 20;
$row_number = ($page * $per_page) + 1;
?>
<?php if (empty($rows)) : ?>
<tr>
	<td colspan="4" class="text-center text-muted">No data quality alerts found for the selected filters.</td>
</tr>
<?php else : ?>
	<?php foreach ($rows as $row) : ?>
	<tr>
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
			<a href="<?= base_url('staff/staff_contracts/' . (int) ($row->staff_id ?? 0)) ?>">
				<?= htmlspecialchars((string) ($row->full_name ?? 'Unknown')) ?>
			</a>
		</td>
		<td>
			<ul class="mb-0 ps-3">
				<?php foreach ((array) ($row->data_quality_alerts ?? []) as $alert) : ?>
					<li><?= htmlspecialchars((string) $alert) ?></li>
				<?php endforeach; ?>
			</ul>
		</td>
	</tr>
	<?php endforeach; ?>
<?php endif; ?>
