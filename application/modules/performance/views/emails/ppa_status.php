<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>PPA Status Notification</title>
    <style type="text/css">
      body {
        margin: 0; 
        padding: 0; 
        background-color: #f4f4f4; 
        font-family: Arial, sans-serif;
      }
      .container {
        width: 100%; 
        max-width: 650px; 
        margin: 0 auto; 
        background: #ffffff; 
        padding: 20px;
      }
      .header {
        text-align: center; 
        padding: 10px 0;
      }
      .header img {
        max-height: 80px;
      }
      .content {
        padding: 20px;
      }
      .footer {
        text-align: center; 
        font-size: 12px; 
        color: #888888; 
        padding: 10px 0;
      }
      h1 {
        color: #2A2A2A;
      }
      p {
        line-height: 1.5;
      }
      .btn {
        display: inline-block; 
        background-color: #117a65; 
        color: #ffffff; 
        padding: 10px 20px;
        text-decoration: none; 
        border-radius: 5px;
        margin-top: 10px;
      }
      .audit-table {
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 20px;
      }
      .audit-table th, .audit-table td {
        border: 1px solid #ccc;
        padding: 8px; 
        font-size: 13px;
        text-align: left;
      }
      .audit-table th {
        background-color: #f2f2f2;
      }
    </style>
  </head>
  <body>
    <div class="container">
      <!-- Header with Africa CDC logo -->
      <div class="header">
        <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC">
      </div>

      <!-- Email Content -->
      <div class="content">
        <h1>PPA Status Update</h1>
        <p>Dear <?php echo $name; ?>,</p>
        <p>
          This is to inform you that your Performance Planning and Appraisal (PPA) form for the period 
          <strong><?php echo str_replace('-',' ',$period); ?></strong> is <strong><?php echo $status; ?></strong>. <?php if ($status=='Returned'){ echo "Make all the neccessary adjustments from the supervisor and resubmit";

          } ?>
        </p>

        <p>You can view your PPA form and monitor progress at any time through the HR portal.</p>

        <a href="<?php echo site_url().'performance/view_ppa/'.$entry_id.'/'.$staff_id?>" class="btn" style="color:#fff !important;">View My PPA</a>

        <!-- Audit Trail Section -->
        <?php if (!empty($approval_trail)): ?>
        <h3 style="margin-top: 30px;">Approval Trail</h3>
        <table class="audit-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Role</th>
              <th>Action</th>
              <th>Date</th>
              <th>Comment</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($approval_trail as $log): 
              $staff = Modules::run('auth/contract_info', $log->staff_id);
              if ($log->staff_id == $ppa->staff_id) $role = 'Staff';
              elseif ($log->staff_id == $ppa->supervisor_id) $role = 'First Supervisor';
              elseif (!empty($ppa->supervisor2_id) && $log->staff_id == $ppa->supervisor2_id) $role = 'Second Supervisor';
              else $role = 'Other';
            ?>
            <tr>
              <td><?= $staff->fname . ' ' . $staff->lname; ?></td>
              <td><?= $role ?></td>
              <td><?= $log->action ?></td>
              <td><?= date('d M Y H:i', strtotime($log->created_at)) ?></td>
              <td><?= $log->comments ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>

        <p>Thank you for your attention and continued commitment.</p>
        <p>Best regards,<br><strong>HR Team, Africa CDC</strong></p>
      </div>

      <!-- Footer -->
      <div class="footer">
        &copy; <?= date('Y') ?> Africa CDC. All Rights Reserved.
      </div>
    </div>
  </body>
</html>
