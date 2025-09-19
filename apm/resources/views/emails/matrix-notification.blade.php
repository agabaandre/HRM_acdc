<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrix Notification - Africa CDC</title>
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
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            color: #333333;
        }
        .content {
            padding: 20px 0;
            line-height: 1.6;
        }
        h2 {
            color: #119A48;
            font-size: 18px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        p {
            line-height: 1.6;
            color: #444444;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .details {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #119A48;
        }
        .details h2 {
            margin-top: 0;
            font-size: 18px;
            color: #119A48;
            margin-bottom: 15px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-top: 15px;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #555555;
            min-width: 120px;
        }
        .detail-value {
            color: #333333;
            text-align: right;
            flex: 1;
        }
        .status-pending {
            color: #856404;
            background-color: #fff3cd;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-approved {
            color: #155724;
            background-color: #d4edda;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-returned {
            color: #721c24;
            background-color: #f8d7da;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #119A48;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
            max-width: 100%;
            word-wrap: break-word;
            box-sizing: border-box;
            text-align: center;
        }
        .btn:hover {
            background-color: #0d7a3a;
            color: #ffffff !important;
            text-decoration: none;
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
        @media only screen and (max-width: 600px) {
            .container {
                padding: 20px;
            }
            .header h1 {
                font-size: 20px;
            }
            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .detail-value {
                text-align: left;
            }
            .btn {
                width: 100%;
                text-align: center;
                display: block;
                margin: 15px auto 0 auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC Logo">
            <h1>
                @if($type == 'approval')
                    {{ $resource_type }} Approval Request
                @elseif($type == 'returned')
                    {{ $resource_type }} Returned for Revision
                @else
                    {{ $resource_type }} Notification
                @endif
            </h1>
        </div>

        <!-- Main Content -->
        <div class="content">
            <p>Dear <strong>{{ $recipient->title }} {{ $recipient->fname }} {{ $recipient->lname }}</strong>,</p>

            <p>{{ $message }}</p>

            <div class="details">
                <h2>Approval Details</h2>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Document Number:</span>
                        <span class="detail-value">#{{ isset($resource->document_number) ? $resource->document_number : 'QM/'.$resource->year.'/'.$resource->quarter }} </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Created by:</span>
                        <span class="detail-value">{{ $resource->staff->fname }} {{ $resource->staff->lname }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Division:</span>
                        <span class="detail-value">{{ $resource->division ? ($resource->division->name ?? $resource->division->division_name ?? 'N/A') : 'N/A' }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value status-{{ strtolower($resource->overall_status) }}">{{ ucfirst($resource->overall_status) }}</span>
                    </div>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="{{ $resource->resource_url }}" class="btn">View Details</a>
            </div>


        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                This is an automated notification from the Africa CDC Approvals Management System.<br>
                Please log in to the system to take action on pending items.
            </p>
            <p>&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
        </div>
    </div>
</body>
</html> 