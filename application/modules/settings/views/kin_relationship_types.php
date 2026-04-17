<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$table_missing = empty($kin_relationship_types);
?>
<div class="row">
	<div class="col-md-12">
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add relationship type</h4>
				<hr>
			</div>
			<div class="card-body">
				<?php if ($table_missing): ?>
					<div class="alert alert-warning">
						Table <code>kin_relationship_types</code> is not installed yet. Run the one-time job:
						<code>php index.php jobs/jobs/add_staff_profile_extended_fields</code>
					</div>
				<?php else: ?>
				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="kin_relationship_types">
				<input type="hidden" name="redirect" value="kin_relationship_types">
				<div class="row align-items-end">
					<div class="col-md-12 mb-2">
						<button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
						<button type="reset" class="btn btn-secondary"><i class="fa fa-undo"></i> Reset</button>
					</div>
					<div class="col-sm-4">
						<div class="form-group">
							<label>Relationship name</label>
							<input type="text" name="relationship_name" class="form-control" required placeholder="e.g. Spouse">
						</div>
					</div>
					<div class="col-sm-2">
						<div class="form-group">
							<label>Sort order</label>
							<input type="number" name="sort_order" class="form-control" value="0">
						</div>
					</div>
					<div class="col-sm-3">
						<div class="form-group">
							<label>Active</label>
							<select name="is_active" class="form-control">
								<option value="1" selected>Yes</option>
								<option value="0">No</option>
							</select>
						</div>
					</div>
				</div>
				<?php echo form_close(); ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if (!$table_missing): ?>
	<div class="col-md-12 mt-3">
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Relationship types</h4>
				<hr>
			</div>
			<div class="card-body">
				<table class="table table-striped">
					<thead>
						<tr>
							<th>#</th>
							<th>Name</th>
							<th>Sort</th>
							<th>Active</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$no = 1;
						foreach ($kin_relationship_types->result() as $row):
						?>
						<tr>
							<td><?= $no++ ?></td>
							<td><?= htmlspecialchars($row->relationship_name) ?></td>
							<td><?= (int) $row->sort_order ?></td>
							<td><?= !empty($row->is_active) ? 'Yes' : 'No' ?></td>
							<td>
								<button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#update_kin_relationship_types<?= (int) $row->kin_relationship_id ?>">
									<i class="fa fa-edit"></i> Edit
								</button>
								<?php if (in_array('77', $permissions)): ?>
								<button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#delete_kin_relationship_types<?= (int) $row->kin_relationship_id ?>">
									<i class="fa fa-trash"></i> Delete
								</button>
								<?php endif; ?>
							</td>
						</tr>
						<?php
							if (in_array('78', $permissions)) {
								include __DIR__ . '/modals/update_kin_relationship_types.php';
							}
							if (in_array('77', $permissions)) {
								include __DIR__ . '/modals/delete/delete_kin_relationship_types.php';
							}
						endforeach;
						?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>
