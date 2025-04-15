<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>PPA Submission Reminder</title>
  <style type="text/css">
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      background-color: #f4f4f4;
    }

    .email-wrapper {
      width: 100%;
      background-color: #f4f4f4;
      padding: 40px 0;
    }

    .email-container {
      max-width: 650px;
      margin: 0 auto;
      background-color: #ffffff;
      border: 1px solid #dddddd;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .header {
      text-align: center;
      background-color: #ffffff;
      padding: 30px 20px 10px 20px;
    }

    .header img {
      max-height: 70px;
      margin-bottom: 10px;
    }

    .header h1 {
      margin: 0;
      font-size: 22px;
      color: #119A48;
    }

    .content {
      padding: 30px 30px 10px 30px;
      color: #333333;
      font-size: 15px;
      line-height: 1.6;
    }

    .content strong {
      color: #000000;
    }

    .btn-container {
      text-align: center;
      padding: 20px 0;
    }

    .btn {
      display: inline-block;
      background-color: #119A48;
      color: #ffffff;
      padding: 12px 24px;
      text-decoration: none;
      border-radius: 5px;
      font-weight: bold;
      font-size: 15px;
    }

    .footer {
      background-color: #fafafa;
      padding: 20px 30px;
      font-size: 12px;
      color: #888888;
      text-align: center;
      border-top: 1px solid #eeeeee;
    }

    @media (max-width: 600px) {
      .email-container {
        width: 100%;
        margin: 0 10px;
      }

      .content, .footer {
        padding: 20px;
      }

      .btn {
        width: 100%;
        box-sizing: border-box;
      }
    }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="email-container">
      
      <!-- Header -->
      <div class="header">
        <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC Logo">
        <h1>PPA Submission Reminder</h1>
      </div>

      <!-- Body Content -->
      <div class="content">
        <p>Dear Mr. Eyob Tensaw,</p>

        <p>This is a kind reminder to submit your <strong>Performance Planning and Appraisal (PPA)</strong> form for the period <strong>January 2025 to December 2025</strong>.</p>

        <p>The extended deadline for submission is <strong>April 30, 2025</strong>. Kindly ensure that your PPA is completed and submitted before this date.</p>

        <p>You can access the PPA form by logging into the staff portal using the link below:</p>
      </div>

      <!-- Button -->
      <div class="btn-container">
        <a href="<?php echo $_ENV['PRODUCTION_URL'].'performance'; ?>" class="btn">Submit My PPA</a>
      </div>

      <!-- Footer -->
      <div class="content">
        <p>If you have already submitted your PPA, kindly ignore this reminder.</p>
        <p>Thank you for your attention and commitment.</p>

        <p>Best regards,<br>
        <strong>Human Resources</strong></p>
      </div>

      <div class="footer">
        &copy; 2025 Africa CDC. All rights reserved.
      </div>

    </div>
  </div>
</body>
</html>
