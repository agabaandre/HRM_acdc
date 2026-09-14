@php
    $base = rtrim((string) config('helpdesk.frontend_url', 'http://localhost:5174'), '/');
    $ticketUrl = $base.'/tickets/'.$ticket->id;
@endphp

@extends('emails.helpdesk.layout')

@section('title', 'New requester comment')
@section('headline', $ticketReopened ? 'Ticket reopened by requester' : 'New requester comment')
@section('subheadline', 'A requester has added an update that needs your attention.')

@section('content')
    <p>Hello,</p>

    @if($ticketReopened)
        <div class="note-box" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
            <strong>Action required:</strong> this ticket was <strong>reopened</strong> by the requester and needs your attention.
        </div>
        <p>
            <strong>{{ $requester->name }}</strong> commented on ticket
            <strong>{{ $ticket->ticket_number }}</strong> and reopened it because the issue is not resolved.
        </p>
    @else
        <p>
            <strong>{{ $requester->name }}</strong> added a comment on ticket
            <strong>{{ $ticket->ticket_number }}</strong>.
        </p>
    @endif

    <div class="details">
        <p class="details-title">Ticket details</p>
        <div class="detail-row">
            <span class="detail-label">Subject</span>
            <span class="detail-value">{{ $ticket->subject }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value">
                @if($ticketReopened)
                    Open <span style="color:#b45309;font-weight:700;">(reopened)</span>
                @else
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Requester</span>
            <span class="detail-value">
                {{ $ticket->requester_name ?? $requester->name }}
                @php $email = $ticket->requester_email ?? $requester->email; @endphp
                @if($email)
                    <br><a href="mailto:{{ $email }}">{{ $email }}</a>
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Comment</span>
            <span class="detail-value">
                <div class="comment-box">{{ $comment->body }}</div>
            </span>
        </div>
    </div>
@endsection

@section('action_url', $ticketUrl)
@section('action_label', 'Open ticket')
