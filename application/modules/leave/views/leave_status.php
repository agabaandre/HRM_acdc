<!-- Display leave application status for employees -->
<div class="card">

    <div class="card-body">

        <div class="table-responsive">
            <!-- Leave application status table -->
            <div class="row">
                <div class="col-md-12">
                    <a class="btn btn-primary px-5 radius-30" href="<?php echo base_url() ?>leave/request"><i class="fa fa-plus"></i>Leave Application</a>


                    <form id="leave-filter-form" method="get" action="<?= base_url('leave/status'); ?>">
                        <div class="row mb-3">

                            <div class="col-md-3">
                                <label for="start_date">Start Date:</label>
                                <input type="text" name="start_date" id="start_date" class="form-control datepicker">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date">End Date:</label>
                                <input type="text" name="end_date" id="end_date" class="form-control datepicker">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date">Status:</label>
                                <select class="form-control select2" name="status">
                                    <option value="">All</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary mt-4">Apply Filters</button>
                            </div>

                        </div>
                    </form>
                    <table id="leave-table" class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Leave Start Date</th>
                                <th>Requested Days</th>
                                <th>Leave Type</th>
                                <th>Level Status</th>
                                <th>Overall Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            foreach ($leaves as $leave) : ?>
                                <tr data-status="<?= $leave['overall_status']; ?>" <?php if ($leave['overall_status'] == 'Approved') { ?>style="background:#d2f0d7 !important" ;<?php } else if ($leave['overall_status'] == 'Rejected') { ?>style="background:#ffcdcd !important" ; <?php } ?>>
                                    <td><?= $i++; ?></td>
                                    <td><?= $leave['start_date']; ?></td>
                                    <td><?= date_difference($leave['end_date'], $leave['start_date']); ?></td>
                                    <td><?= $leave['leave_name']; ?></td>
                                    <td><?= $leave['approval_status'] . '-(SS) <br/> ' . $leave['approval_status1'] . '-(HR) <br/> ' . $leave['approval_status2'] . '-(S) <br/> ' . $leave['approval_status3'] . '-(HoD)<br/> '; ?></td>

                                    <td><?= $leave['overall_status']; ?></td>
                                    <td>
                                        <?php if (($leave['approval_status'] == 'Rejected') or ($leave['approval_status1'] == 'Rejected') or ($leave['approval_status2'] == 'Rejected') or ($leave['approval_status3'] == 'Rejected')) : ?>
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