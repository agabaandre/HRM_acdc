<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Employee Performance Midterm Review Submission Reminder</title>
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
    <!-- Header -->
    <div class="header">
      <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC Logo">
      <h1>Midterm Performance Submission Reminder</h1>
    </div>

    <!-- Body Content -->
    <div class="content">
      <p>Dear <?= htmlspecialchars($name) ?>,</p>

      <p>This is a kind reminder to submit your <strong>Midterm Review Performance </strong> form for the period <strong><?= htmlspecialchars($period) ?></strong>.</p>

      <p>The deadline for submission is <strong><?= date('F d, Y', strtotime($deadline)) ?></strong>. Kindly ensure that your PPA is completed and submitted before this date.</p>

      <p>You can access the PPA form by logging into the staff portal:</p>

      <p>
        <a href="https://tools.africacdc.org/staff/performance" class="btn">Submit My PPA</a>
      </p>

      <p>If you have already submitted your Midterm Review, kindly ignore this reminder.</p>

      <p>Thank you for your attention and commitment.</p>

      <p>Best regards,<br>
      <strong>Human Resources</strong></p>
    </div>

    <!-- Footer -->
    <div class="footer">
      &copy; <?= date('Y') ?> Africa CDC. All rights reserved.
    </div>
  </div>
</body>
</html>
