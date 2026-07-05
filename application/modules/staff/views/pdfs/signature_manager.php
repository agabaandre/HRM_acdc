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
</style>

<h3 style="margin-bottom: 10px;">Staff Signature Manager</h3>
<table>
	<thead>
		<tr>
			<th style="width: 6%;">#</th>
			<th style="width: 12%;">SAP</th>
			<th style="width: 30%;">Name of Staff</th>
			<th style="width: 14%;">Status</th>
			<th>Signature text</th>
		</tr>
	</thead>
	<tbody>
		<?php if (!empty($rows)) : ?>
			<?php $i = 1; ?>
			<?php foreach ($rows as $row) : ?>
				<tr>
					<td><?= $i++ ?></td>
					<td><?= htmlspecialchars((string) ($row->SAPNO ?? '')) ?></td>
					<td><?= htmlspecialchars((string) ($row->full_name ?? 'Unknown')) ?></td>
					<td><?= htmlspecialchars((string) ($row->signature_status_label ?? '')) ?></td>
					<td><?= htmlspecialchars((string) ($row->signature_text ?? '')) ?></td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr>
				<td colspan="5">No staff found.</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>
