<style>
  body { font-family: Arial, sans-serif; color: #1f2937; }
  .container { max-width: 900px; margin: 0 auto; }
  .title { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
  .meta { color: #4b5563; margin-bottom: 12px; }
  .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; margin-right: 6px; }
  .badge-ppa { background: #dbeafe; color: #1d4ed8; }
  .badge-mid { background: #fef3c7; color: #92400e; }
  .badge-end { background: #d1fae5; color: #065f46; }
  table { width: 100%; border-collapse: collapse; margin-top: 14px; }
  th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; font-size: 13px; }
  th { background: #f9fafb; }
  .btn {
    display: inline-block;
    margin-top: 14px;
    padding: 10px 14px;
    border-radius: 6px;
    background: #2563eb;
    color: #fff !important;
    text-decoration: none;
    font-weight: 600;
  }
</style>

<div class="container">
  <div class="title">Unified Performance Approval Reminder</div>
  <div class="meta">
    Hello <?= htmlspecialchars($supervisor_name) ?>,<br>
    You have pending approvals requiring your action as first or second approver.
  </div>

  <div class="meta">
    <span class="badge badge-ppa">PPA: <?= (int)($type_counts['ppa'] ?? 0) ?></span>
    <span class="badge badge-mid">Midterm: <?= (int)($type_counts['midterm'] ?? 0) ?></span>
    <span class="badge badge-end">Endterm: <?= (int)($type_counts['endterm'] ?? 0) ?></span>
    <span style="margin-left: 8px;">Generated: <?= htmlspecialchars($generated_on) ?></span>
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Staff</th>
        <th>Type</th>
        <th>Period</th>
        <th>Status</th>
        <th>Submitted</th>
        <th>Review</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($pending_list as $row): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($row['staff_name']) ?></td>
          <td><?= htmlspecialchars($row['approval_type']) ?></td>
          <td><?= htmlspecialchars($row['period']) ?></td>
          <td><?= htmlspecialchars($row['status']) ?></td>
          <td><?= !empty($row['submitted_at']) ? date('d M Y', strtotime($row['submitted_at'])) : '-' ?></td>
          <td><a href="<?= htmlspecialchars($row['review_url']) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <a href="<?= htmlspecialchars($pending_url) ?>" class="btn">View all pending approvals</a>

  <p style="margin-top: 16px; color: #4b5563; font-size: 12px;">
    Note: This email includes copied recipients from the pending list for tracking.
  </p>
</div>
