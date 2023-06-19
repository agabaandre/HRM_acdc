<div class="card">

  <div class="card-body">

    <div class="table-responsive">

      <!-- Filters -->
      <form id="leave-filters" class="mb-4">
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label for="status-filter">Status:</label>
              <select id="status-filter" class="form-control">
                <option value="">All</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="start-date-filter">Start Date:</label>
              <input type="date" id="start-date-filter" class="form-control" />
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="end-date-filter">End Date:</label>
              <input type="date" id="end-date-filter" class="form-control" />
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group" style="margin-top:20px;">
              <button type="submit" class="btn  btn-sm btn-secondary">Apply Filters</button>
              <button type="button" id="reset-filters" class="btn  btn-sm btn-danger">Reset Filters</button>
            </div>
          </div>
          </div>
      </form>

      <table id="leave-table" class="table">
        <thead>
          <tr>
            <th>Staff ID</th>
            <th>Staff Name</th>
            <th>Leave Type</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Requested Days</th>
            <th>Remarks</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($leaves as $leave) : ?>
            <tr data-status="<?php echo $leave['approval_status']; ?>">
              <td><?php echo $leave['staff_id']; ?></td>
              <td><?php echo $leave['fname'] . ' ' . $leave['lname']; ?></td>
              <td><?php echo $leave['leave_name']; ?></td>
              <td><?php echo $leave['start_date']; ?></td>
              <td><?php echo $leave['end_date']; ?></td>
              <td><?php echo $leave['requested_days']; ?></td>
              <td><?php echo $leave['remarks']; ?></td>
              <td>
                <?php if ($leave['approval_status'] == 'Pending') : ?>
                  <a href="#" class="btn btn-success approve-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Approve</a>
                  <a href="#" class="btn btn-danger reject-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Reject</a>
                <?php else : ?>
                  <span class="text-success">Approved</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script>
        $(document).ready(function() {
          // Handle filter form submission
          $('#leave-filters').submit(function(e) {
            e.preventDefault();
            applyFilters();
          });

          // Handle filter reset
          $('#reset-filters').click(function() {
            $('#leave-filters')[0].reset();
            applyFilters();
          });

          // Apply filters
          function applyFilters() {
            var statusFilter = $('#status-filter').val();
            var startDateFilter = $('#start-date-filter').val();
            var endDateFilter = $('#end-date-filter').val();

            $('#leave-table tbody tr').hide().each(function() {
              var status = $(this).data('status');
              var startDate = $(this).find('td:eq(3)').text();
              var endDate = $(this).find('td:eq(4)').text();

              if ((statusFilter === '' || statusFilter === status) &&
                (startDateFilter === '' || startDate >= startDateFilter) &&
                (endDateFilter === '' || endDate <= endDateFilter)) {
                $(this).show();
              }
            });
          }

          // Approve leave via Ajax
          $('.approve-btn').click(function(e) {
            e.preventDefault();
            var leaveId = $(this).data('leave-id');
            $.ajax({
              url: '<?php echo base_url('leave/approve/'); ?>' + leaveId,
              type: 'POST',
              dataType: 'json',
              success: function(response) {
                if (response.status === 'success') {
                  var leave = response.leave;
                  var row = $('tr').filter(function() {
                    return $(this).find('td:first').text() === leave.staff_id.toString();
                  });
                  row.find('.approve-btn').replaceWith('<span class="text-success">Approved</span>');
                  row.find('.reject-btn').replaceWith('<span class="text-danger">Rejected</span>');
                }
              }
            });
          });

          // Reject leave via Ajax
          $('.reject-btn').click(function(e) {
            e.preventDefault();
            var leaveId = $(this).data('leave-id');
            $.ajax({
              url: '<?php echo base_url('leave/reject/'); ?>' + leaveId,
              type: 'POST',
              dataType: 'json',
              success: function(response) {
                if (response.status === 'success') {
                  var leave = response.leave;
                  var row = $('tr').filter(function() {
                    return $(this).find('td:first').text() === leave.staff_id.toString();
                  });
                  row.find('.approve-btn').replaceWith('<span class="text-success">Approved</span>');
                  row.find('.reject-btn').replaceWith('<span class="text-danger">Rejected</span>');
                }
              }
            });
          });
        });
      </script>


    </div>

  </div>
</div>