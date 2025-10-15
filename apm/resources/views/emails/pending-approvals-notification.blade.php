<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pending Approvals Notification - Africa CDC</title>
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
        .summary-cards {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: #119A48;
            color: white;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            flex: 1;
        }
        .summary-card h3 {
            margin: 0;
            font-size: 24px;
        }
        .summary-card p {
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        .pending-items {
            margin-top: 20px;
        }
        .category-section {
            margin-bottom: 30px;
        }
        .category-title {
            background-color: #e9ecef;
            padding: 10px 15px;
            font-weight: bold;
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
        }
        .item {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 3px solid #ffc107;
        }
        .item-title {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .item-details {
            font-size: 14px;
            color: #666;
        }
        .item-details span {
            margin-right: 15px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
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
            <h2>Pending Approvals Notification</h2>
            <p>Hello {{ $approverTitle }} {{ $approverName }},</p>
            <p>You have pending approvals that require your attention.</p>
        </div>

    <div class="summary-cards">
        <div class="summary-card">
            <h3>{{ $summaryStats['total_pending'] }}</h3>
            <p>Total Pending</p>
        </div>
        @foreach($summaryStats['by_category'] as $category => $count)
        <div class="summary-card">
            <h3>{{ $count }}</h3>
            <p>{{ $category }}</p>
        </div>
        @endforeach
    </div>

    <div class="pending-items">
        @foreach($pendingApprovals as $category => $items)
        <div class="category-section">
            <div class="category-title">{{ $category }} ({{ count($items) }})</div>
            
            @foreach($items as $item)
            <div class="item">
                <div class="item-title">{{ $item['title'] }}</div>
                <div class="item-details">
                    <span><strong>Division:</strong> {{ $item['division'] }}</span>
                    <span><strong>Submitted by:</strong> {{ $item['submitted_by'] }}</span>
                    <span><strong>Date Received:</strong> {{ $item['date_received'] ? \Carbon\Carbon::parse($item['date_received'])->format('M d, Y H:i') : 'N/A' }}</span>
                </div>
                <div class="item-details">
                    <span class="badge badge-warning">{{ $item['workflow_role'] }}</span>
                    <span class="badge badge-info">Level {{ $item['approval_level'] }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>

        <!-- Footer -->
        <div class="footer">
            <p>Please review and take action on these pending approvals.</p>
            <a href="{{ $baseUrl }}/pending-approvals" class="btn">View All Pending Approvals</a>
            <p><small>This is an automated notification. Please do not reply to this email.</small></p>
            <p style="font-style: italic; color: #119A48; font-weight: 500; margin: 15px 0;">
                <strong>Prompt approvals enhance staff and organisational efficiency.</strong>
            </p>
            <p>&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
