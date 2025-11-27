<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Endterm Review - Staff Consent Required</title>
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

    .alert-box {
      background-color: #fff3cd;
      border: 1px solid #ffc107;
      border-radius: 5px;
      padding: 15px;
      margin: 20px 0;
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
      <h1>Endterm Review - Action Required</h1>
    </div>

    <!-- Body Content -->
    <div class="content">
      <p>Dear <?= htmlspecialchars($name) ?>,</p>

      <div class="alert-box">
        <p><strong>Action Required:</strong> Your first supervisor has approved your Endterm Review for the period <strong><?= htmlspecialchars($period) ?></strong>. You are now required to provide your consent.</p>
      </div>

      <p>Please review the overall rating assigned by your supervisor and provide your consent by:</p>
      <ul>
        <li>Confirming that you formally discussed the results with your supervisor</li>
        <li>Accepting or rejecting the overall rating assigned</li>
      </ul>

      <p>The deadline for providing consent is <strong><?= date('F d, Y', strtotime($deadline)) ?></strong>.</p>

      <p>You can access your Endterm Review by logging into the staff portal:</p>

      <p>
        <a href="<?php echo $_ENV['PRODUCTION_URL'].'performance/endterm/endterm_review/' . $entry_id . '/' . $staff_id; ?>" class="btn">Review and Provide Consent</a>
      </p>

      <p><strong>Important:</strong> After you provide consent, the second supervisor will be notified to review and approve your Endterm Review.</p>

      <p>Thank you for your attention.</p>

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

