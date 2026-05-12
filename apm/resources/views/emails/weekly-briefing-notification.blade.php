<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $headerTitle }} — Africa CDC</title>
    <style>
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
            margin-bottom: 20px;
        }
        .header img {
            max-height: 70px;
            max-width: 200px;
            width: auto;
            height: auto;
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
        .content p {
            line-height: 1.6;
            color: #444444;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .content a {
            color: #119A48;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #119A48;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 10px;
        }
        .closing {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid #eeeeee;
            color: #444444;
            font-size: 14px;
        }
        .footer {
            font-size: 12px;
            color: #888888;
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid #eeeeee;
        }
        .footer p {
            margin: 0 0 8px 0;
        }
        @media only screen and (max-width: 600px) {
            .container { padding: 20px; }
            .header h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC">
            <h1>{{ $headerTitle }}</h1>
        </div>

        <div class="content">
            @php
                $fullName = trim(implode(' ', array_filter([(string) ($recipient->title ?? ''), (string) ($recipient->fname ?? ''), (string) ($recipient->lname ?? '')])));
            @endphp
            @if ($recipient && $fullName !== '')
                <p>Dear <strong>{{ $fullName }}</strong>,</p>
            @else
                <p>Dear Colleague,</p>
            @endif

            {!! $bodyHtml !!}

            <div class="closing">
                <p>Yours sincerely,<br>
                <strong>Africa Centres for Disease Control and Prevention (Africa CDC)</strong><br>
                <span style="font-size:13px;color:#555;">Weekly brief · Approvals Management System (APM)</span></p>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated notification from the Africa CDC Approvals Management System.</p>
            <p style="font-style: italic; color: #119A48; font-weight: 500; margin: 15px 0;">
                <strong>Prompt submissions support organisational reporting and decision-making.</strong>
            </p>
            <p>&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
