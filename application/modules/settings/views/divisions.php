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
				<hr>
			</div>
			<!-- /.card-header -->

			<div class="card-body">


				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="divisions">
				<input type="hidden" name="redirect" value="division">
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
							<label>Division Name</label>
							<input type="text" name="division_name" autocomplete="off" class="form-control" placeholder="Division Name" required />
						</div>
					</div>
					<div class="col-sm-4">
						<!-- text input -->
						<div class="form-group">
							<label>Division Head</label>
							<input type="text" name="division_head" autocomplete="off" class="form-control" placeholder="Division Head" required />
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
				<h4 class="card-title">Divisions List</h4>
				<hr>
				<br>

			</div>
			<!-- /.card-header -->
			<div class="card-body">

				<table id="mytab2" class="table mydata table-striped ">
					<thead>
						<tr>
							<th style="width:2%;">#</th>
							<th>Division Name</th>
							<th>Division Head</th>
							<th>Actions</th>
						</tr>
					</thead>
					<?php

					$no = 1;

					foreach ($divisions->result() as $division) : ?>
						<tbody>

							<tr>
								<td><?php echo $no; ?>. </td>
								<td><?php echo $division->division_name; ?></td>
								<td><?php echo $division->division_head; ?></td>
								<td><span class="badge text-bg-info"><a data-bs-toggle="modal" data-bs-target="#update_divisions<?php echo $division->division_id; ?>" href="#">Edit</a></span>
						

						<?php
						if (in_array('78', $permissions)) :
						include('modals/update_divisions.php');
						endif;
							if (in_array('77', $permissions)) :
						include('modals/delete/delete_divisions.php');
						endif;
						
						$no++;
					endforeach

						?>

						</tbody>

				</table>

				<?php echo $links; ?>

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
