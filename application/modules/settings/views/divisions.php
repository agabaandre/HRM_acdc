<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$lists = $this->staff_mdl->get_all_staff_data([]);
?>


<div class="row">
  <div class="col-md-12">
    <div class="card card-default">
      <div class="card-header">
        <h4 class="card-title">Add Division</h4>
      </div>

      <div class="card-body">
        <?= form_open_multipart(base_url('settings/add_content')); ?>
        <input type="hidden" name="table" value="divisions">
        <input type="hidden" name="redirect" value="division">

        <div class="row">
          <div class="col-sm-3">
            <div class="form-group">
              <label>Division Name</label>
              <input type="text" name="division_name" class="form-control" placeholder="Division Name" required>
            </div>
          </div>

		  <div class="col-sm-3">
            <div class="form-group">
              <label>D</label>
              <input type="text" name="division_name" class="form-control" placeholder="Division Name" required>
            </div>
          </div>

          <div class="col-sm-3">
            <div class="form-group">
              <label>Category</label>
              <select name="category" class="form-control" required>
                <option value="">Select Category</option>
                <option value="Programs">Programs</option>
                <option value="Operations">Operations</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          <?php
          $dropdowns = [
            'division_head' => 'Division Head',
            'focal_person' => 'Focal Person',
            'finance_officer' => 'Finance Officer',
            'admin_assistant' => 'Admin Assistant',
            'head_oic_id' => 'Division Head OIC',
            'director_id' => 'Director',
            'director_oic_id' => 'Director OIC'
          ];
          foreach ($dropdowns as $field => $label): ?>
            <div class="col-sm-3">
              <div class="form-group">
                <label><?= $label ?></label>
                <select class="form-control select2" name="<?= $field ?>" required>
                  <option value="">Select <?= $label ?></option>
                  <?php foreach ($lists as $staff): ?>
                    <option value="<?= $staff->staff_id ?>"><?= $staff->lname . ' ' . $staff->fname ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          <?php endforeach; ?>

          <!-- Date Fields -->
          <div class="col-sm-3">
            <div class="form-group">
              <label>Head OIC Start Date</label>
              <input type="text" name="head_oic_start_date" class="form-control datepicker">
            </div>
          </div>

          <div class="col-sm-3">
            <div class="form-group">
              <label>Head OIC End Date</label>
              <input type="text" name="head_oic_end_date" class="form-control datepicker">
            </div>
          </div>

          <div class="col-sm-3">
            <div class="form-group">
              <label>Director OIC Start Date</label>
              <input type="text" name="director_oic_start_date" class="form-control datepicker">
            </div>
          </div>

          <div class="col-sm-3">
            <div class="form-group">
              <label>Director OIC End Date</label>
              <input type="text" name="director_oic_end_date" class="form-control datepicker">
            </div>
          </div>

          <div class="col-md-12 mt-3">
            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
            <button type="reset" class="btn btn-secondary"><i class="fa fa-undo"></i> Reset</button>
          </div>
        </div>
        <?= form_close(); ?>
      </div>
    </div>
  </div>

  <!-- Division List -->
  <div class="col-md-12 mt-4">
    <div class="card card-default">
      <div class="card-header">
        <h4 class="card-title">Divisions List</h4>
      </div>
      <div class="card-body">
        <table id="mytab2" class="table mydata table-striped">
          <thead>
            <tr>
              <th>#</th>
              <th>Division Name</th>
              <th>Category</th>
              <th>Division Head</th>
              <th>Focal Person</th>
              <th>Finance Officer</th>
              <th>Admin Assistant</th>
              <th>Directorate</th>
              <th>Head OIC</th>
              <th>Head OIC Dates</th>
              <th>Director</th>
              <th>Director OIC</th>
              <th>Director OIC Dates</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1;
            foreach ($divisions->result() as $division): ?>
              <tr>
                <td><?= $no++; ?></td>
                <td><?= $division->division_name ?></td>
                <td><?= $division->category ?></td>
                <td><?= staff_name($division->division_head) ?></td>
                <td><?= staff_name($division->focal_person) ?></td>
                <td><?= staff_name($division->finance_officer) ?></td>
                <td><?= staff_name($division->admin_assistant) ?></td>
                <td><?= staff_name($division->directorate_id) ?></td>
                <td><?= staff_name($division->head_oic_id) ?></td>
                <td><?= $division->head_oic_start_date . ' - ' . $division->head_oic_end_date ?></td>
                <td><?= staff_name($division->director_id) ?></td>
                <td><?= staff_name($division->director_oic_id) ?></td>
                <td><?= $division->director_oic_start_date . ' - ' . $division->director_oic_end_date ?></td>
                <td>
                  <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#update_divisions<?= $division->division_id ?>">
                    <i class="fa fa-edit"></i> Edit
                  </button>
                  <?php
                  if (in_array('78', $permissions)) include('modals/update_divisions.php');
                  if (in_array('77', $permissions)) include('modals/delete/delete_divisions.php');
                  ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
