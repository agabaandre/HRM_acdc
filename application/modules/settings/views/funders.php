<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>

<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add Funder</h4>
				<hr>
			</div>
			<!-- /.card-header -->

			<div class="card-body">


				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="funders">
				<input type="hidden" name="redirect" value="funder">
				<div class="row">
					<div class="col-md-12">
						<button type="submit" class="btn btn-success">Save</button>
						<button type="reset" class="btn  btn-secondary">Reset All</button>
					</div>
					<div class="col-md-12" style="margin:0 auto;">
						<span class="status"></span>
					</div>
					<div class="col-sm-5">
						<!-- text input -->
						<div class="form-group">
							<label>Funder</label>
							<input type="text" name="funder" autocomplete="off" class="form-control" placeholder="Funder" required />
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
				<h4 class="card-title">Funders List</h4>
				<hr>
				<br>
			</div>
			<!-- /.card-header -->
			<div class="card-body">


				<table id="mytab2" class="table mydata table-striped ">
					<thead>
						<tr>
							<th style="width:2%;">#</th>
							<th>Funder</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>

						<?php

						$no = 1;

						foreach ($funders->result() as $funder) : ?>

							<tr>
								<td><?php echo $no; ?>. </td>
								<td><?php echo $funder->funder; ?></td>
								<td><span class="badge text-bg-info"><a data-bs-toggle="modal" data-bs-target="#update_funders<?php echo $funder->funder_id; ?>" href="#">Edit</a></span>
								</td>
							</tr>

						<?php
							if (in_array('77', $permissions)) :
								include('modals/update_funders.php');
							endif;
							if (in_array('77', $permissions)) :
								include('modals/delete/delete_funders.php');
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
