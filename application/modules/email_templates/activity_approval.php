<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title><?php echo $data->subject?></title>
    <style type="text/css">
      body {
        margin: 0;
        padding: 0;
        background-color: #f4f4f4;
        font-family: Arial, sans-serif;
      }
      .container {
        width: 100%;
        max-width: 600px;
        margin: 0 auto;
        background: #ffffff;
        padding: 20px;
      }
      .header {
        text-align: center;
        padding: 10px 0;
      }
      .header img {
        max-width: 150px;
      }
      .content {
        padding: 20px;
      }
      .footer {
        text-align: center;
        font-size: 12px;
        color: #888888;
        padding: 10px 0;
      }
      h1 {
        color: #2A2A2A;
      }
      p {
        line-height: 1.5;
      }
      .btn {
        display: inline-block;
        background-color: #28a745;
        color: #ffffff;
        padding: 10px 20px;
        text-decoration: none;
        border-radius: 5px;
      }
      .activity-list {
         margin: 20px 0;
         padding: 0;
         list-style-type: none;
      }
      .activity-list li {
         padding: 10px;
         border-bottom: 1px solid #dddddd;
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
        <h1><?php echo $subject?></h1>
        <p><?php echo $name ?>,</p>
        <p>
          We are pleased to inform you that your recent activity submissions have been reviewed and approved by your supervisor. Below is a summary of the approved activities:
        </p>
        <ul class="activity-list">
          <li><strong><?php echo $activity->name?>:</strong><?php echo $activity->descrition. ' Starting on: '.$activity->start_date. ' Ending on '.$activity->end_date ?> </li>
          
        </ul>
        <p>
          To view more details or any additional instructions regarding these activities, please click the button below.
        </p>
        <p>
          <a href="<?php echo base_url()?>/" class="btn">View Details</a>
        </p>
        <p>
          Thank you for your hard work and dedication.
        </p>
        <p>
          Best regards,<br>
          <?php echo $data->supervisor?><br>
          Supervisor, Africa CDC
        </p>
      </div>
      <!-- Footer -->
      <div class="footer">
        <p>&copy; 2025 Africa CDC. All Rights Reserved.</p>
      </div>
    </div>
  </body>
</html>
