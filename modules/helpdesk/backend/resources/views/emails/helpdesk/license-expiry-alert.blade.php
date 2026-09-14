@extends('emails.helpdesk.layout')

@section('title', 'License expiry alert')
@section('headline', ($expiry['is_expired'] ?? false) ? 'License expired' : 'License expiring soon')
@section('subheadline', $license->name)

@section('content')
    <p>Hello <strong>{{ $recipientName }}</strong>,</p>

    <p>
        @if ($expiry['is_expired'] ?? false)
            The following software license has <strong>expired</strong> and may need renewal:
        @else
            The following software license is <strong>approaching expiry</strong>:
        @endif
    </p>

    <ul style="margin: 0; padding-left: 1.25rem;">
        <li style="margin-bottom: 0.35rem;"><strong>Name:</strong> {{ $license->name }}</li>
        @if ($license->vendor)
            <li style="margin-bottom: 0.35rem;"><strong>Vendor:</strong> {{ $license->vendor }}</li>
        @endif
        <li style="margin-bottom: 0.35rem;">
            <strong>Expiry date:</strong>
            {{ $license->expiry_date?->format('Y-m-d') ?? '—' }}
        </li>
        <li style="margin-bottom: 0.35rem;">
            <strong>Days remaining:</strong>
            @if (($expiry['days_until_expiry'] ?? null) === null)
                —
            @elseif (($expiry['days_until_expiry'] ?? 0) < 0)
                Expired {{ abs((int) $expiry['days_until_expiry']) }} day(s) ago
            @else
                {{ (int) $expiry['days_until_expiry'] }} day(s)
            @endif
        </li>
        @if ($license->responsible_person)
            <li style="margin-bottom: 0.35rem;">
                <strong>Responsible:</strong>
                {{ $license->responsible_person['name'] }}
                @if (!empty($license->responsible_person['email']))
                    ({{ $license->responsible_person['email'] }})
                @endif
            </li>
        @endif
    </ul>

    <p style="margin-top: 1rem;">Please review renewal and update the license record when complete.</p>
@endsection

@section('action_url', $licensesUrl)
@section('action_label', 'Open licenses')
