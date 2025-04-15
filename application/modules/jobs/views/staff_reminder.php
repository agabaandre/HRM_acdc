<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>PPA Submission Reminder</title>
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
      max-width: 650px;
      margin: 0 auto;
      background-color: #ffffff;
      border: 1px solid #dddddd;
      padding: 30px;
      box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }

    .header {
        text-align: center;
        margin-bottom: 10px;
      }
      .header img {
        max-height: 80px;
      }

    .header h2 {
      margin: 0;
      font-size: 20px;
      color: #119A48; /* Africa CDC Green */
    }

    .content {
      padding: 20px 0;
    }

    .footer {
      font-size: 12px;
      color: #888888;
      text-align: center;
      padding-top: 30px;
      border-top: 1px solid #eeeeee;
    }

    .btn {
      display: inline-block;
      padding: 10px 20px;
      background-color: #119A48;
      color: #ffffff;
      text-decoration: none;
      border-radius: 5px;
      font-weight: bold;
      margin-top: 15px;
    }
  </style>
</head>
<body>
  <div class="container">
     <!-- Header -->
     <div class="header">
        <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC">
      </div>
    <div class="content">
    <h1>PPA Submission Reminder</h1>
      <p>Dear <?= $name ?>,</p>

      <p>This is a kind reminder to submit your <strong>Performance Planning and Appraisal (PPA)</strong> form for the period <strong><?= $period ?></strong>.</p>

      <p>The extended deadline for submission is <strong><?= date('F d, Y', strtotime($deadline)) ?></strong>. Kindly ensure that your PPA is completed and submitted before this date.</p>

      <p>You can access the PPA form by logging into the staff portal:</p>

      <p>
        <a href="<?= base_url('performance') ?>" class="btn">Submit My PPA</a>
      </p>

      <p>If you have already submitted your PPA, kindly ignore this reminder.</p>

      <p>Thank you for your attention and commitment.</p>

      <p>Best regards,<br>
      Human Resources</p>
    </div>

    <div class="footer">
      &copy; <?= date('Y') ?> Africa CDC. All rights reserved.
    </div>
  </div>
</body>
</html>
