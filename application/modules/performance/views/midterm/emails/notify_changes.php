<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>PPA Update Notification</title>
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
      background-color: #ffffff;
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
      padding: 20px 0;
    }
    h1 {
      color: #333333;
      font-size: 20px;
      margin-bottom: 20px;
    }
    p {
      line-height: 1.6;
      color: #444444;
      font-size: 14px;
    }
    .btn {
      display: inline-block;
      background-color: #007b5e;
      color: #ffffff !important;
      padding: 10px 18px;
      border-radius: 5px;
      text-decoration: none;
      margin-top: 20px;
    }
    .btn:hover {
    background-color: #005844; /* optional darker shade */
    color: #ffffff !important; /* ensure text remains visible */
    text-decoration: none;
    }
    .footer {
      text-align: center;
      font-size: 12px;
      color: #777777;
      margin-top: 30px;
      padding-top: 15px;
      border-top: 1px solid #dddddd;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC Logo">
    </div>

    <!-- Main Content -->
    <div class="content">
      <h1>Your PPA Has Been Updated</h1>

      <p>Dear <strong><?= $name ?></strong>,</p>

      <p>
        We would like to inform you that your submitted PPA for the period: 
        <strong><?= str_replace('-', ' ', $period); ?></strong> has been <strong>updated</strong> by your supervisor <strong><?= $supervisor_name ?></strong>.
      </p>

      <p>
        These updates may include feedback, changes in objectives, KPIs, timelines, and trainings.
      </p>


      <p>
        <a href="<?= site_url('performance/view_ppa/' . $entry_id . '/' . $staff_id); ?>" class="btn">View Updated PPA</a>
      </p>

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
