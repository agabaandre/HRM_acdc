@extends('emails.helpdesk.layout')

@section('title', 'Ticket in progress')
@section('headline', 'Your ticket is being worked on')
@section('subheadline', 'The IT Service Desk team has started on your request.')

@section('content')
    <p>Hello <strong>{{ $ticket->requester_name ?? 'colleague' }}</strong>,</p>

    <p>
        Ticket <strong>{{ $ticket->ticket_number }}</strong> —
        <em>{{ $ticket->subject }}</em> — is now <strong>in progress</strong>.
    </p>

    @if($agent)
        <p>
            <strong>{{ $agent->name }}</strong> from the IT Service Desk is working on it.
        </p>
    @endif

    <div class="details">
        <p class="details-title">Ticket details</p>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value">In progress</span>
        </div>
        @if($ticket->category?->name)
            <div class="detail-row">
                <span class="detail-label">Category</span>
                <span class="detail-value">{{ $ticket->category->name }}</span>
            </div>
        @endif
    </div>

    <div class="note-box">
        You will receive another email when the ticket is resolved or if we need more information from you.
    </div>
@endsection

@section('action_url', $ticketUrl)
@section('action_label', 'View ticket')
