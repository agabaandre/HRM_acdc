<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Midterm Review Update Saved Notification</title>
  <style type="text/css">
    body {
      margin: 0;
      padding: 0;
      background-color: #f8f9fa;
      font-family: Arial, sans-serif;
    }
    .container {
      max-width: 650px;
      margin: 0 auto;
      background: #ffffff;
      padding: 20px;
      border: 1px solid #dddddd;
    }
    .header {
      text-align: center;
      padding: 15px 0;
    }
    .header img {
      height: 80px;
    }
    .content {
      padding: 20px;
    }
    h1 {
      color: #333333;
    }
    p {
      line-height: 1.6;
      color: #444444;
    }
    .btn {
      display: inline-block;
      background-color: #007b5e;
      color: #ffffff;
      padding: 10px 18px;
      border-radius: 5px;
      text-decoration: none;
      margin-top: 15px;
    }
    .btn:hover {
      background-color: #005844;
      color: #ffffff !important;
      text-decoration: none;
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

    <!-- Main Content -->
    <div class="content">
      <h1>Midterm Review Changes Saved</h1>

      <p>Dear <strong><?= $supervisor_name ?></strong>,</p>

      <p>
        This is to confirm that the updates you made to the <strong>Midterm Review</strong> of
        <strong><?= $name ?></strong>, for the period: <strong><?= str_replace('-', ' ', $period); ?></strong>, have been successfully saved.
      </p>

      <a href="<?= site_url('performance/midterm/midterm_review/' . $entry_id . '/' . $staff_id); ?>" class="btn" style="color:#fff !important;">View Updated Midterm</a>

      <p>
        Best regards,<br>
        <strong>HR Team, Africa CDC</strong>
      </p>
    </div>

    <!-- Footer -->
    <div class="footer">
      &copy; <?= date('Y') ?> Africa CDC. All rights reserved.
    </div>
  </div>
</body>
</html>
