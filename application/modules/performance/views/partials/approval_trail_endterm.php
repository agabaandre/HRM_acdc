<h4 class="mt-4">Approval Trail</h4>

<div class="table-responsive">
  <table class="table table-bordered table-sm">
    <thead class="table-light">
      <tr>
        <th>Name</th>
        <th>Role</th>
        <th>Action</th>
        <th>Date</th>
        <th>Comment</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($approval_trail)): ?>
        <?php foreach ($approval_trail as $log): 
          $logged = Modules::run('auth/contract_info', $log->staff_id);
          $role = 'Other';

          if ($log->staff_id == $ppa->staff_id) {
              $role = 'Staff';
          } elseif ($log->staff_id == $ppa->endterm_supervisor_1) {
              $role = 'First Supervisor';
          } elseif (!empty($ppa->endterm_supervisor_2) && $log->staff_id == $ppa->endterm_supervisor_2) {
              $role = 'Second Supervisor';
          }
        ?>
          <tr>
            <td><?= $logged->title . ' ' . $logged->fname . ' ' . $logged->lname . ' ' . $logged->oname ?></td>
            <td><?= $role ?></td>
            <td><?= htmlspecialchars((string) $log->action) ?></td>
            <td><?= date('d M Y H:i', strtotime($log->created_at)) ?></td>
            <td><?= htmlspecialchars((string) $log->comments) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-center text-muted">No approval activity yet.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

