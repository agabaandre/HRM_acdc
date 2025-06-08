<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>PPA Submission Confirmation</title>
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
      background-color: #005844; /* optional darker shade */
      color: #ffffff !important; /* ensure text remains visible */
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
        <h1>PPA Submission Confirmed</h1>
        <p>Dear <?= $name ?>,</p>
        <p>
          This is to confirm that you have successfully submitted your PPA form for the performance period:
          <strong><?= str_replace('-',' ',$period); ?></strong>.
        </p>
        <p>
          Your PPA has been forwarded to your supervisor for review. You will be notified once any actions are taken on your submission.
        </p>

        <a href="<?= site_url('performance/view_ppa/' . $entry_id); ?>/<?=$staff_id?>" class="btn" style="color:#fff !important;">View Submitted PPA</a>


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
