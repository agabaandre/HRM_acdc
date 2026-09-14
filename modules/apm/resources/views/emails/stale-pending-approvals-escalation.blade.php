<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stale approval escalation - Africa CDC</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 650px; margin: 0 auto; background: #ffffff; border: 1px solid #dddddd; padding: 28px; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h1 { margin: 0; font-size: 20px; color: #119A48; }
        .content { line-height: 1.65; }
        .lead { background: #fdecea; border-left: 4px solid #dc3545; padding: 14px 16px; margin: 18px 0; border-radius: 4px; }
        .item { background: #f8f9fa; padding: 12px 14px; margin-bottom: 10px; border-radius: 5px; border-left: 3px solid #dc3545; }
        .item-title { font-weight: bold; color: #007bff; margin-bottom: 6px; }
        .item-meta { font-size: 13px; color: #555; }
        .btn { display: inline-block; margin-top: 8px; padding: 8px 14px; background: #119A48; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-size: 13px; }
        .footer { margin-top: 24px; font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 16px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Stale approval escalation</h1>
    </div>
    <div class="content">
        <p>Dear {{ $recipient->fname }} {{ $recipient->lname }},</p>

        <div class="lead">
            <p style="margin: 0 0 10px 0;">
                {{ $staleCount ?? count($stalePendingItems ?? []) }} document(s) have remained at an approval step for more than
                <strong>{{ $approvalWarningDays ?? 7 }}</strong> day(s). You are receiving this escalation because you are the
                document creator, Head of Division, a senior approver in the chain, or a configured oversight approver (general workflow).
            </p>
            <p style="margin: 0;">
                The current approver has already been reminded. Please follow up so the item can be approved or returned.
            </p>
        </div>

        <p style="margin-top: 18px;"><strong>Items:</strong></p>
        @foreach($stalePendingItems ?? [] as $item)
            <div class="item">
                <div class="item-title">{{ $item['title'] ?? 'Document' }}</div>
                <div class="item-meta">
                    <strong>Type:</strong> {{ $item['category'] ?? ($item['type'] ?? 'N/A') }} &nbsp;|&nbsp;
                    <strong>Division:</strong> {{ $item['division'] ?? 'N/A' }} &nbsp;|&nbsp;
                    <strong>Stuck at level:</strong> {{ $item['stuck_approval_level'] ?? ($item['approval_level'] ?? 'N/A') }}
                    @if(!empty($item['workflow_role']))
                        ({{ $item['workflow_role'] }})
                    @endif
                    &nbsp;|&nbsp;
                    <strong>At this level since:</strong>
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
            <a href="{{ $pendingApprovalsUrl }}" class="btn">View pending approvals</a>
        </p>
    </div>
    <div class="footer">
        <p>
            Threshold: <strong>approval_warning_days</strong> in System settings.
            General workflow (workflow 1) oversight levels: <strong>general_workflow_stale_escalation_orders</strong>.
        </p>
        <p>&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
    </div>
</div>
</body>
</html>
