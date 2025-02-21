<?php
// Assuming $title and $logs are defined in your controller
?>
<style>
  /* Adjust modal styling if needed */
  .modal {
    clear: both;
    position: fixed;
    margin-left: 100px;
    margin-top: 120px;
    z-index: 1050; /* BS5 default modal z-index */
    overflow-x: auto;
    overflow-y: auto;
  }
</style>

<div class="card">
  <div class="card-header">
    <h3><?php echo $title; ?></h3>
  </div>
  <div class="card-body">
    <!-- Filter Form -->
    <form class="row g-3 mb-4" method="GET" action="<?php echo base_url('auth/userLogs'); ?>">
      <div class="col-md-3">
        <label for="filterName" class="form-label">User Name</label>
        <input type="text" class="form-control" id="filterName" name="name" placeholder="Search by name" value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>">
      </div>
      <div class="col-md-3">
        <label for="filterEmail" class="form-label">Email</label>
        <input type="email" class="form-control" id="filterEmail" name="email" placeholder="Search by email" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
      </div>
      <div class="col-md-3">
        <label for="filterFromDate" class="form-label">From Date</label>
        <input type="date" class="form-control" id="filterFromDate" name="from_date" value="<?php echo isset($_GET['from_date']) ? htmlspecialchars($_GET['from_date']) : ''; ?>">
      </div>
      <div class="col-md-3">
        <label for="filterToDate" class="form-label">To Date</label>
        <input type="date" class="form-control" id="filterToDate" name="to_date" value="<?php echo isset($_GET['to_date']) ? htmlspecialchars($_GET['to_date']) : ''; ?>">
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="<?php echo base_url('admin/userLogs'); ?>" class="btn btn-secondary">Reset</a>
      </div>
    </form>



    <div class="table-responsive">
      <?php echo $links?>
      <table class="table table-striped">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Action</th>
            <th>Date &amp; Time</th>
            <th>User</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php $no = 0; ?>
          <?php foreach ($logs as $logEntry): $no++; ?>
            <tr id="user<?php echo $logEntry->user_log_id; ?>">
              <td><?php echo $no; ?>.</td>
              <td><?php echo substr(ucwords($logEntry->action), 0, 35) . "..."; ?></td>
              <td>
                <?php
                  // Combine date and time for display
                  echo date("Y-m-d H:i", strtotime($logEntry->date_loged_in . ' ' . $logEntry->time_loged_in));
                ?>
              </td>
              <td><?php echo ucwords($logEntry->name); ?></td>
              <td>
                <a href="#" data-bs-toggle="modal" data-bs-target="#check<?php echo $no; ?>">
                  <i class="bi bi-pencil-square"></i> More...
                </a>
              </td>
            </tr>

            <!-- Modal for Detailed Log -->
            <div class="modal fade" id="check<?php echo $no; ?>" tabindex="-1" aria-labelledby="modalLabel<?php echo $no; ?>" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="modalLabel<?php echo $no; ?>">Activity Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <h5><?php echo ucwords($logEntry->name); ?></h5>
                    <hr>
                    <p><?php echo $logEntry->action; ?></p>
                    <br>
                    <small>Date &amp; Time: <?php echo date("Y-m-d H:i", strtotime($logEntry->date_loged_in . ' ' . $logEntry->time_loged_in)); ?></small>
                    <br>
                    <small>Email: <?php echo $logEntry->email; ?></small>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Clear Logs Confirmation Modal -->
    <div class="modal fade" id="clearlogs" tabindex="-1" aria-labelledby="clearLogsLabel" aria-hidden="true">
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="clearLogsLabel">Clear Activity Log</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Do you want to clear all logs?</p>
          </div>
          <div class="modal-footer">
            <a href="<?php echo base_url(); ?>admin/clearLogs" class="btn btn-danger">Yes, Clear All</a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script type="text/javascript">
  // Add any custom JavaScript or initialize Bootstrap components if needed.
  // Note: Bootstrap 5 no longer requires jQuery.
</script>
