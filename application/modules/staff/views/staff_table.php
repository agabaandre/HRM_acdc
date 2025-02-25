<style>
	@media print {
		.hidden {
			display: none;
		}

		@page {
			margin-top: 0;
			margin-bottom: 0;
			display: flex;
			justify-content: center;
			align-items: center;
			height: 100%;
			/* html, body{
                height: 100%;
                width: 100%;
            } */
		}

		/* body{
            padding-top: 72px;
            padding-bottom: 72px;
        } */
	}
</style>


<div class="card">
	<div class="col-md-12" style="float: right;">
		<a href="<?php echo base_url() ?>staff/new" class="btn   btn-dark btn-sm btn-bordered">+ Add New Staff</a>
	</div>
	<div class="card-body">
		<div class="justify-content-center">
			<?php //print_r($this->session->tempdata());
			?>
		</div>
		<?php echo form_open_multipart(base_url('staff'), array('id' => 'staff_ppa', 'class' => 'staff')); ?>
		<div class="row">
			<div class="col-md-3">
				<label>Surname</label>
				<input type="text" name="lname" class="form-control">
			</div>
			<div class="col-md-3">
				<label>Firstname</label>
				<input type="text" name="fname" class="form-control">
			</div>

			<div class="col-md-2">
				<label>Gender</label>
				<select class="form-control select2" name="gender">
					<option value="Male">Male
					</option>
					<option value="Female">Female
					</option>
				</select>
			</div>
			<div class="col-md-2">
				<label>SAP NO</label>
				<input type="text" name="SAPNO" class="form-control">
			</div>
			<div class="col-md-2">
				<label>Nationaility</label>
				<select class="form-control select2" name="nataionality_id" multiple>
					<?php $nationalities = $this->db->get('nationalities')->result();
					foreach ($nationalities as $nationality) : ?>
						<option value="<?php echo $nationality->nationality_id; ?>"><?php echo $nationality->nationality; ?>
						</option>
					<?php endforeach;

					?>

				</select>
			</div>


		</div>

		
	
			<button type="submit" class="btn btn-secondary mt-2"><i class="fa fa-exchange-alt"></i>Apply</button>
			<a href="#" class="btn btn-secondary mt-2"><i class="fa fa-file-csv"></i>Export</a>
			
	</div>


	</form>
	<?php echo $links ?>
	<div class=" table-responsive">
		<table class="table table-striped table-bordered">
			<thead>
				<tr>
					<th>#</th>
					<th>SAPNO</th>
					<th>Title</th>
					<th>Image</th>
					<th>Name</th>
					<th>Gender</th>
					<th>Job</th>
				
					<th>Division</th>
					<th>Nationality</th>
					<th>Acting Job</th>
					<th>First Supervisor</th>
					<th>Second Supervisor</th>
					<th>Duty Station</th>
					<th>Email</th>
					<th>Telephone</th>
					<th>WhatsApp</th>
				</tr>
			</thead>
			<tbody>


				<?php
				//dd($staffs);
			//dd($cont);

				$i = 1;
				foreach ($staffs as $data) :

				?>

					<tr>

						<td><?= $i++ ?></td>
						<td><?= $data->SAPNO ?></td>
						<td><?= $data->title ?></td>
						<td>
							<?php 
							$surname=$data->lname;
							$other_name=$data->fname;
							$image_path=base_url().'uploads/staff/'.@get_photo($data->staff_id);
							echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path);
							
							?>
							
						</td>
						<td><a href="#" data-bs-toggle="modal" data-bs-target="#add_profile<?php echo $data->staff_id; ?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
						<td><?= $data->gender ?></td>

						<td><?= @character_limiter($data->job_name, 8); ?></td>
					
						<td><?= $data->division_name; ?></td>
						<td><?= $data->nationality; ?></td>
						<td><?= @character_limiter($data->job_acting, 8); ?></td>
						<td><?= @staff_name($data->first_supervisor); ?></td>
						<td><?= @staff_name($data->second_supervisor); ?></td>

						<td><?= $data->duty_station_name; ?></td>
						<td><?= $data->work_email; ?></td>
						<td><?= @$data->tel_1 ?> <?php if (!empty($data->tel_2)) {
														echo '  ' . $data->tel_2;
													} ?></td>
						<td><?= $data->whatsapp ?>
					
					</td>


						<div class="modal fade" id="add_profile<?php echo $data->staff_id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
							<div class="modal-dialog modal-dialog-centered modal-lg">
								<div class="modal-content">
									<div class="modal-header">
										<h5 class="modal-title" id="add_item_label">Employee Profile: <?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></h5>
										<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

									</div>
									<div class="toolbar hidden-print">
										<div class="text-end" style="margin-right:10px;">
											<a href="#" data-bs-toggle="modal" class="btn   btn-dark btn-sm btn-bordered btn-print" data-bs-target="#edit_profile<?php echo $data->staff_id; ?>">Edit</a>
											<a href="#" class="btn   btn-dark btn-sm btn-bordered btn-print" onclick="printPage()">Print</a>
										</div>
										<hr>
									</div>
									<div class="modal-body" id="worker_profile">
										<div class="col-md-12 d-flex justify-content-center p-5">
											<div>
												<img src="<?php echo base_url() ?>/assets/images/AU_CDC_Logo-800.png" width="300">
											</div>
										</div>
										<div class="row justify-content-center">
											<!-- <div class="col-md-4" style="width:180px;">
											
											</div> -->

											<div class="col-md-8 ">
											    <div class ="photo justify-content-center"><?=@$staff_photo?>
												<br>
												<h3>Name: <?=$data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></h3>
												</div>
												<h4>Personal Information</h4>
												<ul>
													<li><strong>SAPNO:</strong> <?= $data->SAPNO ?></li>
													<li><strong>Title:</strong> <?= $data->title ?></li>
													<li><strong>Gender:</strong> <?= $data->gender ?></li>
													<li><strong>Nationality:</strong> <?php $data->nationality ?></li>
												</ul>
												<h4>Contact Information</h4>
												<ul>
													<li><strong>Email:</strong> <?= @$data->work_email ?></li>
													<li><strong>Telephone:</strong> <?= @$data->tel_1 ?> <?php if (!empty($data->tel_2)) {
																												echo '  ' . $data->tel_2;
																											} ?></li>
													<li><strong>WhatsApp:</strong> <?= @$data->whatsapp ?></li>
												</ul>
												<h4>Contract Information</h4>
												<?php $cont = Modules::run('staff/latest_staff_contract', $data->staff_id);



												?>

											
												<a href="<?php echo base_url(); ?>staff/staff_contracts/<?php echo $data->staff_id; ?>" 
												class="btn btn-primary no-print">
													Manage Contracts <i class="fa fa-th"></i>
												</a>

												<ul>
													
													<li><strong>Duty Station:</strong> <?= $cont->duty_station_name ?></li>
													<li><strong>Division:</strong> <?= $cont->division_name ?></li>
													<li><strong>Job:</strong> <?= @character_limiter($cont->job_name, 15) ?></li>
													<li><strong>Acting Job:</strong> <?= @character_limiter($cont->job_acting, 15) ?></li>
													
		
													<li><strong>Funder:</strong> <?= $cont->funder ?></li>
													<li><strong>Contracting Organisation:</strong> <?= $cont->contracting_institution ?></li>
													<li><strong>Grade:</strong> <?= $cont->grade ?></li>
													<li><strong>Contract Type:</strong> <?= $cont->contract_type ?></li>
													<li><strong>Contract Status:</strong> <?= $cont->status ?></li>
													<li><strong>Contract Start Date:</strong> <?= $cont->start_date ?></li>
													<li><strong>Contract End Date:</strong> <?= $cont->end_date ?></li>
													<li><strong>Contract Comments:</strong> <?= $cont->comments ?></li>
												</ul>
												<?php ?>

											</div>
										</div>
									</div>
									<div class="modal-footer">
										<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
									</div>
								</div>
							</div>
						</div>


						<!-- edit model -->
						<!-- edit employee data model -->
						<div class="modal fade" id="edit_profile<?php echo $data->staff_id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
							<div class="modal-dialog modal-dialog-centered modal-lg">
								<div class="modal-content">
									<div class="modal-header">
										<h5 class="modal-title" id="add_item_label">Edit Employee Profile: <?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></h5>
										<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
									</div>

									<div class="modal-body">

										<?php echo validation_errors(); ?>
										<?php echo form_open('staff/update_staff'); ?>
										<div class="row">

											<div class="col-md-6">


												<h4>Personal Information</h4>

												<div class="form-group">
													<label for="SAPNO">SAP Number:</label>
													<input type="text" class="form-control" value="<?= $data->SAPNO ?>" name="SAPNO" id="SAPNO" readonly>
												</div>

												<div class="form-group">
													<label for="gender">Title:</label>
													<select class="form-control" name="title" id="title" required>
														<?php if (!empty($data->title)) { ?>
															<option value="<?php echo $data->title ?>"><?php echo $data->title ?></option>
														<?php } ?>
														<option value="">Select Title</option>
														<option value="Dr">Dr</option>
														<option value="Prof">Prof</option>
														<option value="Rev">Rev</option>
														<option value="Mr">Mr</option>
														<option value="Mrs">Mrs</option>

													</select>
												</div>

												<div class="form-group">
													<label for="fname">First Name:</label>
													<input type="text" class="form-control" value="<?php echo $data->fname;   ?>" name="fname" id="fname" required>
												</div>
												<input type="hidden" name="staff_id" value="<?php echo $data->staff_id; ?>">

												<div class="form-group">
													<label for="lname">Last Name:</label>
													<input type="text" class="form-control" name="lname" value="<?php echo $data->lname;   ?>" id="lname" required>
												</div>

												<div class="form-group">
													<label for="oname">Other Name:</label>
													<input type="text" class="form-control" value="<?php echo $data->oname;   ?>" name="oname" id="oname">
												</div>

												<div class="form-group">
													<label for="date_of_birth">Date of Birth:</label>
													<input type="text" class="form-control datepicker" value="<?php echo $data->date_of_birth; ?>" name="date_of_birth" id="date_of_birth" required>
												</div>

												<div class="form-group">
													<label for="gender">Gender:</label>
													<select class="form-control" name="gender" id="gender" required>
														<?php if (!empty($data->gender)) {
															echo $data->gender;
														} ?>
														<option value="Male">Male</option>
														<option value="Female">Female</option>
														<option value="Other">Other</option>
													</select>
												</div>

												<div class="form-group">
													<label for="nationality_id">Nationality:</label>
													<select class="form-control select2" name="nationality_id" id="nationality_id" required>
														<?php $lists = Modules::run('lists/nationality');
														foreach ($lists as $list) :
														?>
															<option value="<?php echo $list->nationality_id; ?>" <?php if ($list->nationality_id == $data->nationality_id) {
																														echo "selected";
																													} ?>><?php echo $list->status; ?><?php echo $list->nationality; ?></option>
														<?php endforeach; ?>
														<!-- Add more options as needed -->
													</select>
												</div>

												<div class="form-group">
													<label for="initiation_date">Initiation Date:</label>
													<input type="text" class="form-control datepicker" value="<?php echo $data->initiation_date; ?>" name="initiation_date" id="initiation_date" required>
												</div>
											</div>

											<div class="col-md-6">
												<h4>Contact Information</h4>


												<div class="form-group">
													<label for="tel_1">Telephone 1:</label>
													<input type="text" class="form-control" value="<?php echo $data->tel_1; ?>" name="tel_1" id="tel_1" required>
												</div>

												<div class="form-group">
													<label for="tel_2">Telephone 2:</label>
													<input type="text" class="form-control" value="<?php echo $data->tel_2; ?>" name="tel_2" id="tel_2">
												</div>

												<div class="form-group">
													<label for="whatsapp">WhatsApp:</label>
													<input type="text" class="form-control" name="whatsapp" value="<?php echo $data->whatsapp; ?>" id="whatsapp" required>
												</div>

												<div class="form-group">
													<label for="work_email">Work Email:</label>
													<input type="email" class="form-control" name="work_email" value="<?php echo $data->work_email; ?>" id="work_email" required>
												</div>
												<br>
												<div class="form-group">
													<label for="private_email">Private Email:</label>
													<input type="email" class="form-control" name="private_email" value="<?php echo $data->private_email; ?>" id="private_email">
												</div>

												<div class="form-group">
													<label for="physical_location">Physical Location:</label>
													<textarea class="form-control" name="physical_location" id="physical_location" rows="2" required><?php echo $data->physical_location; ?></textarea>
												</div>
											</div>
										</div>

										<div class="form-group" style="float:right;">
											<br>
											<label for="submit"></label>
											<input type="submit" class="btn btn-dark" value="Submit">
											<input type="reset" class="btn btn-danger" value="Reset">
										</div>

										<?php echo form_close(); ?>
									</div>
								</div>
							</div>

						</div>
	</div>

</div>

<!-- edit employee data model -->




<!-- edit model -->


<script>
	// Print button functionality
	function printPage() {
		// Hide the print button before printing
		document.querySelector(".btn-print").style.display = "none";

		// Print only the worker's profile
		var printContents = document.getElementById("worker_profile").innerHTML;
		var originalContents = document.body.innerHTML;

		document.body.innerHTML = printContents;
		window.print();

		// Restore the original contents after printing
		document.body.innerHTML = originalContents;

		// Dismiss the modal after printing
		document.getElementById("add_profile").addEventListener("afterprint", function() {
			var modal = new bootstrap.Modal(document.getElementById("add_profile"));
			modal.hide();
		});
	}
</script>




</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
