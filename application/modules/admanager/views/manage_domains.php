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
    
		<?php 
     $status = $this->uri->segment(3);
     $route = $this->uri->segment(2);
    echo form_open_multipart(base_url('admanager/'.$route.'/'.$status), array('id' => 'ad_manager', 'class' => 'staff')); ?>
		<div class="row">
			<div class="col-md-2">
				<label>Name</label>
				<input type="text" name="lname" class="form-control" value="<?php echo $this->input->post('lname')?>">
			</div>
      <?php if(($this->uri->segment(2) == 'report')&&($this->uri->segment(1) == 'admanager')) { ?>
      <div class="col-md-2">
				<label>Date From</label>
				<input type="text" name="datefrom" class="form-control datepicker" value="<?php echo $this->input->post('datefrom')?>" autocomplete="false">
			</div>
      <div class="col-md-2">
				<label>Date To</label>
				<input type="text" name="dateto" class="form-control datepicker" value="<?php echo $this->input->post('dateto')?>" autocomplete="false">
			</div>
      <?php } ?>

			<div class="col-md-2">
				<label>Gender</label>
				<select class="form-control select2" name="gender">
				<option value="">Select Gender
				</option>
					<option value="Male" <?php if($this->input->post('gender')=='Male'){ echo 'selected';}?>>Male
					</option>
					<option value="Female" <?php if($this->input->post('gender')=='Female'){ echo 'selected';}?>>Female
					</option>
				</select>
			</div>
			<div class="col-md-2">
				<label>SAP NO</label>
				<input type="text" name="SAPNO" class="form-control">
			</div>
			<div class="col-md-2">
				<label>Nationaility</label>
				<select class="form-control select2" name="nationality_id">
				<option  value =''>Select Nationality</option>
					<?php $nationalities = $this->db->get('nationalities')->result();
					
					foreach ($nationalities as $nationality) : ?>
					     
						<option value="<?php echo $n = $nationality->nationality_id; ?>"<?php if($this->input->post('nationality_id')==$n){ echo 'selected';}?>><?php echo $nationality->nationality; ?>
						</option>
					<?php endforeach;

					?>

				</select>
			</div>
			<div class="col-md-2 mt-4" style="display:inline-flex;">
			<button type="submit" class="btn btn-sm btn-secondary" style="margin-right:2px;"><i class="fa fa-exchange-alt"></i>Apply</button>
			<?php if ($this->uri->segment(2) == 'report'){ ?>
			<a href='<?php echo base_url("admanager/report/1")?>' class="btn btn-sm btn-secondary"><i class="fa fa-file-csv"></i>Export</a>
			<?php }	?>
			
			<?php if ($this->uri->segment(2) == 'expired_accounts'){ ?>
			<a href='<?php echo base_url("admanager/expired_accounts/1")?>' class="btn btn-sm btn-secondary"><i class="fa fa-file-csv"></i>Export</a>

			<?php } ?>
            </div>

		</div>

		
	
		
			
	</div>


	</form>
	<?php echo $records ." Total Staff";
	if(!empty($this->input->post())){
	?> 

	<p>Result Limited By <?php foreach($this->input->post() as $key=>$value) :
	if($value!= ""){
		if($key=='nationality_id'){
			$cname= getcountry($value);

		}
		else{
			$cname=$value;
		}
		
		echo ucwords(str_replace('nationality_id','Nationality', str_replace('lname','Name',$key))).': '. $cname. ',';
	}
	
	
	endforeach;
	
}?>
	</p>
	<?php echo $links ?>

	
	<div class=" table-responsive">
		<table class="table table-striped table-responsive table-bordered">
			<thead>
				<tr>
					<th>#</th>
					<th>Passport Photo</th>
					<th>Name</th>
					<th>Gender</th>
					<th>Nationality</th>
          <th>Contract Status</th>
					<th>Email</th>
          <th>Mail Ad Staus</th>
          <th>Disable Date</th>
          <th>Action By</th>
				
				</tr>
			</thead>
			<tbody>


				<?php
				//dd($staffs);
			//dd($cont);

				$i = 1;
				if($this->uri->segment(4)!= ""){
					$i = $this->uri->segment(4);
				}
       // dd($staffs);
				foreach ($staffs as $data) :

			   $cont = Modules::run('staff/latest_staff_contract', $data->staff_id);
			  // dd($cont);

				?>

					<tr>

						<td><?= $i++ ?></td>
						<td>
							<?php 
							$surname=$data->lname;
							$other_name=$data->fname;
							$image_path=base_url().'uploads/staff/'.@$data->photo;
							echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path,$data->photo);
							
							?>
							
						</td>
						<td><a href="#" data-bs-toggle="modal" data-bs-target="#add_profile<?php echo $data->staff_id; ?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
						<td><?= $data->gender ?></td>
						<td><?= $data->nationality; ?></td>
					
            <td><?= @character_limiter($data->status); ?></td>
					

					
						<td><?= $data->work_email; ?></td>
            <td>
    <?php 
    if ($data->email_status == 1) { 
        echo '<span class="badge bg-success">Active</span>'; 
        echo " <button type='button' class='btn btn-sm btn-danger ms-2' data-bs-toggle='modal' data-bs-target='#disableModal$data->staff_id'>
                Mark as Disabled
              </button>";
    } else { 
        echo '<span class="badge bg-danger">Disabled</span>'; 
        echo " <button type='button' class='btn btn-sm btn-success ms-2' data-bs-toggle='modal' data-bs-target='#enableModal$data->staff_id'>
                Mark as Enabled
              </button>";
    }
    ?>
</td>

<!-- Bootstrap 5 Modal -->
<div class="modal fade" id="disableModal<?=$data->staff_id?>" tabindex="-1" aria-labelledby="disableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="disableModalLabel">Confirm Disable Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to mark this account as disbaled?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?php echo base_url('admanager/mark_disabled/'); ?><?php echo $data->staff_id; ?>" class="btn btn-danger">Yes</a>
            </div>
        </div>
    </div>
</div>


<!-- Bootstrap 5 Modal -->
<div class="modal fade" id="enableModal<?=$data->staff_id?>" tabindex="-1" aria-labelledby="enableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="disableModalLabel">Confirm Enable Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to mark this account as Enabled?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?php echo base_url('admanager/mark_enabled/'); ?><?php echo $data->staff_id; ?>" class="btn btn-danger">Yes</a>
            </div>
        </div>
    </div>
</div>


            <td><?= $data->email_disabled_at; ?></td>

            <td>
			<?php
				// Map email_disabled_by to name or system label
				if (!empty($staff->email_disabled_by)) {
					$staff['email_disabled_by'] = ($staff['email_disabled_by'] == $staff->staff_id)
						? 'System'
						: staff_name($staff->email_disabled_by);
						echo $staff['email_disabled_by'];
				} else {
					echo 'System';
				}
				
			?>
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
										<div class="col-md-12 d-flex justify-content-center">
											<div>
												<img src="<?php echo base_url() ?>/assets/images/AU_CDC_Logo-800.png" width="150">
											</div>
										
										</div>
										<div class="col-md-12 d-flex justify-content-center">
												<?=@$staff_photo?>
												<br>
												<h4>Name: <?=$data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></h4>
										</div>
										<div class="row justify-content-center">
											<!-- <div class="col-md-4" style="width:180px;">
											
											</div> -->

											<div class="row">
											 
												
												<div class="col-md-6">
												<h4>Personal Information</h4>
												<ul>
													<li><strong>SAPNO:</strong> <?= $data->SAPNO ?></li>
													<li><strong>Title:</strong> <?= $data->title ?></li>
													<li><strong>Gender:</strong> <?= $data->gender ?></li>
													<li><strong>Date of Birth:</strong> <?= $data->date_of_birth ?></li>
													<li><strong>Nationality:</strong> <?=$data->nationality ?></li>
													<li><strong>Initiation Date:</strong> <?= $data->initiation_date; ?></li>
												</ul>
												</div>
												<div class="col-md-6">
												<h4>Contact Information</h4>
												<ul>
													<li><strong>Email:</strong> <?= @$data->work_email ?></li>
													<li><strong>Telephone:</strong> <?= @$data->tel_1 ?> <?php if (!empty($data->tel_2)) {
																												echo '  ' . $data->tel_2;
																											} ?></li>
													<li><strong>WhatsApp:</strong> <?= @$data->whatsapp ?></li>
													<li><strong>Physical Location:</strong> <?= @$data->physical_location ?></li>
												</ul>
												</div>
												<div class="col-md-8">
												<h4>Contract Information</h4>
			
											<a href="<?php echo base_url(); ?>staff/staff_contracts/<?php echo $data->staff_id; ?>" 
												class="btn btn-primary no-print">
													Manage Contracts <i class="fa fa-eye"></i>
												</a>

												<ul>
													
													<li><strong>Duty Station:</strong> <?= $cont->duty_station_name ?></li>
													<li><strong>Division:</strong> <?= $cont->division_name ?></li>
													<li><strong>Job:</strong> <?= @character_limiter($cont->job_name, 30) ?></li>
													<?php if(!empty($cont->job_acting)||$cont->job_acting!='N/A'){ ?>
													<li><strong>Acting Job:</strong> <?= @character_limiter($cont->job_acting, 30) ?></li>
													<?php } ?>
													<li><strong>First Supervisor:</strong><?= @staff_name($cont->first_supervisor); ?></li>
													<li><strong>Second Supervisor:</strong> <?= @staff_name($cont->second_supervisor); ?></li>
											
													<li><strong>Funder:</strong> <?= $cont->funder ?></li>
													<li><strong>Contracting Organisation:</strong> <?= $cont->contracting_institution ?></li>
													<li><strong>Grade:</strong> <?= $cont->grade ?></li>
													<li><strong>Contract Type:</strong> <?= $cont->contract_type ?></li>
													<li><strong>Contract Status:</strong> <?= $cont->status ?></li>
													<li><strong>Contract Start Date:</strong> <?= $cont->start_date ?></li>
													<li><strong>Contract End Date:</strong> <?= $cont->end_date ?></li>
													<li><strong>Contract Comments:</strong> <?= $cont->comments ?></li>
												</ul>
												</div>
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
													<label for="SAPNO">SAP Number:<?=asterik()?></label>
													<input type="text" class="form-control" value="<?= $data->SAPNO ?>" name="SAPNO" id="SAPNO">
												</div>

												<div class="form-group">
													<label for="gender">Title:<?=asterik()?></label>
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
													<label for="fname">First Name:<?=asterik()?></label>
													<input type="text" class="form-control" value="<?php echo $data->fname;   ?>" name="fname" id="fname" required>
												</div>
												<input type="hidden" name="staff_id" value="<?php echo $data->staff_id; ?>">

												<div class="form-group">
													<label for="lname">Last Name:<?=asterik()?></label>
													<input type="text" class="form-control" name="lname" value="<?php echo $data->lname;   ?>" id="lname" required>
												</div>

												<div class="form-group">
													<label for="oname">Other Name:</label>
													<input type="text" class="form-control" value="<?php echo $data->oname;   ?>" name="oname" id="oname">
												</div>

												<div class="form-group">
													<label for="date_of_birth">Date of Birth:<?=asterik()?></label>
													<input type="text" class="form-control datepicker" value="<?php echo $data->date_of_birth; ?>" name="date_of_birth" id="date_of_birth" required>
												</div>

												<div class="form-group">
													<label for="gender">Gender:<?=asterik()?></label>
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
													<label for="nationality_id">Nationality:<?=asterik()?></label>
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
													<label for="initiation_date">Initiation Date: <?=asterik()?></label>
													<input type="text" class="form-control datepicker" value="<?php echo $data->initiation_date; ?>" name="initiation_date" id="initiation_date" required>
												</div>
											</div>

											<div class="col-md-6">
												<h4>Contact Information</h4>


												<div class="form-group">
													<label for="tel_1">Telephone 1: <?=asterik()?></label>
													<input type="text" class="form-control" value="<?php echo $data->tel_1; ?>" name="tel_1" id="tel_1" required>
												</div>

												<div class="form-group">
													<label for="tel_2">Telephone 2:</label>
													<input type="text" class="form-control" value="<?php echo $data->tel_2; ?>" name="tel_2" id="tel_2">
												</div>

												<div class="form-group">
													<label for="whatsapp">WhatsApp:</label>
													<input type="text" class="form-control" name="whatsapp" value="<?php echo $data->whatsapp; ?>" id="whatsapp">
												</div>

												<div class="form-group">
													<label for="work_email">Work Email:<?=asterik()?></label>
													<input type="email" class="form-control" name="work_email" value="<?php echo $data->work_email; ?>" id="work_email" required>
												</div>
												<br>
												<div class="form-group">
													<label for="private_email">Private Email:</label>
													<input type="email" class="form-control" name="private_email" value="<?php echo $data->private_email; ?>" id="private_email">
												</div>

												<div class="form-group">
													<label for="physical_location">Physical Location:<?=asterik()?></label>
													<textarea class="form-control" name="physical_location" id="physical_location" rows="2" required><?php echo $data->physical_location; ?></textarea>
												</div>
											</div>
										</div>

										<div class="form-group" style="float:right;">
											<br>
											<label for="submit"></label>
											<input type="submit" class="btn btn-dark" value="Save">
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
<?php echo $records ." Total Staff";?>
<?php echo $links ?>
</div>
</div>
</div>

<!-- Bootstrap Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Employee Passport Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" style="width:150px; height:auto; border-radius:10px;">
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
function openImageModal(imageSrc) {
    document.getElementById("modalImage").src = imageSrc;
    var myModal = new bootstrap.Modal(document.getElementById("imageModal"), {});
    myModal.show();
}
</script>