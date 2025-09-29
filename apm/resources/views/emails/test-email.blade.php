<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>APM System Test Email</title>
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
        .test-info {
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
        <p>Test Email Notification</p>
    </div>
    
    <div class="content">
        <h2>Email Configuration Test</h2>
        <p>This is a test email to verify that the APM system's email configuration is working correctly.</p>
        
        <div class="test-info">
            <h3>Test Information:</h3>
            <ul>
                <li><strong>Timestamp:</strong> {{ $timestamp->format('M d, Y H:i:s T') }}</li>
                <li><strong>System:</strong> Africa CDC APM</li>
                <li><strong>Environment:</strong> {{ app()->environment() }}</li>
                <li><strong>Mail Driver:</strong> {{ config('mail.default') }}</li>
                <li><strong>Mail Host:</strong> {{ config('mail.mailers.smtp.host') }}</li>
                <li><strong>Mail Port:</strong> {{ config('mail.mailers.smtp.port') }}</li>
            </ul>
        </div>
        
        <p>If you received this email, it means the email system is working correctly and notifications should be delivered properly.</p>
        
        <h3>What this means:</h3>
        <ul>
            <li>✅ SMTP configuration is correct</li>
            <li>✅ Email authentication is working</li>
            <li>✅ Pending approval notifications will be sent</li>
            <li>✅ Daily reminder emails will be delivered</li>
        </ul>
    </div>
    
    <div class="footer">
        <p>This is an automated test email from the Africa CDC APM system.</p>
        <p>Please ignore if you received this in error.</p>
    </div>
</body>
</html>
