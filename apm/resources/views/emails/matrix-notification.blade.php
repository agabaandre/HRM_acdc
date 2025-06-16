<!DOCTYPE html>
<html>
<head>
    <title>Matrix Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #6c757d;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $type === 'matrix_approval' ? 'Matrix Approval Required' : 'Matrix Notification' }}</h2>
        </div>
        
        <div class="content">
            <p>Dear {{ $recipient->fname }} {{ $recipient->lname }},</p>
            
            <p>{{ $message }}</p>
            
            <p>Matrix Details:</p>
            <ul>
                <li>Matrix ID: #{{ $matrix->id }}</li>
                <li>Created by: {{ $matrix->staff->fname }} {{ $matrix->staff->lname }}</li>
                <li>Division: {{ $matrix->division->name }}</li>
                <li>Status: {{ ucfirst($matrix->overall_status) }}</li>
            </ul>

            <a href="{{ config('app.url') }}/matrices/{{ $matrix->id }}" class="button">
                View Matrix
            </a>
        </div>

        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html> 