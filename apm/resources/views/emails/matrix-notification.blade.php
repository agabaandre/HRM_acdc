<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Matrix Notification</title>
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
      max-width: 30%;
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
      <img src="https://africacdc.org/wp-content/uploads/2020/02/AfricaCDC_Logo.png" alt="Africa CDC Logo">
    </div>

    <!-- Main Content -->
    <div class="content">
      <h1>
        @if($type === 'matrix_approval')
          Resource Approval Request
        @elseif($type === 'matrix_returned')
          Resource Returned for Revision
        @else
          Resource Notification
        @endif
      </h1>

      <p>Dear <strong>{{ $recipient->fname }} {{ $recipient->lname }}</strong>,</p>

      <p>{{ $message }}</p>

      <p>Resource Details:</p>
      <ul>
        <li>Resource : #{{ $resource->id }} - {{ $resource->quarter ?? '' }} {{ $resource->year ?? '' }}</li>
        <li>Created by: {{ $resource->staff->fname }} {{ $resource->staff->lname }}</li>
        <li>Division: {{ $resource->division->name }}</li>
        <li>Status: {{ ucfirst($resource->overall_status) }}</li>
      </ul>

      <a href="{{ config('app.url') }}/matrices/{{ $resource->id }}" class="btn">View Matrix</a>

      <p style="margin-top: 20px;">
        Best regards,<br>
        <strong>Africa CDC Team</strong>
      </p>
    </div>

    <!-- Footer -->
    <div class="footer">
      &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
      <br>
      This is an automated message. Please do not reply to this email.
    </div>
  </div>
</body>
</html> 