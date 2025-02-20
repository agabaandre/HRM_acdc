<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>
<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Units</h4>
				<hr>
			</div>
			<!-- /.card-header -->

			<div class="card-body">


				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="units">
				<input type="hidden" name="redirect" value="units">
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
							<label>Unit Name</label>
							<input type="text" name="unit_name" autocomplete="off" class="form-control" placeholder="Unit Name" required />
						</div>
					</div>

                    <div class="form-group col-sm-5">
                                <label for="division_id">Division:</label>
                                <select class="form-control select2" name="division_id" id="division_id" required>
                                    <?php $lists = Modules::run('lists/divisions');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->division_id; ?>" 
                                                                            ><?php echo $list->division_name; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                          <div class="form-group col-sm-5">
                                <label for="first_supervisor">Unit Head:</label>
                                <select class="form-control select2" name="staff_id" id="" required>
                                    <option value="">Select First Supervisor</option>
                                    <?php $lists = Modules::run('lists/supervisor');
                                    foreach ($lists as $list) :
                                    ?>
                                    
                                        <option value="<?php echo $list->staff_id; ?>" 
                                                                            ><?php echo $list->lname . ' ' . $list->fname; ?></option>
                                    
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
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
							<th>Unit</th>
                            <th>Division</th>
                            <th>Unit Head</th>
							<th>Actions</th>
                         
						</tr>
					</thead>
					<tbody>
						<?php

						$no = 1;

						foreach ($units->result() as $unit) : ?>


							<tr>
								<td><?php echo $no++; ?>. </td>
                                <td><?php echo $unit->unit_name; ?></td>
								<td><?php echo acdc_division($unit->division_id); ?></td>
                                <td><?php echo staff_name($unit->staff_id); ?></td>
								<td><button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#update_institution<?php echo $unit->unit_id; ?>" href="#"><i class="fa fa-edit"></i>Edit</button>
								</td>
							</tr>

						<?php
							if (in_array('78', $permissions)) :
								include('modals/update_units.php');
							endif;
						
						endforeach

						?>

					</tbody>

				</table>

			</div>
			<!-- /.card-body -->
		</div>
	</div>
