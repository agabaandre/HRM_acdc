<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
?>
<div class="row">
	<div class="col-md-12">
		<div class="card card-default">
			<div class="card-header">
				<h4 class="card-title">Add Nationality</h4>
			</div>
			<div class="card-body">

				<?php echo form_open_multipart(base_url('settings/add_content')); ?>
				<input type="hidden" name="table" value="nationalities">
				<input type="hidden" name="redirect" value="nationalities">
				<div class="row">
					<div class="col-md-12">
						<span class="status"></span>
					</div>
					<div class="col-sm-3">
						<div class="form-group">
							<label>Country Name</label>
							<input type="text" name="nationality" class="form-control" placeholder="Country Name (e.g. Uganda)" required />
						</div>
					</div>
					<div class="col-sm-3">
						<div class="form-group">
							<label>Nationality Name</label>
							<input type="text" name="nationality_name" class="form-control" placeholder="Nationality (e.g. Ugandan)" required />
						</div>
					</div>
					<div class="col-sm-2">
						<div class="form-group">
							<label>Continent</label>
							<input type="text" name="continent" class="form-control" placeholder="AFRICA, ASIA..." required />
						</div>
					</div>
					<div class="col-sm-2">
						<div class="form-group">
							<label>ISO2 Code</label>
							<input type="text" name="iso2" class="form-control" maxlength="2" placeholder="UG" required />
						</div>
					</div>
					<div class="col-sm-2">
						<div class="form-group">
							<label>ISO3 Code</label>
							<input type="text" name="iso3" class="form-control" maxlength="3" placeholder="UGA" required />
						</div>
					</div>
					<div class="col-sm-3">
						<div class="form-group">
							<label>Region</label>
							<select name="region_id" class="form-control select2" required>
								<option value="">Select Region</option>
								<option value="0">Rest of the World</option>
								<option value="1">AU_Central</option>
								<option value="2">AU_Eastern</option>
								<option value="3">AU_Northern</option>
								<option value="4">AU_Southern</option>
								<option value="5">AU_Western</option>
							</select>
						</div>
					</div>
					<div class="col-md-12">
						<br>
						<button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
						<button type="reset" class="btn btn-secondary"><i class="fa fa-undo"></i> Reset</button>
					</div>
				</div>
			</div>
			</form>
		</div>
	</div>
</div>
<div class="col-md-12 mt-4">
	<div class="card card-default">
		<div class="card-header">
			<h4 class="card-title">Nationalities List</h4>
		</div>
		<div class="card-body">
			<table class="table mydata table-striped table-bordered">
				<thead>
					<tr>
						<th>#</th>
						<th>Country</th>
						<th>Nationality</th>
						<th>Continent</th>
						<th>ISO2</th>
						<th>ISO3</th>
						<th>Region</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					$no = 1;
					foreach ($nationalities->result() as $row): ?>
						<tr>
							<td><?= $no++; ?>.</td>
							<td><?= ($row->nationality); ?></td>
							<td><?= ($row->nationality_name); ?></td>
							<td><?= ($row->continent); ?></td>
							<td><?= ($row->iso2); ?></td>
							<td><?= ($row->iso3); ?></td>
							<td>
								<?php
								$regions = [
									0 => 'Rest of World',
									1 => 'AU_Central',
									2 => 'AU_Eastern',
									3 => 'AU_Northern',
									4 => 'AU_Southern',
									5 => 'AU_Western'
								];
								echo $regions[$row->region_id] ?? 'Unknown';
								?>
							</td>
							<td>
								<button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#update_nationality<?= $row->nationality_id; ?>">
									<i class="fa fa-edit"></i> Edit
								</button>
								<?php
					
								include('modals/update_nationalities.php');
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
