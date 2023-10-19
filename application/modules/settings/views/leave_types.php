<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>
<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add Leave Type</h4>
				<hr>
			</div>
			<!-- /.card-header -->

			<div class="card-body">
				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="leave_types">
				<input type="hidden" name="redirect" value="leave">
				<div class="row">
					<div class="col-md-12">
						<button type="submit" class="btn btn-success">Save</button>
						<button type="reset" class="btn  btn-secondary">Reset All</button>
						<!-- <a href="<?php echo base_url() ?>auth/acdc_users" class="btn btn-success btn-sm">Auto Generate Users from the Staff List </a> -->
					</div>
					<div class="col-md-12" style="margin:0 auto;">
						<span class="status"></span>
					</div>
					<div class="col-sm-4">
						<!-- text input -->
						<div class="form-group">
							<label>Leave Name</label>
							<input type="text" name="leave_name" autocomplete="off" class="form-control" placeholder="Leave Name" required />
						</div>
					</div>
					<div class="col-sm-4">
						<!-- text input -->
						<div class="form-group">
							<label>Leave Days</label>
							<input type="number" name="leave_days" autocomplete="off" class="form-control" placeholder="Leave Days" required />
						</div>
					</div>

					<div class="col-sm-4">
						<!-- text input -->
						<div class="form-group">
							<label>Is Accrued</label>
							<input type="number" name="is_accrued" autocomplete="off" class="form-control" placeholder="Is Accrued" required />
						</div>
					</div>
					<div class="col-sm-4">
						<!-- text input -->
						<div class="form-group">
							<label>Accrual Rate</label>
							<input type="number" name="accrual_rate" autocomplete="off" class="form-control" placeholder="Accrual Rate" required />
						</div>
					</div>
				</div>
			</div>
			</form>

		</div>
		<!-- /.card-body -->
	</div>
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Leave Types</h4>
				<hr>
				<br>
			</div>
			<!-- /.card-header -->
			<div class="card-body">

				<table id="mytab2" class="table mydata table-striped ">
					<thead>
						<tr>
							<th style="width:2%;">#</th>
							<th>Leave Name</th>
							<th>Leave Days</th>
							<th>Is Accrued</th>
							<th>Accrual Rate</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php

						$no = 1;

						foreach ($leaves->result() as $leave) : ?>


							<tr>
								<td><?php echo $no; ?>. </td>
								<td><?php echo $leave->leave_name; ?></td>
								<td><?php echo $leave->leave_days; ?></td>
								<td><?php echo $leave->is_accrued; ?></td>
								<td><?php echo $leave->accrual_rate; ?></td>
								<td><span class="badge text-bg-info"><a data-bs-toggle="modal" data-bs-target="#update_leave_types<?php echo $leave->leave_id; ?>" href="#">Edit</a></span>
								</td>
							</tr>

						<?php
							if (in_array('78', $permissions)) :
								include('modals/update_leave_types.php');
							endif;
							if (in_array('77', $permissions)) :
								include('modals/delete/delete_leave_types.php');
							endif;
							$no++;
						endforeach

						?>

					</tbody>

				</table>

			</div>

		</div>
	</div>
