@extends('emails.helpdesk.layout')

@section('title', 'Monthly agent report')
@section('headline', 'Your monthly performance report')
@section('subheadline', 'Period: {{ $report->periodLabel() }}')

@section('content')
    <p>Hello <strong>{{ $report->user?->name ?? 'colleague' }}</strong>,</p>

    <p>Your monthly Service Desk performance report is ready.</p>

    <div class="resolution-box">
        {!! nl2br(e($report->ai_summary)) !!}
    </div>

    <div class="details">
        <p class="details-title">At a glance</p>
        <div class="detail-row">
            <span class="detail-label">Tickets worked</span>
            <span class="detail-value">{{ $report->metrics_json['tickets_worked'] ?? 0 }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Resolved</span>
            <span class="detail-value">{{ $report->metrics_json['tickets_resolved'] ?? 0 }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Avg first response</span>
            <span class="detail-value">
                @if(isset($report->metrics_json['avg_first_response_minutes']))
                    {{ $report->metrics_json['avg_first_response_minutes'] }} min
                @else
                    n/a
                @endif
            </span>
        </div>
    </div>

    <p>Thank you for supporting Africa CDC staff.</p>
@endsection
