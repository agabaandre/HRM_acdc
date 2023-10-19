<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>

<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add Duty Station</h4>
				<hr>
			</div>
			<!-- /.card-header -->

			<div class="card-body">


				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="duty_stations">
				<input type="hidden" name="redirect" value="duty">
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
							<label>Duty Station Name</label>
							<input type="text" name="duty_station_name" autocomplete="off" class="form-control" placeholder="Duty Station Name" required />
						</div>
					</div>
					<div class="col-sm-4">
						<!-- text input -->
						<div class="form-group">
							<label>Country</label>
							<select type="text" name="country" autocomplete="off" placeholder="Country" class="form-control">
								<?php foreach ($countries->result() as $coutry) : ?>
									<option value="<?php echo $coutry->name ?>"><?php echo $coutry->name ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="col-sm-4">
						<!-- text input -->
						<div class="form-group">
							<label>Type</label>
							<select type="text" name="type" autocomplete="off" placeholder="Type" class="form-control" required>
								<option disabled selected>Type</option>
								<option value="MS">Member State</option>
								<option value="Head Office">Head Office</option>
								<option value="RCC">RCC</option>
							</select>
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
				<h4 class="card-title">Duty Station List</h4>
				<hr>
				<br>
				<div class="card-body">

					<table id="mytab2" class="table mydata table-striped ">
						<thead>
							<tr>
								<th>#</th>
								<th>Duty Station Name</th>
								<th>Country</th>
								<th>Type</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php

							$no = 1;

							foreach ($duties->result() as $duty) : ?>


								<tr>
									<td><?php echo $no; ?>. </td>
									<td><?php echo $duty->duty_station_name; ?></td>
									<td><?php echo $duty->country; ?></td>
									<td><?php echo $duty->type; ?></td>
									<td>
										<span class="badge text-bg-info"><a data-bs-toggle="modal" data-bs-target="#update_duty<?php echo $duty->duty_station_id; ?>" href="#">Edit</a></span>
										<span class="badge text-bg-danger"><a data-bs-toggle="modal" data-bs-target="#delete_duty<?php echo $duty->duty_station_id; ?>" href="#">Delete</a></span>
									</td>
								</tr>
							<?php
								if (in_array('77', $permissions)) :
									include('modals/update_duty.php');
								endif;
								if (in_array('77', $permissions)) :
									include('modals/delete/delete_duty_stations.php');
								endif;

								$no++;
							endforeach

							?>
						</tbody>

					</table>

				</div>

			</div>
		</div>
