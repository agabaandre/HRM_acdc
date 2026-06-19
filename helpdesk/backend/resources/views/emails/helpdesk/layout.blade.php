@php
    use App\Support\HelpdeskMailBranding;

    $brandName = HelpdeskMailBranding::brandName();
    $logoUrl = HelpdeskMailBranding::logoUrl();
    $primary = HelpdeskMailBranding::primaryColor();
    $primaryDark = HelpdeskMailBranding::primaryDarkColor();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', $brandName) — {{ $brandName }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333333;
            background-color: #f0f4f2;
            margin: 0;
            padding: 24px 12px;
        }
        .outer {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
        }
        .card {
            background-color: #ffffff;
            border: 1px solid #dde5e0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(12, 26, 18, 0.08);
        }
        .brand-bar {
            height: 4px;
            background: linear-gradient(90deg, {{ $primary }} 0%, {{ $primaryDark }} 100%);
        }
        .header {
            text-align: center;
            padding: 28px 32px 18px;
            background: linear-gradient(180deg, #f7fbf8 0%, #ffffff 100%);
            border-bottom: 1px solid #e8efea;
        }
        .header img {
            max-height: 72px;
            max-width: 220px;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto 14px;
        }
        .brand-name {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: {{ $primary }};
        }
        .headline {
            margin: 8px 0 0;
            font-size: 22px;
            line-height: 1.3;
            font-weight: 700;
            color: #1e293b;
        }
        .subheadline {
            margin: 8px 0 0;
            font-size: 14px;
            color: #64748b;
        }
        .content {
            padding: 28px 32px 8px;
        }
        .content p {
            margin: 0 0 14px;
            color: #334155;
        }
        .details {
            background-color: #f8faf9;
            border: 1px solid #e2ebe6;
            border-left: 4px solid {{ $primary }};
            border-radius: 8px;
            padding: 18px 20px;
            margin: 18px 0 22px;
        }
        .details-title {
            margin: 0 0 12px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: {{ $primary }};
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 8px 0;
            border-bottom: 1px solid #e8efea;
        }
        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .detail-label {
            min-width: 108px;
            font-weight: 600;
            color: #64748b;
        }
        .detail-value {
            flex: 1;
            text-align: right;
            color: #1e293b;
            word-break: break-word;
        }
        .detail-value a {
            color: {{ $primary }};
            text-decoration: none;
        }
        .note-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 14px 16px;
            margin: 0 0 18px;
            color: #78350f;
            font-size: 13px;
        }
        .comment-box {
            background: #ffffff;
            border: 1px solid #dbe5df;
            border-radius: 8px;
            padding: 14px 16px;
            margin-top: 8px;
            color: #1e293b;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .resolution-box {
            background: #ffffff;
            border: 1px solid #dbe5df;
            border-radius: 8px;
            padding: 14px 16px;
            margin-top: 8px;
            color: #1e293b;
            font-size: 14px;
            line-height: 1.55;
        }
        .actions {
            text-align: center;
            padding: 4px 32px 28px;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            background-color: {{ $primary }};
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(17, 154, 72, 0.24);
        }
        .btn:hover {
            background-color: {{ $primaryDark }};
            color: #ffffff !important;
        }
        .fallback-link {
            margin: 18px 0 0;
            font-size: 12px;
            color: #64748b;
            text-align: center;
            word-break: break-all;
        }
        .fallback-link a {
            color: {{ $primary }};
            text-decoration: none;
        }
        .footer {
            padding: 18px 32px 24px;
            border-top: 1px solid #e8efea;
            background: #f8faf9;
            text-align: center;
        }
        .footer p {
            margin: 0 0 8px;
            font-size: 12px;
            color: #64748b;
        }
        .footer .tagline {
            margin-top: 12px;
            font-style: italic;
            color: {{ $primary }};
            font-weight: 600;
        }
        @media only screen and (max-width: 600px) {
            body { padding: 12px 8px; }
            .header, .content, .actions, .footer { padding-left: 20px; padding-right: 20px; }
            .headline { font-size: 20px; }
            .detail-row { flex-direction: column; gap: 4px; }
            .detail-value { text-align: left; }
            .btn { display: block; width: 100%; box-sizing: border-box; }
        }
    </style>
</head>
<body>
    <div class="outer">
        <div class="card">
            <div class="brand-bar"></div>
            <div class="header">
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}">
                <p class="brand-name">{{ $brandName }}</p>
                <h1 class="headline">@yield('headline')</h1>
                @hasSection('subheadline')
                    <p class="subheadline">@yield('subheadline')</p>
                @endif
            </div>

            <div class="content">
                @yield('content')
            </div>

            @hasSection('action_url')
                <div class="actions">
                    <a href="@yield('action_url')" class="btn">@yield('action_label', 'Open ticket')</a>
                    <p class="fallback-link">
                        If the button does not work, copy this link into your browser:<br>
                        <a href="@yield('action_url')">@yield('action_url')</a>
                    </p>
                </div>
            @endif

            <div class="footer">
                <p>This is an automated message from {{ $brandName }}.</p>
                <p>Please do not reply to this email.</p>
                <p class="tagline">Prompt support keeps Africa CDC operations running smoothly.</p>
                <p>&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
