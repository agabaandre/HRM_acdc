<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>
<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add Division</h4>

			</div>
			<!-- /.card-header -->

			<div class="card-body">


				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="divisions">
				<input type="hidden" name="redirect" value="division">
				<div class="row">

					<div class="col-md-12" style="margin:0 auto;">
						<span class="status"></span>
					</div>
					<div class="col-sm-3">
						<!-- text input -->
						<div class="form-group">
							<label>Division Name</label>
							<input type="text" name="division_name" autocomplete="off" class="form-control" placeholder="Division Name" required />
						</div>
					</div>
					<div class="col-sm-3">
						<div class="form-group">
							<label for="focal_person">Focal Person</label>
							<select class="form-control select2" name="focal_person" required>
								<option value="">Select Focal Person</option>
								<?php
								$lists = $this->staff_mdl->get_all_staff_data([]);
								foreach ($lists as $list): ?>
									<option value="<?= $list->staff_id ?>"><?= $list->lname . ' ' . $list->fname ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="col-sm-3">
						<div class="form-group">
							<label for="finance_officer">Finance Officer</label>
							<select class="form-control select2" name="finance_officer" required>
								<option value="">Select Finance Officer</option>
								<?php foreach ($lists as $list): ?>
									<option value="<?= $list->staff_id ?>"><?= $list->lname . ' ' . $list->fname ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="col-sm-3">
						<div class="form-group">
							<label for="admin_assistant">Admin Assistant</label>
							<select class="form-control select2" name="admin_assistant" required>
								<option value="">Select Admin Assistant</option>
								<?php foreach ($lists as $list): ?>
									<option value="<?= $list->staff_id ?>"><?= $list->lname . ' ' . $list->fname ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="col-md-12">
						<br>
						<button type="submit" class="btn btn-success"><i class="fa fa-save"></i>Save</button>
						<button type="reset" class="btn  btn-secondary"><i class="fa fa-undo"></i>Reset</button>
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
				<h4 class="card-title">Divisions List</h4>
				<hr>
				<br>

			</div>
			<!-- /.card-header -->
			<div class="card-body">
				<table id="mytab2" class="table mydata table-striped">
					<thead>
						<tr>
							<th style="width:2%;">#</th>
							<th>Division Name</th>
							<th>Division Head</th>
							<th>Focal Person</th>
							<th>Finance Officer</th>
							<th>Admin Assistant</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php $no = 1;
						foreach ($divisions->result() as $division): ?>
							<tr>
								<td><?php echo $no; ?>.</td>
								<td><?php echo @$division->division_name; ?></td>
								<td><?php echo staff_name(@$division->division_head); ?></td>
								<td><?php echo staff_name(@$division->focal_person); ?></td>
								<td><?php echo staff_name(@$division->finance_officer); ?></td>
								<td><?php echo staff_name(@$division->admin_assistant); ?></td>
								<td>
									<button class="btn btn-secondary btn-sm"
										data-bs-toggle="modal"
										data-bs-target="#update_divisions<?php echo $division->division_id; ?>">
										<i class="fa fa-edit"></i> Edit
									</button>

									<?php
									if (in_array('78', $permissions)) {
										include('modals/update_divisions.php');
									}
									if (in_array('77', $permissions)) {
										include('modals/delete/delete_divisions.php');
									}
									?>
								</td>
							</tr>
						<?php $no++;
						endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- /.card-body -->
		</div>
	</div>



	<script>
		//get selected item
		function changeVal(selTag) {
			var x = selTag.options[selTag.selectedIndex].text;
			return x;
		}


		$(document).ready(function() {


			//Submit new user data

			$(".user_form").submit(function(e) {

				e.preventDefault();

				$('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');
				var formData = $(this).serialize();
				// console.log(formData);
				var url = "<?php echo base_url(); ?>auth/addUser";
				$.ajax({
					url: url,
					method: 'post',
					data: formData,
					success: function(result) {
						console.log(result);
						setTimeout(function() {
							$('.status').html(result);
							$.notify(result, 'info');
							$('.status').html('');
							$('.clear').click();
						}, 1000);


					}
				}); //ajax

			}); //form submit


			//Submit user update
			$(".update_user").submit(function(e) {
				e.preventDefault();
				$('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');
				var formData = new FormData(this);
				console.log(formData);
				var url = "<?php echo base_url(); ?>auth/updateUser";
				$.ajax({
					url: url,
					method: 'post',
					contentType: false,
					processData: false,
					data: formData,
					success: function(result) {

						console.log(result);

						setTimeout(function() {

							$('.status').html(result);

							$.notify(result, 'info');

							$('.status').html('');

							$('.clear').click();

						}, 3000);


					}
				}); //ajax


			}); //form submit



			$(".reset").submit(function(e) {
				e.preventDefault();
				$('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');
				var formData = $(this).serialize();
				console.log(formData);
				var url = "<?php echo base_url(); ?>auth/resetPass";
				$.ajax({
					url: url,
					method: 'post',
					data: formData,
					success: function(result) {
						// console.log(result);
						setTimeout(function() {
							$('.status').html(result);
							$.notify(result, 'info');
							$('.status').html('');

							$('.clear').click();

						}, 3000);


					}
				}); //ajax


			}); //form submit


			//block user

			$(".block").submit(function(e) {

				e.preventDefault();


				$('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');



				var formData = $(this).serialize();

				console.log(formData);

				var url = "<?php echo base_url(); ?>auth/blockUser";

				$.ajax({
					url: url,
					method: 'post',
					data: formData,
					success: function(result) {

						console.log(result);

						setTimeout(function() {

							$('.status').html(result);

							$.notify(result, 'info');

							$('.status').html('');

							$('.clear').click();

						}, 3000);


					}
				}); //ajax


			}); //form submit


			//block user

			$(".unblock").submit(function(e) {

				e.preventDefault();


				$('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');



				var formData = $(this).serialize();

				console.log(formData);

				var url = "<?php echo base_url(); ?>auth/unblockUser";

				$.ajax({
					url: url,
					method: 'post',
					data: formData,
					success: function(result) {

						console.log(result);

						setTimeout(function() {

							$('.status').html(result);

							$.notify(result, 'info');

							$('.status').html('');

							$('.clear').click();

						}, 3000);


					}
				}); //ajax


			}); //form submit


		}); //doc ready


		function getCountries(val) {
			$.ajax({
				method: "GET",
				url: "<?php echo base_url(); ?>geoareas/getCountries",
				data: 'region_data=' + val,
				success: function(res) {
					console.log(res);
					$(".scountry").html(res);
				}
			});
		}
	</script>