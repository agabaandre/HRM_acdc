<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Endterm Review - Second Supervisor Approval Required</title>
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
        font-size: 20px;
        margin-bottom: 20px;
      }
      p {
        line-height: 1.6;
        color: #444444;
        font-size: 14px;
      }
      .info-box {
        background-color: #e7f3ff;
        border-left: 4px solid #007b5e;
        padding: 15px;
        margin: 20px 0;
      }
      .info-box p {
        margin: 5px 0;
      }
      .info-box ul {
        margin: 10px 0;
        padding-left: 20px;
      }
      .info-box li {
        margin: 5px 0;
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
        background-color: #005844;
        color: #ffffff !important;
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
        <h1>Action Required: Endterm Review - Second Supervisor Approval</h1>

        <p>Dear <strong><?= $second_supervisor_name ?></strong>,</p>

        <p>
          The Endterm Review for <strong><?= $name ?></strong> for the performance period 
          <strong><?= str_replace('-', ' ', $period); ?></strong> has been approved by the First Supervisor, 
          <strong><?= $first_supervisor_name ?></strong>, and the staff member has provided their consent.
        </p>

        <div class="info-box">
          <p><strong>Action Required:</strong></p>
          <p>Please log in to the system to review and approve the endterm review. You will need to:</p>
          <ul>
            <li>Review the endterm evaluation completed by the first supervisor</li>
            <li>Indicate whether you agree or disagree with the evaluation</li>
            <li>Provide your approval</li>
          </ul>
        </div>

        <p>
          Your approval is required to complete the endterm review process.
        </p>

        <a href="<?= site_url('performance/endterm/endterm_review/' . $entry_id . '/' . $staff_id); ?>" class="btn">Review and Approve Endterm</a>

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

