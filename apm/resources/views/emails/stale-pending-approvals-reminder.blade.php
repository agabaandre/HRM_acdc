<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending approval reminder - Africa CDC</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 650px; margin: 0 auto; background: #ffffff; border: 1px solid #dddddd; padding: 28px; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h1 { margin: 0; font-size: 20px; color: #119A48; }
        .content { line-height: 1.65; }
        .lead { background: #fff8e6; border-left: 4px solid #ffc107; padding: 14px 16px; margin: 18px 0; border-radius: 4px; }
        .item { background: #f8f9fa; padding: 12px 14px; margin-bottom: 10px; border-radius: 5px; border-left: 3px solid #ffc107; }
        .item-title { font-weight: bold; color: #007bff; margin-bottom: 6px; }
        .item-meta { font-size: 13px; color: #555; }
        .btn { display: inline-block; margin-top: 8px; padding: 8px 14px; background: #119A48; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-size: 13px; }
        .footer { margin-top: 24px; font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 16px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Gentle reminder — pending approvals</h1>
    </div>
    <div class="content">
        <p>Dear {{ $recipient->fname }} {{ $recipient->lname }},</p>

        <div class="lead">
            <p style="margin: 0 0 10px 0;">
                The following {{ $staleCount ?? count($stalePendingItems ?? []) }} item(s) reached your approval level at least
                <strong>{{ $approvalWarningDays ?? 7 }}</strong> day(s) ago (based on when they were received at your current step).
            </p>
            <p style="margin: 0;">
                When you are ready to clear your queue, you may <strong>approve</strong> to send the document to the next person in the approval trail,
                or <strong>return</strong> it (for example for archiving or corrections), which also stops further reminders for that item once it is no longer pending at your level.
            </p>
        </div>

        <p style="margin-top: 18px;"><strong>Items:</strong></p>
        @foreach($stalePendingItems ?? [] as $item)
            <div class="item">
                <div class="item-title">{{ $item['title'] ?? 'Document' }}</div>
                <div class="item-meta">
                    <strong>Type:</strong> {{ $item['category'] ?? ($item['type'] ?? 'N/A') }} &nbsp;|&nbsp;
                    <strong>Division:</strong> {{ $item['division'] ?? 'N/A' }} &nbsp;|&nbsp;
                    <strong>Received at your level:</strong>
                    @if(!empty($item['date_received']))
                        {{ \Carbon\Carbon::parse($item['date_received'])->format('M d, Y') }}
                    @else
                        N/A
                    @endif
                </div>
                @if(!empty($item['view_url']))
                    <a href="{{ $item['view_url'] }}" class="btn">Open in APM</a>
                @endif
            </div>
        @endforeach

        <p style="margin-top: 22px;">
            <a href="{{ $pendingApprovalsUrl }}" class="btn">View all pending approvals</a>
        </p>
    </div>
    <div class="footer">
        <p>This is an automated message from the Africa CDC Approvals Management System. The reminder threshold is configured under <strong>System settings</strong> (approval warning days).</p>
        <p>&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
    </div>
</div>
</body>
</html>
