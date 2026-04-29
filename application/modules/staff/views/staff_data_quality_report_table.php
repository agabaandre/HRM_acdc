<?php if (empty($rows)) : ?>
<tr>
	<td colspan="3" class="text-center text-muted">No data quality alerts found for the selected filters.</td>
</tr>
<?php else : ?>
	<?php foreach ($rows as $row) : ?>
	<tr>
		<td><?= (int) ($row->staff_id ?? 0) ?></td>
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
