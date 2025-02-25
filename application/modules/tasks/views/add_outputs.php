
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filters</h5>
        </div>
        <div class="card-body">
            <form action="<?php echo site_url('quarterly_output/filter'); ?>" method="get" class="form-inline">
            <div class="form-group mr-3">
                    <label for="filter_period" class="mr-2">Financial Year:</label>
                    <select id="filter_period" name="filter_period" class="form-control">
                        <option value="">All</option>
                        <option value="Q1" <?php echo ($this->input->get('filter_period') == 'Q1') ? 'selected' : ''; ?>>Q1</option>
                        <option value="Q2" <?php echo ($this->input->get('filter_period') == 'Q2') ? 'selected' : ''; ?>>Q2</option>
                        <option value="Q3" <?php echo ($this->input->get('filter_period') == 'Q3') ? 'selected' : ''; ?>>Q3</option>
                        <option value="Q4" <?php echo ($this->input->get('filter_period') == 'Q4') ? 'selected' : ''; ?>>Q4</option>
                    </select>
                </div>
                <div class="form-group mr-3">
                    <label for="filter_period" class="mr-2">Period:</label>
                    <select id="filter_period" name="filter_period" class="form-control">
                        <option value="">All</option>
                        <option value="Q1" <?php echo ($this->input->get('filter_period') == 'Q1') ? 'selected' : ''; ?>>Q1</option>
                        <option value="Q2" <?php echo ($this->input->get('filter_period') == 'Q2') ? 'selected' : ''; ?>>Q2</option>
                        <option value="Q3" <?php echo ($this->input->get('filter_period') == 'Q3') ? 'selected' : ''; ?>>Q3</option>
                        <option value="Q4" <?php echo ($this->input->get('filter_period') == 'Q4') ? 'selected' : ''; ?>>Q4</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
    </div>

 <!-- Add Data Button -->
 <?php 
 $session = $this->session->userdata('user');
 $permissions = $session->permissions;

 //dd($session);
 
 if (in_array('79', $permissions)) : ?>
<button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addModal">
    Add Quarterly Output
</button>

<?php endif; ?>


    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h5>Quarterly Output List</h5>
        </div>
        <div class="card-body">
        <table class="table table-bordered mydata">
      <thead>
        <tr>
          <th>#</th>
          <th>Unit ID</th>
          <th>Name</th>
          <th>Description</th>
          <th>Financial Year</th>
          <th>Period</th>
       
          <th>Created By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $i =1;
         foreach($quarterly_outputs as $output): ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo $output->unit_name; ?></td>
            <td><?php echo $output->name; ?></td>
            <td><?php echo $output->description; ?></td>
            <td><?php echo $output->financial_year; ?></td>
            <td><?php echo $output->period; ?></td>
         
            <td><?php echo staff_name($output->created_by); ?></td>
            <td>
            <?php if ($session->staff_id===$output->created_by){?>
              <!-- Edit Button -->
              <a href="#"
                 class="btn btn-sm btn-warning"
                 data-bs-toggle="modal"
                 data-bs-target="#editModal"
                 data-id="<?php echo $output->quarterly_output_id; ?>"
                 data-unit_id="<?php echo $output->unit_id; ?>"
                 data-name="<?php echo htmlspecialchars($output->name); ?>"
                 data-description="<?php echo htmlspecialchars($output->description); ?>"
                 data-financial_year="<?php echo $output->financial_year; ?>"
                 data-period="<?php echo $output->period; ?>">
               
                Edit
              </a>
              <!-- Delete Button -->

              <a href="#"
                 class="btn btn-sm btn-danger"
                 data-bs-toggle="modal"
                 data-bs-target="#deleteModal"
                 data-id="<?php echo $output->quarterly_output_id; ?>">
                Delete
              </a>
              <?php } ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
        </div>
    </div>
</div>



<!-- Add Data Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered " role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addModalLabel">Add Quarterly Output</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form action="<?php echo site_url('tasks/add_outputs'); ?>" method="post">
          <div class="form-group">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" class="form-control" required>
            <input type="hidden" id="created_by" name="created_by" class="form-control" value="<?php echo $this->session->userdata('user')->staff_id?>" required>
            <input type="hidden" id="unit_id" name="unit_id" class="form-control" value="<?php echo $this->session->userdata('user')->unit_id?>" required>
          </div>
          <div class="form-group">
            <label for="period">Unit:</label>
            <select id="period" name="unit_id" class="form-control" required>
              <?php
               $staff_id = $this->session->userdata('user')->staff_id;
              $units = Modules::run('lists/units',$staff_id);
              $sessionunit = $this->session->userdata('user')->unit_id;
               foreach($units as $unit):
               ?>
              <option value="<?php echo $id =$unit->unit_id?>" <?php if($sessionunit==$id){echo "selected"; }?>><?php echo $unit->unit_name?></option>
              <?php
              endforeach; ?>
              ?>
             
            </select>
          </div>
          <div class="form-group">
            <label for="description">Description:</label>
            <textarea id="description" name="description" class="form-control" required></textarea>
          </div>
          <div class="form-group">
            <label for="financial_year">Financial Year:</label>
            <input type="text" id="financial_year" name="financial_year" value="<?php echo date('Y')?>" class="form-control" readonly>
          </div>
          <div class="form-group">
            <label for="period">Period:</label>
            <select id="period" name="period" class="form-control" required>
              <option value="Q1">Q1</option>
              <option value="Q2">Q2</option>
              <option value="Q3">Q3</option>
              <option value="Q4">Q4</option>
            </select>
          </div>


          <button type="submit" class="btn btn-primary">Submit</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit Data Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Quarterly Output</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form action="<?php echo site_url('tasks/edit_outputs'); ?>" method="post">
          <input type="hidden" name="quarterly_output_id" id="edit_quarterly_output_id">
          <div class="form-group mb-2">
            <input type="hidden" id="edit_unit_id" name="unit_id" class="form-control" required>
          </div>
          <div class="form-group mb-2">
            <label for="edit_name">Name:</label>
            <input type="text" id="edit_name" name="name" class="form-control" required>
          </div>
          <div class="form-group mb-2">
            <label for="edit_description">Description:</label>
            <textarea id="edit_description" name="description" class="form-control" required></textarea>
          </div>
          <div class="form-group mb-2">
            <label for="edit_financial_year">Financial Year:</label>
            <input type="text" id="edit_financial_year" name="financial_year" class="form-control" required>
          </div>
          <div class="form-group mb-2">
            <label for="edit_period">Period:</label>
            <select id="edit_period" name="period" class="form-control" required>
              <option value="Q1">Q1</option>
              <option value="Q2">Q2</option>
              <option value="Q3">Q3</option>
              <option value="Q4">Q4</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete the data?
      </div>
      <div class="modal-footer">
        <form action="<?php echo site_url('tasks/delete_outputs'); ?>" method="post">
          <input type="hidden" name="quarterly_output_id" id="delete_quarterly_output_id">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Yes, Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
  // Edit Modal: Populate fields with data from the clicked row.
  var editModal = document.getElementById('editModal');
  editModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    // Extract data from data attributes
    var outputId      = button.getAttribute('data-id');
    var unitId        = button.getAttribute('data-unit_id');
    var name          = button.getAttribute('data-name');
    var description   = button.getAttribute('data-description');
    var financialYear = button.getAttribute('data-financial_year');
    var period        = button.getAttribute('data-period');
   

    // Update the modal's form fields
    editModal.querySelector('#edit_quarterly_output_id').value = outputId;
    editModal.querySelector('#edit_unit_id').value            = unitId;
    editModal.querySelector('#edit_name').value                 = name;
    editModal.querySelector('#edit_description').value          = description;
    editModal.querySelector('#edit_financial_year').value       = financialYear;
    editModal.querySelector('#edit_period').value               = period;

  });

  // Delete Modal: Set the ID for deletion
  var deleteModal = document.getElementById('deleteModal');
  deleteModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var outputId = button.getAttribute('data-id');
    deleteModal.querySelector('#delete_quarterly_output_id').value = outputId;
  });




  $(document).ready(function () {

    $("#datepicker").datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true
    });

  // CSRF token variables
  var csrfName = "<?= $this->security->get_csrf_token_name(); ?>";
  var csrfHash = "<?= $this->security->get_csrf_hash(); ?>";

  // Submit the "Add Quarterly Output" form via AJAX
  $("#addModal form").on("submit", function (e) {
    e.preventDefault(); // Prevent default form submission
    var form = $(this);
    // Append the CSRF token to the form data
    var formData = form.serialize() + '&' + csrfName + '=' + csrfHash;
    
    $.ajax({
      url: form.attr("action"),
      method: "POST",
      data: formData,
      dataType: "json", // expecting JSON response from server
      success: function (response) {
        show_notification(response.message, 'success');
        $("#addModal").modal("hide");
        // Optionally, refresh your data table or page content here.
      },
      error: function (xhr, status, error) {
        // Try to extract an error message from the server response, if available
        var errMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : error;
        show_notification(errMsg, 'error');
      }
    });
  });

  // Submit the "Edit Quarterly Output" form via AJAX
  $("#editModal form").on("submit", function (e) {
    e.preventDefault();
    var form = $(this);
    var formData = form.serialize() + '&' + csrfName + '=' + csrfHash;
    
    $.ajax({
      url: form.attr("action"),
      method: "POST",
      data: formData,
      dataType: "json",
      success: function (response) {
        show_notification(response.message, 'success');
        $("#editModal").modal("hide");
        // Optionally, refresh your data table or page content here.
      },
      error: function (xhr, status, error) {
        var errMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : error;
        show_notification(errMsg, 'error');
      }
    });
  });

  // Submit the "Delete Confirmation" form via AJAX
  $("#deleteModal form").on("submit", function (e) {
    e.preventDefault();
    var form = $(this);
    var formData = form.serialize() + '&' + csrfName + '=' + csrfHash;
    
    $.ajax({
      url: form.attr("action"),
      method: "POST",
      data: formData,
      dataType: "json",
      success: function (response) {
        show_notification(response.message, 'success');
        $("#deleteModal").modal("hide");
        // Optionally, refresh your data table or page content here.
      },
      error: function (xhr, status, error) {
        var errMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : error;
        show_notification(errMsg, 'error');
      }
    });
  });
});

</script>


