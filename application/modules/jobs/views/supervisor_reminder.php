<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>PPA Approval Reminder</title>
    <style>
      body {
        margin: 0; padding: 0;
        background-color: #f4f4f4;
        font-family: Arial, sans-serif;
      }
      .container {
        max-width: 700px;
        margin: 0 auto;
        background: #ffffff;
        padding: 25px 30px;
        border: 1px solid #e1e1e1;
        border-radius: 6px;
      }
      .header {
        text-align: center;
        margin-bottom: 20px;
      }
      .header img {
        max-height: 80px;
      }
      .content h1 {
        color: #07579A;
        font-size: 20px;
      }
      .content p {
        line-height: 1.6;
        color: #333;
        font-size: 14px;
      }
      .btn {
        display: inline-block;
        background-color: #07579A;
        color: #ffffff !important;
        padding: 10px 18px;
        border-radius: 5px;
        text-decoration: none;
        margin: 10px 0 20px;
      }
      .table-container {
        margin-top: 20px;
        border: 1px solid #dddddd;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
      }
      table th, table td {
        padding: 10px;
        border: 1px solid #ddd;
      }
      table th {
        background-color: #119A48;
        color: white;
        text-align: left;
      }
      .footer {
        text-align: center;
        font-size: 12px;
        color: #777777;
        margin-top: 30px;
      }
    </style>
  </head>
  <body>
    <div class="container">
      <!-- Header -->
      <div class="header">
        <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC">
      </div>

      <!-- Content -->
      <div class="content">
        <h1>PPA Approval Reminder</h1>
        <p>Dear <strong><?= $supervisor_name ?></strong>,</p>
        <p>
          You have pending Performance Planning and Appraisal (PPA) submissions from your staff for the period <strong><?= str_replace('-', ' ', $period) ?></strong>.
        </p>
        <p>
          Please review and approve them before the deadline: <strong><?= date('d M, Y', strtotime($deadline)) ?></strong>.
        </p>
        <a href="<?= site_url('performance/assigned') ?>" class="btn">View All Assigned PPAs</a>

        <?php if (!empty($pending_list)) : ?>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Staff Name</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php $i = 1; foreach ($pending_list as $row): ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $row->staff_name ?></td>
                    <td>
                      <a href="<?= site_url('performance/view_ppa/' . $row->entry_id . '/' . $row->staff_id) ?>" class="btn" style="padding: 6px 12px;">Review</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <p style="margin-top: 20px;">Regards,<br><strong>HR Team, Africa CDC</strong></p>
      </div>

      <!-- Footer -->
      <div class="footer">
        &copy; <?= date('Y') ?> Africa CDC. All rights reserved.
      </div>
    </div>
  </body>
</html>
