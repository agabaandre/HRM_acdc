<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New {{ $itemType }} Pending Approval - Africa CDC</title>
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
        }
        .item-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .item-title {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 15px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: bold;
            width: 120px;
            color: #666;
        }
        .detail-value {
            flex: 1;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 3px;
            margin-right: 5px;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .badge-info {
            background-color: #17a2b8;
            color: #fff;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #119A48;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC Logo">
            <h2>New {{ $itemType }} Pending Approval</h2>
            <p>Hello  {{ $approverName }},</p>
            <p>A new {{ $itemType }} requires your approval.</p>
        </div>

    <div class="item-details">
        <div class="item-title">{{ $item['title'] ?? $itemType }}</div>
        
        <div class="detail-row">
            <div class="detail-label">Type:</div>
            <div class="detail-value">{{ $itemType }}</div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Division:</div>
            <div class="detail-value">{{ $item['division'] ?? 'N/A' }}</div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Submitted by:</div>
            <div class="detail-value">{{ $item['submitted_by'] ?? 'N/A' }}</div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Date Received:</div>
            <div class="detail-value">{{ $item['date_received'] ? \Carbon\Carbon::parse($item['date_received'])->format('M d, Y H:i') : 'N/A' }}</div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Workflow Role:</div>
            <div class="detail-value">
                <span class="badge badge-warning">{{ $item['workflow_role'] ?? 'N/A' }}</span>
            </div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Approval Level:</div>
            <div class="detail-value">
                <span class="badge badge-info">Level {{ $item['approval_level'] ?? 'N/A' }}</span>
            </div>
        </div>
    </div>

        <!-- Footer -->
        <div class="footer">
            <p>Please review and take action on this pending approval.</p>
            <a href="{{ $baseUrl }}/pending-approvals" class="btn">View Pending Approvals</a>
            <p><small>This is an automated notification. Please do not reply to this email.</small></p>
            <p style="font-style: italic; color: #119A48; font-weight: 500; margin: 15px 0;">
                <strong>Prompt approvals enhance staff and organisational efficiency.</strong>
            </p>
            <p>&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
