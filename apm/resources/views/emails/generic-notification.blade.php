<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>APM System Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #119A48;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 0 0 8px 8px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        .notification-info {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Africa CDC APM System</h1>
        <p>System Notification</p>
    </div>
    
    <div class="content">
        <h2>Hello {{ $staff->fname }} {{ $staff->lname }},</h2>
        
        <div class="notification-info">
            <h3>Notification Details:</h3>
            <p><strong>Message:</strong> {{ $message }}</p>
            <p><strong>Type:</strong> {{ ucfirst(str_replace('_', ' ', $type)) }}</p>
            <p><strong>Time:</strong> {{ $notification->created_at->format('M d, Y H:i:s T') }}</p>
        </div>
        
        <p>This is an automated notification from the Africa CDC APM system.</p>
        
        <p>Please log into the system to view more details or take any required actions.</p>
    </div>
    
    <div class="footer">
        <p>This is an automated notification from the Africa CDC APM system.</p>
        <p>Please ignore if you received this in error.</p>
        <p style="font-style: italic; color: #119A48; font-weight: 500; margin: 15px 0;">
            <strong>Prompt approvals enhance staff and organisational efficiency.</strong>
        </p>
    </div>
</body>
</html>
