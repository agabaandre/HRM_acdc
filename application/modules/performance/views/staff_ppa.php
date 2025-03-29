<div class="card">
  <div class="card-body">
    <h4 class="card-title"><?= $title ?></h4>
    <div class="table-responsive">
      <table id="ppa-table" class="table mydata table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>Submission Date</th>
            <th>Period</th>
            <th>Objectives</th>
            <th>Approval Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1;
		  //dd($plans);
          foreach ($plans as $plan): ?>
            <tr data-status="<?= $plan['overall_status']; ?>">
              <td><?= $i++; ?></td>
              <td><?= date('d M Y', strtotime($plan['created_at'])) ?></td>
              <td><?= str_replace('-',' ',$plan['performance_period']); ?></td>
              <td>
                <?php
                $objs = json_decode($plan['objectives']);
                $j = 1;
                foreach ($objs as $key => $ob) {
                  echo '<p>' . $j++ . '. ' . ($ob->objective ?? '') . '</p><hr>';
                }
                ?>
              </td>
              <td>
                <?php if ($plan['overall_status'] === 'Approved'): ?>
                  <span class="badge bg-success">Approved</span>
                <?php elseif ($plan['overall_status'] === 'Returned'): ?>
                  <span class="badge bg-danger">Returned</span>
                <?php elseif ($plan['overall_status'] === 'Submitted'): ?>
                  <span class="badge bg-warning text-dark">Pending </span>
                <?php endif; ?>
              </td>
              <td>
                <a href="<?php echo base_url()?>performance/view_ppa/<?=$plan['entry_id']; ?>" class="btn btn-primary btn-sm" >
                  <i class="fa fa-eye"></i> Preview
				</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

