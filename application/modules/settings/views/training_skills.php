<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>

<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add Training Skill</h4>
				<hr>
			</div>
			<!-- /.card-header -->
			<div class="card-body">
				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="training_skills">
				<input type="hidden" name="redirect" value="training">
				<div class="row">
					<div class="col-md-12">
						<button type="submit" class="btn btn-success"><i class="fa fa-save"></i>Save</button>
						<button type="reset" class="btn  btn-secondary"><i class="fa fa-undo"></i>Reset</button>
						<!-- <a href="<?php echo base_url() ?>auth/acdc_users" class="btn btn-success btn-sm">Auto Generate Users from the Staff List </a> -->
					</div>
					<div class="col-md-12" style="margin:0 auto;">
						<span class="status"></span>
					</div>
					<div class="col-sm-5">
						<!-- text input -->
						<div class="form-group">
							<label>Skill</label>
							<input type="text" name="skill" autocomplete="off" class="form-control" placeholder="Skill" required />
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
				<h4 class="card-title">Training Skills</h4>
				<hr>
				<br>
			</div>
			<!-- /.card-header -->
			<div class="card-body">

				<table id="mytab2" class="table mydata table-striped ">
					<thead>
						<tr>
							<th style="width:2%;">#</th>
							<th>Skill</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php

						$no = 1;

						foreach ($skills->result() as $skill) : ?>


							<tr>
								<td><?php echo $no; ?>. </td>
								<td><?php echo $skill->skill; ?></td>
								<td><button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#update_training_skills<?php echo $skill->id; ?>" href="#"><i class="fa fa-edit"></i>Edit</button>
							</tr>

						<?php
							if (in_array('78', $permissions)) :
								include('modals/update_training_skills.php');
							endif;
							if (in_array('77', $permissions)) :
								include('modals/delete/delete_training_skills.php');
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
