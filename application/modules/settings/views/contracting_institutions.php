<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>
<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add Contracting Institution</h4>
				<hr>
			</div>
			<!-- /.card-header -->

			<div class="card-body">


				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="contracting_institutions">
				<input type="hidden" name="redirect" value="institution">
				<div class="row">
					<div class="col-md-12">
						<button type="submit" class="btn btn-success"><i class="fa fa-save"></i>Save</button>
						<button type="reset" class="btn  btn-secondary"><i class="fa fa-undo"></i>Reset</button>
					</div>
					<div class="col-md-12" style="margin:0 auto;">
						<span class="status"></span>
					</div>
					<div class="col-sm-5">
						<!-- text input -->
						<div class="form-group">
							<label>Institution Name</label>
							<input type="text" name="contracting_institution" autocomplete="off" class="form-control" placeholder="Institution Name" required />
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
				<h4 class="card-title">Institutions List</h4>
				<hr>
				<br>
			</div>
			<!-- /.card-header -->
			<div class="card-body">

				<table id="mytab2" class="table mydata table-striped ">
					<thead>
						<tr>
							<th style="width:2%;">#</th>
							<th>Contracting Institution Name</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php

						$no = 1;

						foreach ($institutions->result() as $institute) : ?>


							<tr>
								<td><?php echo $no; ?>. </td>
								<td><?php echo $institute->contracting_institution; ?></td>
								<td><button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#update_institution<?php echo $institute->contracting_institution_id; ?>" href="#"><i class="fa fa-edit"></i>Edit</button>
								</td>
							</tr>

						<?php
							if (in_array('78', $permissions)) :
								include('modals/update_contracting_institutions.php');
							endif;
							if (in_array('77', $permissions)) :
								include('modals/delete/delete_contracting_institutions.php');
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
