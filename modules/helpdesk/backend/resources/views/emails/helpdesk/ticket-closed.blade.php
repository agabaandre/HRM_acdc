@extends('emails.helpdesk.layout')

@section('title', 'Ticket closed')
@section('headline', 'Your ticket has been closed')
@section('subheadline', $autoClosed ? 'The review period ended without further action from you.' : 'Thank you for confirming the resolution.')

@section('content')
    <p>Hello <strong>{{ $ticket->requester_name ?? 'colleague' }}</strong>,</p>

    <p>
        Your ticket <strong>{{ $ticket->ticket_number }}</strong> —
        <em>{{ $ticket->subject }}</em> — is now <strong>closed</strong>.
    </p>

    @if ($autoClosed)
        <p>
            The ticket remained in <em>Resolved</em> status for the configured review period. If the issue
            persists, you may still reopen it from the Helpdesk with a comment explaining what is not working.
        </p>
    @else
        <p>Thank you for confirming that the resolution met your needs.</p>
    @endif

    @if ($ticket->resolution_summary)
        <div class="details">
            <p class="details-title">Resolution summary</p>
            <div class="resolution-box">
                {!! $ticket->resolution_summary !!}
            </div>
        </div>
    @endif
@endsection

@section('action_url', $ticketUrl)
@section('action_label', 'View ticket')
