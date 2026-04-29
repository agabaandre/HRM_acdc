<style>
	table {
		width: 100%;
		border-collapse: collapse;
		font-size: 11px;
	}
	th, td {
		border: 1px solid #ddd;
		padding: 6px;
		vertical-align: top;
	}
	th {
		background: #f5f5f5;
		text-align: left;
	}
	ul {
		margin: 0;
		padding-left: 16px;
	}
</style>

<h3 style="margin-bottom: 10px;">Staff Data Quality Report</h3>
<table>
	<thead>
		<tr>
			<th style="width: 6%;">#</th>
			<th style="width: 28%;">Name of Staff</th>
			<th>Data Quality Alerts</th>
		</tr>
	</thead>
	<tbody>
		<?php if (!empty($rows)) : ?>
			<?php $i = 1; ?>
			<?php foreach ($rows as $row) : ?>
				<tr>
					<td><?= $i++ ?></td>
					<td><?= htmlspecialchars((string) ($row->full_name ?? 'Unknown')) ?></td>
					<td>
						<?php if (!empty($row->data_quality_alerts)) : ?>
							<ul>
								<?php foreach ((array) $row->data_quality_alerts as $alert) : ?>
									<li><?= htmlspecialchars((string) $alert) ?></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							-
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr>
				<td colspan="3">No data quality alerts found.</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>
