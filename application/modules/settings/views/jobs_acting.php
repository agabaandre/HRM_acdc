<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>

<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add Job Acting</h4>
				<hr>
			</div>
			<!-- /.card-header -->

			<div class="card-body">


				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="jobs_acting">
				<input type="hidden" name="redirect" value="acting">
				<div class="row">
					<div class="col-md-12">
						<button type="submit" class="btn btn-success">Save</button>
						<button type="reset" class="btn  btn-secondary">Reset All</button>
						<!-- <a href="<?php echo base_url() ?>auth/acdc_users" class="btn btn-success btn-sm">Auto Generate Users from the Staff List </a> -->
					</div>
					<div class="col-md-12" style="margin:0 auto;">
						<span class="status"></span>
					</div>
					<div class="col-sm-5">
						<!-- text input -->
						<div class="form-group">
							<label>Job Acting</label>
							<input type="text" name="job_acting" autocomplete="off" class="form-control" placeholder="Job Acting" required />
						</div>
					</div>

					<div class="col-sm-4">

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
				<h4 class="card-title">Jobs Acting List</h4>
				<hr>
				<br>
			</div>
			<!-- /.card-header -->
			<div class="card-body">

				<table id="mytab2" class="table mydata table-striped ">
					<thead>
						<tr>
							<th style="width:2%;">#</th>
							<th>Job Acting</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php

						$no = 1;

						foreach ($jobs_acting->result() as $acting) : ?>


							<tr>
								<td><?php echo $no++; ?>. </td>
								<td><?php echo $acting->job_acting; ?></td>
								<td><span class="badge text-bg-info"><a data-bs-toggle="modal" data-bs-target="#update_jobs_acting<?php echo $acting->job_acting_id; ?>" href="#">Edit</a></span>
								</td>
							</tr>

						<?php
							if (in_array('78', $permissions)) :
								include('modals/update_jobs_acting.php');
							endif;
							if (in_array('77', $permissions)) :
								include('modals/delete/delete_jobs_acting.php');
							endif;


							$no++;
						endforeach

						?>

					</tbody>

				</table>


			</div>
			<!-- /.card-body -->
		</div>
	</div>
