<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Performance Approval Reminder</title>
  <style type="text/css">
    body {
      font-family: Arial, sans-serif;
      font-size: 14px;
      color: #333333;
      background-color: #f4f4f4;
      margin: 0;
      padding: 0;
    }

    .container {
      width: 100%;
      max-width: 900px;
      margin: 0 auto;
      background-color: #ffffff;
      border: 1px solid #dddddd;
      padding: 30px;
      box-shadow: 0 0 10px rgba(0,0,0,0.05);
      border-radius: 8px;
    }

    .header {
      text-align: center;
      margin-bottom: 10px;
    }

    .header img {
      max-height: 70px;
    }

    .header h1 {
      margin: 10px 0 0 0;
      font-size: 22px;
      color: #119A48;
    }

    .content {
      padding: 20px 0;
      line-height: 1.6;
    }

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
      padding: 10px 20px;
      background-color: #119A48;
      color: #ffffff !important;
      text-decoration: none;
      border-radius: 5px;
      font-weight: bold;
      margin-top: 15px;
      border: 1px solid #0d7a3a;
    }

    .footer {
      font-size: 12px;
      color: #888888;
      text-align: center;
      padding-top: 30px;
      border-top: 1px solid #eeeeee;
    }

    @media only screen and (max-width: 600px) {
      .container {
        padding: 20px;
      }

      .btn {
        width: 100%;
        text-align: center;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC Logo">
      <h1>Performance Approval Reminder</h1>
    </div>

    <div class="content">
      <p>Hello <?= htmlspecialchars($supervisor_name) ?>,</p>

      <p>You have pending approvals requiring your action as first or second approver.</p>

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

      <p>
        <a href="<?= htmlspecialchars($pending_url) ?>" class="btn" style="background-color:#119a48;color:#ffffff !important;text-decoration:none;">View all pending approvals</a>
      </p>

      <p style="color: #4b5563; font-size: 13px;">
        This reminder is sent to you as an approver. Use the button above to open your full pending list in the staff portal.
      </p>

      <p>Thank you for your attention and commitment.</p>

      <p>Best regards,<br>
      <strong>Human Resources</strong></p>
    </div>

    <div class="footer">
      &copy; <?= date('Y') ?> Africa CDC. All rights reserved.
    </div>
  </div>
</body>
</html>
