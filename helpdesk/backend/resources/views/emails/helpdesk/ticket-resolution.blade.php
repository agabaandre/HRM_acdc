@extends('emails.helpdesk.layout')

@section('title', 'Ticket closed')
@section('headline', 'Your ticket has been closed')
@section('subheadline', 'Please review the resolution below.')

@section('content')
    <p>Hello <strong>{{ $ticket->requester_name ?? 'colleague' }}</strong>,</p>

    <p>
        Your ticket <strong>{{ $ticket->ticket_number }}</strong> —
        <em>{{ $ticket->subject }}</em> — has been closed by the IT Service Desk team.
    </p>

    <div class="details">
        <p class="details-title">What we did</p>
        <div class="resolution-box">
            {!! $ticket->resolution_summary !!}
        </div>
    </div>

    <div class="note-box">
        If the issue is <strong>not</strong> fixed, open the ticket in the Helpdesk (sign in via the Staff portal)
        to add a comment or reopen the request so we can continue working on it.
    </div>
@endsection

@section('action_url', $ticketUrl)
@section('action_label', 'View ticket & respond')
