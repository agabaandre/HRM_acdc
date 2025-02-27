<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Contract Expiration Notice</title>
    <style type="text/css">
      body {
        margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;
      }
      .container {
        width: 100%; max-width: 600px; margin: 0 auto; background: #ffffff; padding: 20px;
      }
      .header {
        text-align: center; padding: 10px 0;
      }
      .header img {
        max-width: 150px;
      }
      .content {
        padding: 20px;
      }
      .footer {
        text-align: center; font-size: 12px; color: #888888; padding: 10px 0;
      }
      h1 {
        color: #2A2A2A;
      }
      p {
        line-height: 1.5;
      }
      .btn {
        display: inline-block; background-color: #d9534f; color: #ffffff; padding: 10px 20px;
        text-decoration: none; border-radius: 5px;
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
        <h1>Contract Expiration Notice</h1>
        <p>Dear <?php echo $name ?>,</p>
        <p>
          We wish to inform you that your contract expired on <?php echo $date2 ?>. We understand that this may require clarification, and we encourage you to reach out so we can discuss your current employment status.
        </p>
        <p>
          Please contact your supervisor as soon as possible to review the details and discuss the next steps.
        </p>
        <p>
          Thank you for your attention to this matter.
        </p>
        <p>
          Sincerely,<br>
          HR Team, Africa CDC
        </p>
        <p>
          <a href="<?php echo base_url() ?>/staff" class="btn">HR Staff Portal</a>
        </p>
      </div>
      <!-- Footer -->
      <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Africa CDC. All Rights Reserved.</p>
      </div>
    </div>
  </body>
</html>
