<!-- Display leave application status for employees -->
<div class="card">

    <div class="card-body">

        <div class="table-responsive">
            <!-- Leave application status table -->
            <div class="row">
                <div class="col-md-12">
                    <form id="leave-filter-form" method="get" action="<?= base_url('leave/status'); ?>">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="status">Status:</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="start_date">Start Date:</label>
                                <input type="date" name="start_date" id="start_date" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date">End Date:</label>
                                <input type="date" name="end_date" id="end_date" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary mt-4">Apply Filters</button>
                            </div>
                        </div>
                    </form>
                    <table id="leave-table" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Staff ID</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Leave Type</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaves as $leave) : ?>
                                <tr data-status="<?= $leave['approval_status']; ?>">
                                    <td><?= $leave['staff_id']; ?></td>
                                    <td><?= $leave['fname']; ?></td>
                                    <td><?= $leave['lname']; ?></td>
                                    <td><?= $leave['start_date']; ?></td>
                                    <td><?= $leave['end_date']; ?></td>
                                    <td><?= $leave['leave_name']; ?></td>
                                    <td><?= $leave['approval_status']; ?></td>
                                    <td>
                                        <?php if ($leave['approval_status'] === 'Rejected') : ?>
                                            <button class="reapply-btn btn btn-primary" data-leave-id="<?= $leave['leave_id']; ?>">Re-apply</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                $(document).ready(function() {
                    // Re-apply leave via Ajax
                    $('.reapply-btn').click(function(e) {
                        e.preventDefault();
                        var leaveId = $(this).data('leave-id');
                        $.ajax({
                            url: '<?= base_url('leave/reapply/'); ?>' + leaveId,
                            type: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'success') {
                                    var leave = response.leave;
                                    var row = $('tr').filter(function() {
                                        return $(this).find('td:first').text() === leave.staff_id.toString();
                                    });
                                    row.find('td:eq(6)').text(leave.approval_status);
                                    row.find('td:eq(7)').empty();
                                }
                            }
                        });
                    });
                });
            </script>

        </div>
    </div>
</div>