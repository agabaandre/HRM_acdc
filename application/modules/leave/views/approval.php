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
              <input type="text" id="start-date-filter" class="form-control datepicker" />
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="end-date-filter">End Date:</label>
              <input type="text" id="end-date-filter" class="form-control datepicker" />
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
            <th>#</th>
            <th>Staff Name</th>
            <th>Leave Type</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Requested Days</th>
            <th>Leave Balance (Days)</th>
            <th>Remarks</th>
            <th>Supporting File</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 1;
          foreach ($leaves as $leave) : ?>
            <tr <?php if($leave['overall_status']== 'Approved'){
              echo "class='table-success'";} else if
                ($leave['overall_status'] == 'Rejected') {
                  echo "class='table-warning'";}
            ; ?>>
              <td><?php echo $i++; ?>
              <td><?php echo $leave['fname'] . ' ' . $leave['lname']; ?></td>
              <td><?php echo $leave['leave_name']; ?></td>
              <td><?php echo $leave['start_date']; ?></td>
              <td><?php echo $leave['end_date']; ?></td>
              <td><?php echo $leave['requested_days']; ?></td>
              <td style="font-weight:bold;"><?php if ($balance = leave_balance($leave['staff_id'], $leave['leave_id']) > 0) {
                                              echo '<span class="text-success">' . $balance . '</span>';
                                            } else {
                                              '<span class="text-danger">' . $balance . '</span>';
                                            } ?></td>
              <td><?php echo $leave['remarks']; ?></td>

              <td><?php if (!empty($leave['supporting_documentation']) && ($this->session->userdata('user')->role == 20)) : ?><a href='<?php echo base_url() ?>/staff/leave/<?php echo $leave['supporting_documentation']; ?>'>Request Support File</a><?php endif; ?></td>

              <td style="font-weight:bold;">
                <!-- <approval level0 -->
                <?php if (($leave['approval_status'] == 'Pending')) : ?>
                  <a href="<?php echo base_url() ?>leave/approve/<?php echo $leave['request_id'] ?>/supporting_staff/16" class="btn btn-success approve-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Accept Support Role</a>
                  <a href="<?php echo base_url() ?>leave/approve/<?php echo $leave['request_id'] ?>/supporting_staff/32" class="btn btn-danger reject-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Reject Support Role</a>
                <?php endif; ?>
                <?php if ($leave['approval_status'] == 'Approved') : ?>
                  <span class="text-success">Support Role Accepted</span><br />
                <?php endif; ?>
                <?php if ($leave['approval_status'] == 'Rejected') :
                ?>
                  <span class="text-danger">Support Role Rejected</span><br />
                <?php endif;
                ?>
                <!-- <approval level1> -->


                <?php if (($leave['approval_status'] == 'Approved') && ($leave['approval_status1'] == 'Pending')) : ?>
                  <a href="<?php echo base_url() ?>leave/approve/<?php echo $leave['request_id'] ?>/hr/16" class="btn btn-success approve-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Approve</a>
                  <a href="<?php echo base_url() ?>leave/approve/<?php echo $leave['request_id'] ?>/hr/32" class="btn btn-danger reject-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Reject</a>
                <?php endif; ?>
                <?php if ($leave['approval_status1'] == 'Approved') : ?>
                  <span class="text-success">Approved by HR </span><br />
                <?php endif; ?>
                <?php if ($leave['approval_status1'] == 'Rejected') :
                ?>
                  <span class="text-danger">Rejected by HR</span> <br />
                <?php endif; ?>


                <!-- <approval level2> -->


                <?php if (($leave['approval_status1'] == 'Approved') && ($leave['approval_status2'] == 'Pending')) : ?>
                  <a href="<?php echo base_url() ?>leave/approve/<?php echo $leave['request_id'] ?>/supervisor/16" class="btn btn-success approve-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Approve</a>
                  <a href="<?php echo base_url() ?>leave/approve/<?php echo $leave['request_id'] ?>/supervisor/32" class="btn btn-danger reject-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Reject</a>
                <?php endif; ?>
                <?php if ($leave['approval_status2'] == 'Approved') : ?>
                  <span class="text-success">Approved by Supervisor</span><br />
                <?php endif; ?>
                <?php if ($leave['approval_status2'] == 'Rejected') :
                ?>
                  <span class="text-danger">Rejected by Supervisor</span><br />
                <?php endif;
                ?>

                <!-- <approval level3> -->

                <?php if (($leave['approval_status2'] == 'Approved') && ($leave['approval_status3'] == 'Pending') && ($leave['approval_status1'] == 'Approved')) : ?>
                  <a href="<?php echo base_url() ?>leave/approve/<?php echo $leave['request_id'] ?>/hod/16" class="btn btn-success approve-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Approve</a>
                  <a href="<?php echo base_url() ?>leave/approve/<?php echo $leave['request_id'] ?>/hod/32" class="btn btn-danger reject-btn" data-leave-id="<?php echo $leave['leave_id']; ?>">Reject</a>
                <?php endif; ?>
                <?php if ($leave['approval_status3'] == 'Approved') : ?>
                  <span class="text-success">Approved by Hod</span><br/>
                <?php endif; ?>
                <?php if ($leave['approval_status3'] == 'Rejected') :
                ?>
                  <span class="text-danger">Rejected by HoD</span><br/>
                <?php endif;
                ?>
                <?php if($leave['approval_status3'] == 'Rejected'|| $leave['approval_status2'] == 'Rejected'|| $leave['approval_status1'] == 'Rejected'|| $leave['approval_status'] == 'Rejected'){?>

                  
                <?php } ?>


              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    </div>

  </div>
</div>