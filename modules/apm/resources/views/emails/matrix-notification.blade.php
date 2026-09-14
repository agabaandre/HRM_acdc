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
            <img src="{{ config('branding.mail_logo_url') }}" alt="Africa CDC Logo">
            <h1>
                @if($type == 'approval')
                    {{ $resource_type }} Approval Request
                @elseif($type == 'returned')
                    {{ $resource_type }} Returned for Revision
                @elseif($type == 'created')
                    New Quarterly Travel Matrix
                @elseif($type == 'approved' || $type == 'other_memo_approval')
                    {{ $resource_label ?? $resource_type }} — awaiting your approval
                @else
                    {{ $resource_type }} Notification
                @endif
            </h1>
            @if($type == 'created')
                <p style="margin-top: 8px; font-size: 15px; color: #555555;">
                    {{ $division_name ?? ($resource->division->division_name ?? 'Division') }} &middot; {{ $matrix_period ?? ($resource->quarter . ' ' . $resource->year) }}
                </p>
            @endif
        </div>

        <!-- Main Content -->
        <div class="content">
            <p>Dear <strong>{{ $recipient->title }} {{ $recipient->fname }} {{ $recipient->lname }}</strong>,</p>

            @php
                $rawResourceUrl = $matrix_url ?? ($resource->resource_url ?? route('matrices.show', $resource->id, false));
                $openMatrixUrl = str_starts_with((string) $rawResourceUrl, 'http')
                    ? $rawResourceUrl
                    : url($rawResourceUrl);
            @endphp

            @if($type == 'created')
                @php
                    $matrixTitle = $matrix_display_title ?? ($resource->listDisplayTitle() ?? ('Matrix #' . $resource->id));
                    $divisionLabel = $division_name ?? ($resource->division->division_name ?? 'N/A');
                    $creatorName = $created_by_name ?? (($resource->staff->fname ?? '') . ' ' . ($resource->staff->lname ?? ''));
                    $focalName = $focal_person_name ?? null;
                    $kraItems = $key_result_areas ?? [];
                    if ($kraItems === [] && !empty($resource->key_result_area)) {
                        $rawKra = $resource->key_result_area;
                        if (is_string($rawKra)) {
                            $rawKra = json_decode($rawKra, true);
                        }
                        if (is_array($rawKra)) {
                            foreach ($rawKra as $item) {
                                $desc = is_array($item) ? ($item['description'] ?? '') : (string) $item;
                                if (trim($desc) !== '') {
                                    $kraItems[] = trim($desc);
                                }
                            }
                        }
                    }
                @endphp
                <p>
                    A new <strong>Quarterly Travel Matrix</strong> has been opened for
                    <strong>{{ $divisionLabel }}</strong> ({{ $matrix_period ?? ($resource->quarter . ' ' . $resource->year) }}).
                    You are receiving this email because you are a member of the division.
                </p>

                <div class="details" style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 24px; border-left: 4px solid #119A48;">
                    <h2 style="margin-top: 0;">Matrix Details</h2>
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                        <span class="detail-label" style="font-weight: 600; color: #555555; min-width: 140px;">Matrix:</span>
                        <span class="detail-value" style="color: #333333; flex: 1;">{{ $matrixTitle }}</span>
                    </div>
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                        <span class="detail-label" style="font-weight: 600; color: #555555; min-width: 140px;">Division:</span>
                        <span class="detail-value" style="color: #333333; flex: 1;">{{ $divisionLabel }}</span>
                    </div>
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                        <span class="detail-label" style="font-weight: 600; color: #555555; min-width: 140px;">Period:</span>
                        <span class="detail-value" style="color: #333333; flex: 1;">{{ $matrix_period ?? ($resource->quarter . ' ' . $resource->year) }}</span>
                    </div>
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                        <span class="detail-label" style="font-weight: 600; color: #555555; min-width: 140px;">Created by:</span>
                        <span class="detail-value" style="color: #333333; flex: 1;">{{ trim($creatorName) !== '' ? trim($creatorName) : 'N/A' }}</span>
                    </div>
                    @if(!empty($focalName))
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                        <span class="detail-label" style="font-weight: 600; color: #555555; min-width: 140px;">Focal person:</span>
                        <span class="detail-value" style="color: #333333; flex: 1;">{{ $focalName }}</span>
                    </div>
                    @endif
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                        <span class="detail-label" style="font-weight: 600; color: #555555; min-width: 140px;">Status:</span>
                        <span class="detail-value" style="color: #333333; flex: 1;">
                            <span class="status-pending">{{ ucfirst($resource->overall_status ?? 'draft') }}</span>
                        </span>
                    </div>
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: none;">
                        <span class="detail-label" style="font-weight: 600; color: #555555; min-width: 140px;">Open matrix:</span>
                        <span class="detail-value" style="color: #333333; flex: 1;">
                            <a href="{{ $openMatrixUrl }}" style="color: #119A48; font-weight: 600; word-break: break-all;">{{ $openMatrixUrl }}</a>
                        </span>
                    </div>
                </div>

                @if(!empty($kraItems))
                <div class="details" style="background-color: #ffffff; border-radius: 6px; padding: 20px; margin-bottom: 24px; border: 1px solid #e9ecef;">
                    <h2 style="margin-top: 0;">Key Result Areas</h2>
                    <ul style="margin: 0; padding-left: 20px; color: #444444;">
                        @foreach($kraItems as $kraDescription)
                            <li style="margin-bottom: 8px;">{{ $kraDescription }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div style="background-color: #e8f5e9; border-radius: 6px; padding: 18px 20px; margin-bottom: 24px; border-left: 4px solid #119A48;">
                    <p style="margin: 0 0 8px 0; color: #155724; font-weight: 700; font-size: 15px;">
                        You are invited to contribute
                    </p>
                    <p style="margin: 0 0 12px 0; color: #2e7d32; font-size: 14px; line-height: 1.6;">
                        Please log in to APM, open this matrix, and add your planned activities for the quarter.
                        Coordinate with your division focal person if you need guidance before submission.
                    </p>
                    <p style="margin: 0; font-size: 14px; line-height: 1.6;">
                        <a href="{{ $openMatrixUrl }}" style="color: #119A48; font-weight: 700; text-decoration: underline;">Click here to open the matrix in APM</a>
                    </p>
                </div>
            @elseif($type == 'approved' || $type == 'other_memo_approval')
                @php
                    $fwdTitle = $memo_title ?? ($resource->title ?? ($resource->activity_title ?? null));
                    $fwdDoc = $document_number_display ?? ($resource->document_number ?? null);
                    $fwdDivision = $division_name ?? ($resource->division ? ($resource->division->division_name ?? $resource->division->name ?? 'N/A') : 'N/A');
                    $fwdApprovedBy = $approved_by_name ?? 'Previous approver';
                @endphp
                <!-- Forwarded to next approver after a step was approved -->
                <div class="details" style="background-color: #d4edda; border-radius: 6px; padding: 20px; margin-bottom: 30px; border-left: 4px solid #28a745;">
                    <h2 style="color: #155724; margin-top: 0; margin-bottom: 15px;">
                        ✓ Approval Confirmed
                    </h2>
                    <p style="color: #155724; font-size: 16px; margin-bottom: 15px;">{{ $message }}</p>
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #c3e6cb;">
                        <span class="detail-label" style="font-weight: 600; color: #155724; min-width: 140px;">Title:</span>
                        <span class="detail-value" style="color: #155724; flex: 1;">{{ $fwdTitle ?: ($resource_label ?? $resource_type) }}</span>
                    </div>
                    @if(!empty($fwdDoc))
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #c3e6cb;">
                        <span class="detail-label" style="font-weight: 600; color: #155724; min-width: 140px;">Document number:</span>
                        <span class="detail-value" style="color: #155724; flex: 1;">{{ $fwdDoc }}</span>
                    </div>
                    @endif
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #c3e6cb;">
                        <span class="detail-label" style="font-weight: 600; color: #155724; min-width: 140px;">Division:</span>
                        <span class="detail-value" style="color: #155724; flex: 1;">{{ $fwdDivision }}</span>
                    </div>
                    @if($resource->description ?? false)
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: 1px solid #c3e6cb;">
                        <span class="detail-label" style="font-weight: 600; color: #155724; min-width: 140px;">Description:</span>
                        <span class="detail-value" style="color: #155724; flex: 1;">{{ $resource->description }}</span>
                    </div>
                    @endif
                    <div class="detail-item" style="display: flex; padding: 12px 0; border-bottom: none;">
                        <span class="detail-label" style="font-weight: 600; color: #155724; min-width: 140px;">Status:</span>
                        <span class="detail-value" style="color: #155724; flex: 1;">
                            <span style="background-color: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">Approved by {{ $fwdApprovedBy }}</span>
                        </span>
                    </div>
                </div>
            @else
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
            @endif

            @if($type == 'created')
            <p style="margin-top: 20px;">We look forward to your contributions to the division matrix this quarter.</p>
            @elseif($type != 'approved')
            <p style="margin-top: 20px;">Please review and take appropriate action.</p>
            @endif

            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ $openMatrixUrl }}" class="btn">
                    @if($type == 'created')
                        Open Matrix &amp; Contribute
                    @else
                        View Details
                    @endif
                </a>
            </div>
            @if($type == 'created')
            <p style="margin-top: 16px; margin-bottom: 0; text-align: center; font-size: 13px; color: #666666; word-break: break-all;">
                Or copy this link: <a href="{{ $openMatrixUrl }}" style="color: #119A48;">{{ $openMatrixUrl }}</a>
            </p>
            @endif

            <p style="margin-top: 30px; margin-bottom: 0;">
                Best regards,<br>
                <strong>Africa CDC APM System</strong>
            </p>

        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                This is an automated notification from the Africa CDC Approvals Management System.<br>
                Please log in to the system to take action on pending items.
            </p>
            <p style="font-style: italic; color: #119A48; font-weight: 500; margin: 15px 0;">
                <strong>Prompt approvals enhance staff and organisational efficiency.</strong>
            </p>
            <p>&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
        </div>
    </div>
</body>
</html> 