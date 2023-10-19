<div class="card">
	<div class="card-body">
		<div class="table-responsive">
			<div class="row">
				<div class="col-md-12">
					<p class="justify-content-left"><?php echo $links;?><p>
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
								<tr data-status="<?= $plan['overall_status']; ?>">
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
										<a class="btn btn-danger btn-sm pull-right" data-bs-toggle="modal" data-bs-target="#permsModal<?= $plan['id']; ?>">Preview PPA</a>
										<a class="btn btn-danger btn-sm pull-right" data-bs-toggle="modal" data-bs-target="#permsModal<?= $plan['id']; ?>">Print PPA</a>
										<button class="changes-btn btn btn-warning" data-leave-id="<?= $plan['id']; ?>"><?= $plan['overall_status'] ?></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Modal Template -->
<div id="modal-template" style="display: none;">
	<div class="modal fade">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title text-center">Supervisor Actions</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form method="post">
					<div class="modal-body">

						<p>I hereby confirm that this PPA has been developed in consultation with the staff member and that it is aligned with the departmental objectives. The staff fully understands what is expected of them during the performance period and is also aware of the competencies that they will be assessed against.</p>

						<div class="form-group">
							<label for="comment">Approval Options:</label>
							<select class="form-control" name="overall_status" required>
								<option value="">SELECT OPTION</option>
								<option value="Approved">Approved</option>
								<option value="Sentdback">Send Back</option>
							</select>
						</div>
						<div class="form-group">
							<label for="comment">PPA Comments:</label>
							<textarea class="form-control" id="comment" rows="3" name="ppa_comments"></textarea>
						</div>

						<div class="form-group">
							<label class="sign">Supervisor Signature</label><br>
							<?php if (isset($this->session->userdata('user')->signature)) : ?>
								<img src="<?= base_url() ?>uploads/staff/signature/<?= $this->session->userdata('user')->signature; ?>" style="width:100px; height: 80px;">
							<?php endif; ?>
						</div>
					</div>
					<div class="modal-footer">
						<button type="submit" class="btn btn-success">Confirm</button>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
<script>
	$(document).ready(function() {
		<?php foreach ($plans as $plan) : ?>
			var modalTemplate = $("#modal-template").html();
			$("body").append(modalTemplate);
			$(".modal").last().attr("id", "permsModal<?= $plan['id']; ?>");
		<?php endforeach; ?>
	});
</script>
