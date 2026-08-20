@extends('emails.helpdesk.layout')

@section('title', 'Ticket resolved')
@section('headline', 'Your ticket has been resolved')
@section('subheadline', 'Please review the solution below and confirm when you are satisfied.')

@section('content')
    <p>Hello <strong>{{ $ticket->requester_name ?? 'colleague' }}</strong>,</p>

    <p>
        Your ticket <strong>{{ $ticket->ticket_number }}</strong> —
        <em>{{ $ticket->subject }}</em> — has been <strong>resolved</strong> by the HelpDesk team.
    </p>

    <div class="details">
        <p class="details-title">What we did</p>
        <div class="resolution-box">
            {!! $ticket->resolution_summary !!}
        </div>
    </div>

    <div class="note-box">
        If the issue is <strong>not</strong> fixed, open the ticket in the Helpdesk and add a comment to
        <strong>reopen</strong> it so we can continue working on it.
        When you are satisfied, you may <strong>mark the ticket as closed</strong> from the ticket page.
        If no action is taken, the ticket will close automatically after the configured review period.
    </div>
@endsection

@section('action_url', $ticketUrl)
@section('action_label', 'View ticket & respond')
