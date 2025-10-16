<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returned Memos Notification - Africa CDC</title>
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
            color: #dc3545;
        }
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            color: #333333;
        }
        .content {
            padding: 20px 0;
            line-height: 1.6;
        }
        .summary-stats {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #dc3545;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
            display: block;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .returned-items {
            margin-top: 30px;
        }
        .category-section {
            margin-bottom: 25px;
        }
        .category-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }
        .item-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .item {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            overflow: hidden;
            word-wrap: break-word;
        }
        .item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        .item-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .item-meta {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        .item-actions {
            margin-top: 10px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #dc3545;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 15px;
            max-width: 100%;
            word-wrap: break-word;
            box-sizing: border-box;
        }
        .btn:hover {
            background-color: #c82333;
            color: #ffffff !important;
            text-decoration: none;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 12px;
            margin-right: 8px;
        }
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .footer {
            font-size: 12px;
            color: #888888;
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #eeeeee;
        }
        .footer p {
            margin: 0;
        }
        .no-returned {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .no-returned i {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 15px;
        }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .action-button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #dc3545;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 0 10px;
            transition: background-color 0.3s ease;
        }
        .action-button:hover {
            background-color: #c82333;
            color: #ffffff !important;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .container {
                padding: 20px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .header h1 {
                font-size: 20px;
            }
            .btn, .action-button {
                width: 100%;
                text-align: center;
                display: block;
                margin: 15px auto 0 auto;
            }
            .item {
                padding: 12px;
            }
            .item-actions {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC Logo">
            <h1>Returned Memos Notification</h1>
            @php
                $currentHour = (int) date('H');
                $greeting = $currentHour < 12 ? 'Good morning' : 'Good afternoon';
            @endphp
            <p>{{ $greeting }}, {{ $staffName }}! You have returned memos that require your attention.</p>
        </div>

        <div class="content">
            @if($summaryStats['total_returned'] > 0)
                <div class="summary-stats">
                    <h3 style="margin-top: 0;">Returned Memos Summary Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number">{{ $summaryStats['total_returned'] }}</span>
                            <span class="stat-label">Total Returned</span>
                        </div>
                        @foreach($summaryStats['by_category'] as $category => $count)
                            <div class="stat-item">
                                <span class="stat-number">{{ $count }}</span>
                                <span class="stat-label">{{ $category }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="returned-items">
                    <h3>Returned Items Requiring Your Attention</h3>
                    
                    @foreach($returnedItems as $category => $items)
                        @if(count($items) > 0)
                            <div class="category-section">
                                <h4 class="category-title">{{ $category }} ({{ count($items) }} items)</h4>
                                <ul class="item-list">
                                    @foreach($items as $item)
                                        <li class="item">
                                            <div class="item-title">{{ $item['title'] }}</div>
                                            <div class="item-meta">
                                                <strong>Document Number:</strong> {{ $item['document_number'] ?? 'N/A' }} | 
                                                <strong>Division:</strong> {{ $item['division'] }} | 
                                                <strong>Submitted by:</strong> {!! $item['submitted_by'] !!} | 
                                                <strong>Date:</strong> {{ \Carbon\Carbon::parse($item['date_received'])->format('M d, Y H:i') }}
                                            </div>
                                            <div class="item-meta">
                                                <span class="badge badge-danger">{{ $item['workflow_role'] }}</span>
                                                <span class="badge badge-warning">Level {{ $item['approval_level'] }}</span>
                                                @if($item['status'])
                                                    <span class="badge badge-info">{{ ucfirst($item['status']) }}</span>
                                                @endif
                                            </div>
                                            <div class="item-actions">
                                                <a href="{{ $item['view_url'] }}" class="btn">View Details</a>
                                                @if($item['edit_url'])
                                                    <a href="{{ $item['edit_url'] }}" class="btn btn-secondary">Edit</a>
                                                @endif
                                                @if($item['delete_url'])
                                                    <a href="{{ $item['delete_url'] }}" class="btn btn-secondary" 
                                                       onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="action-buttons">
                    <a href="{{ $returnedMemosUrl }}" class="action-button">
                        🔗 View All Returned Memos
                    </a>
                </div>
            @else
                <div class="no-returned">
                    <i>✅</i>
                    <h3>No Returned Memos!</h3>
                    <p>You have no returned memos at this time. Great job keeping your submissions up to date!</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                This is an automated notification from the Africa CDC Approvals Management System.<br>
                Please log in to the system to review and take action on returned items.
            </p>
            <p style="font-style: italic; color: #dc3545; font-weight: 500; margin: 15px 0;">
                <strong>Please address returned memos promptly to maintain workflow efficiency.</strong>
            </p>
            <p>&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
        </div>
    </div>
</body>
</html>