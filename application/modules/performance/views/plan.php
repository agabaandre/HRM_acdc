<!-- Display leave application status for employees -->
<div class="card">

	<div class="card-body">

		<div class="table-responsive">
			<!-- Leave application status table -->
			<div class="row">
				<div class="col-md-12">
					<a class="btn btn-primary px-5 radius-30" href="<?php echo base_url() ?>performance/"><i class="fa fa-plus"></i>Performance Plan Submission</a>


					<form id="leave-filter-form" method="get" action="<?= base_url('myplans'); ?>">
						<div class="row mb-3">

							<div class="col-md-3">
								<label for="end_date">Period:</label>
								<input type="text" name="period" id="period" class="form-control">
							</div>
							<div class="col-md-3">
								<label for="status">Status:</label>
								<select class="form-control select2" name="status">
									<option value="">All</option>
									<option value="Pending">Pending</option>
									<option value="Approved">Approved</option>
									<option value="Rejected">Rejected</option>
								</select>
							</div>

							<div class="col-md-3">
								<button type="submit" class="btn btn-primary mt-4">Apply Filters</button>
							</div>

						</div>
					</form>
					<table id="leave-table" class="table table-striped">
						<thead>
							<tr>
								<th>#</th>
								<th>SAP NO</th>
								<th>Name</th>
								<th>Submission Date</th>
								<th>Period</th>
								<th>Objectives</th>
								<th>Comments</th>
								<th>Approval Status</th>
								<th>Overall Approval</th>

							</tr>
						</thead>
						<tbody>
							<?php
							$i = 1;
							foreach ($plans as $plan) : ?>
								<tr data-status="<?= $plan['overall_status']; ?>" <?php if ($plan['overall_status'] == 'Approved') { ?>style="background:#d2f0d7 !important" ;<?php } else if ($plan['overall_status'] == 'Rejected') { ?>style="background:#ffcdcd !important" ; <?php } ?>>
									<td><?= $i++; ?></td>
									<td><?= $plan['SAPNO']; ?></td>
									<td><?= $plan['lname'] . ' ' . $plan['fname'] . ' ' . $plan['oname']; ?></td>
									<td><?= $plan['created_at']; ?></td>
									<td><?= $plan['period'] ?></td>
									<td><?php $obj = $plan['objectives'];
										$objs = json_decode($obj);
										$counter = 0;
										foreach ($objs as $ob) :
											//print_r($objs);
											echo '<p>' . $counter + 1 . ' ' . $ob[$counter] . '</p><hr>';
											$counter++;
										endforeach;


										?></td>
									<td><?= $plan['ppa_comments'] ?></td>
									<td>
										<?php if (($plan['overall_status'] == 'Approved')) : ?>
											<button class="approved-btn btn btn-success" data-leave-id="<?= $plan['id']; ?>">Approved</button>
										<?php endif; ?>
										<?php if (($plan['overall_status'] == 'Sentback')) : ?>
											<button class="changes-btn btn btn-warning" data-leave-id="<?= $plan['id']; ?>">Make Changes</button>
										<?php endif; ?>
									</td>
									<td>


										<a class="btn btn-danger btn-sm pull-right" data-bs-toggle="modal" data-bs-target="#permsModal<?= $plan['id']; ?>">Review PPA</a>
										<button class="changes-btn btn btn-warning" data-leave-id="<?= $plan['id']; ?>"><?= $plan['overall_status'] ?></button>

									</td>


									</td>

									<div id="permsModal<?= $plan['id']; ?>" class="modal fade">
										<div class="modal-dialog modal-lg modal-dialog-centered">
											<div class="modal-content">
												<div class="modal-header">
													<h4 class="modal-title text-center">Supervisor Actions</h4>
													<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
												</div>
												<!-- Modal Body -->
												<form method="post">
													<div class="modal-body">
														<p>I hereby confirm that this PPA has been developed in consultation with the staff member and that it is aligned with the departmental objectives. The staff fully understands what is expected of them during the performance period and is also aware of the competencies that they will be assess against.

															I commit to providing supervision on the overall work of the staff member throughout the performance period to ensure the achievement of targeted results; and to providing on-going feedback and raising and discussing with him/her areas requiring performance improvement, where applicable
														</p>

														<!-- Comment Textbox -->
														<div class="form-group">
															<label for="comment">Comment:</label>
															<textarea class="form-control" id="comment" rows="3" name="ppa_comments"></textarea>
														</div>
														<div class="form-group">
															<label for="comment">Approval Options:</label>

															<select class="form-control" name="overall_status">
																<option value="" required>SELECT OPTION</option>
																<option value="Approved">Approved</option>
																<option value="Sentdback">Send Back</option>
															</select>
														</div>

														<div class="form-group">
															<label class="sign">Supervisor Signature</label><br>
															<?php if (isset($this->session->userdata('user')->signature)) { ?>
																<img src="<?php echo base_url() ?>uploads/staff/signature/<?php echo $this->session->userdata('user')->signature; ?>" style="width:100px; height: 80px;">
															<?php } ?>
														</div>
													</div>

													<!-- Modal Footer -->
													<div class="modal-footer">
														<button type="submit" class="btn btn-success">Confirm</button>
														<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
													</div>
												</form>
											</div>
										</div>
									</div>
								




				</tr>

			<?php endforeach; ?>
			</tbody>
			</table>
			</div>
		</div>



	</div>
</div>
</div>
