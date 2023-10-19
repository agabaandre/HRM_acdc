<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>

<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add Contract Type</h4>
				<hr>
			</div>

			<div class="card-body">
				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="contract_types">
				<input type="hidden" name="redirect" value="typs">
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
							<label>Contract Type</label>
							<input type="text" name="contract_type" autocomplete="off" class="form-control" placeholder="Contract Type" required />
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
				<h4 class="card-title">Contract Types</h4>
				<hr>
				<br>
				<div class="card-body">

					<table id="mytab2" class="table mydata table-striped ">
						<thead>
							<tr>
								<th style="width:2%;">#</th>
								<th>Contract Type</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php

							$no = 1;

							foreach ($contract_types->result() as $contract) : ?>


								<tr>
									<td><?php echo $no; ?>. </td>
									<td><?php echo $contract->contract_type; ?></td>
									<td><span class="badge text-bg-info"><a data-bs-toggle="modal" data-bs-target="#update_contruct_types<?php echo $contract->contract_type_id; ?>" href="#">Edit</a></span>
									</td>
								</tr>

							<?php
								if (in_array('78', $permissions)) :
									include('modals/update_contract_types.php');
								endif;
								if (in_array('77', $permissions)) :
									include('modals/delete/delete_contract_types.php');
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
