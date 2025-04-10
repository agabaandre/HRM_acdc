<div class="container-fluid py-3 px-4">
  <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
    <h4 class="fw-bold text-uppercase text-primary">
      <i class="fa fa-users me-2 text-secondary"></i>
      Staff <?= ucwords(str_replace('_', ' ', $type)) ?>
    </h4>
    <a href="javascript:history.back()" class="btn btn-outline-dark">
      <i class="fa fa-arrow-left me-1"></i> Back
    </a>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle table-bordered table-striped mydata" id="staffStatsTable">
          <thead class="table-light">
            <tr class="text-center">
              <th>#</th>
              <th>Name</th>
              <th>SAPNO</th>
              <th>Division</th>
              <th>Contract Type</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $counter = 1; ?>
            <?php foreach ($staff_list as $staff): ?>
              <tr>
                <td class="text-center"><?= $counter++ ?></td>
                <td><?= $staff->fname . ' ' . $staff->lname ?></td>
                <td><?= $staff->SAPNO ?></td>
                <td><?= $staff->division_name ?></td>
                <td><?= $staff->contract_type ?></td>
                <td><?= $staff->status ?></td>
                <td class="text-center">
                  <?php if (!empty($staff->entry_id)): ?>
                    <a href="<?= base_url('performance/view_ppa/' . $staff->entry_id . '/' . $staff->staff_id) ?>"
                      class="btn btn-sm btn-outline-primary" target="_blank">
                      <i class="fa fa-eye"></i> View PPA
                    </a>
                  <?php else: ?>
                    <span class="text-muted">No PPA</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>

          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>