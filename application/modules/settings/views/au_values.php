<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>
<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add AU Values</h4>
				<hr>
			</div>

			<div class="card-body">
				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="au_values">
				<input type="hidden" name="redirect" value="au">
				<div class="row">
					<div class="col-md-12">
						<button type="submit" class="btn btn-success">Save</button>
						<button type="reset" class="btn  btn-secondary">Reset All</button>
					</div>
					<div class="col-md-12" style="margin:0 auto;">
						<span class="status"></span>
					</div>
					<div class="col-sm-3">
						<!-- text input -->
						<div class="form-group">
							<label>Description</label>
							<textarea type="text" name="description" autocomplete="off" class="form-control" required></textarea>
						</div>
					</div>
					<div class="col-sm-3">
						<!-- text input -->
						<div class="form-group">
							<label>Annotation</label>
							<textarea type="text" name="annotation" autocomplete="off" class="form-control" required></textarea>
						</div>
					</div>
					<div class="col-sm-3">
						<!-- text input -->
						<div class="form-group">
							<label>Score 5</label>
							<textarea type="text" name="score_5" autocomplete="off" class="form-control" required></textarea>
						</div>
					</div>
					<div class="col-sm-3">
						<!-- text input -->
						<div class="form-group">
							<label>Score 4</label>
							<textarea type="text" name="score_4" autocomplete="off" class="form-control" required></textarea>
						</div>
					</div>
					<div class="col-sm-3">
						<!-- text input -->
						<div class="form-group">
							<label>Score 3</label>
							<textarea type="text" name="score_3" autocomplete="off" class="form-control" required></textarea>
						</div>
					</div>
					<div class="col-sm-3">
						<!-- text input -->
						<div class="form-group">
							<label>Score 2</label>
							<textarea type="text" name="score_2" autocomplete="off" class="form-control" required></textarea>
						</div>
					</div>
					<div class="col-sm-3">
						<!-- text input -->
						<div class="form-group">
							<label>Score 1</label>
							<textarea type="text" name="score_1" autocomplete="off" class="form-control" required></textarea>
						</div>
					</div>
					<div class="col-sm-3">
						<div class="row">
							<div class="col-sm-6">
								<div class="form-group">
									<label>Category</label>
									<select type="text" name="category" autocomplete="off" placeholder="Category" class="form-control">
										<?php //foreach($countries->result() as $coutry): 
										?>
										<option value="<?php echo "Functional"; ?>"><?php echo "Functional" ?></option>
										<option value="<?php echo "Leadership"; ?>"><?php echo "Leadership" ?></option>
										<option value="<?php echo "Core"; ?>"><?php echo "Core" ?></option>
										<option value="<?php echo "Values"; ?>"><?php echo "Values" ?></option>
										<?php //endforeach; 
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="form-group">
									<label>Version</label>
									<select type="text" name="version" autocomplete="off" placeholder="Version" class="form-control">
										<?php //foreach($countries->result() as $coutry): 
										?>
										<option value="<?php echo 1; ?>"><?php echo "Version 1.0"; ?></option>
										<option value="<?php echo 2; ?>"><?php echo "Version 2.0"; ?></option>
										<option value="<?php echo 3; ?>"><?php echo "Version 3.0"; ?></option>
										<option value="<?php echo 4; ?>"><?php echo "Version 4.0"; ?></option>
										<option value="<?php echo 5; ?>"><?php echo "Version 5.0"; ?></option>
										<?php //endforeach; 
										?>
									</select>
								</div>
							</div>
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
				<h4 class="card-title">Jobs AU Values List</h4>
				<hr>
				<br>

			</div>
			<!-- /.card-header -->
			<div class="card-body">

				<table id="mytab2" class="table mydata table-striped ">
					<thead>
						<tr>
							<th style="width:2%;">#</th>
							<th>Description</th>
							<th>Annotation</th>
							<th>Score 5</th>
							<th>Score 4</th>
							<th>Score 3</th>
							<th>Score 2</th>
							<th>Score 1</th>
							<th>Category</th>
							<th>Version</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php

						$no = 1;

						foreach ($au_values->result() as $au) : ?>


							<tr>
								<td><?php echo $no; ?>. </td>
								<td><?php echo $au->description; ?></td>
								<td><?php echo $au->annotation; ?></td>
								<td><?php echo $au->score_5; ?></td>
								<td><?php echo $au->score_4; ?></td>
								<td><?php echo $au->score_3; ?></td>
								<td><?php echo $au->score_2; ?></td>
								<td><?php echo $au->score_1; ?></td>
								<td><?php echo $au->category; ?></td>
								<td><?php echo $au->version; ?></td>
								<td><span class="badge text-bg-info"><a data-bs-toggle="modal" data-bs-target="#update_au_values<?php echo $au->id; ?>" href="#">Edit</a></span>
								</td>
							</tr>
						<?php
							if (in_array('78', $permissions)) :
								include('modals/update_au_values.php');
							endif;
							if (in_array('77', $permissions)) :
								include('modals/delete/delete_au_values.php');
							endif;

							$no++;
						endforeach

						?>

					</tbody>

				</table>

			</div>
		</div>
	</div>
