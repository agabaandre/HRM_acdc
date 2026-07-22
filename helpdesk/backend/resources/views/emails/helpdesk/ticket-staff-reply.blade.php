@extends('emails.helpdesk.layout')

@section('title', 'Ticket update')
@section('headline', 'New reply on your ticket')
@section('subheadline', 'The Service Desk team has added an update.')

@section('content')
    <p>Hello <strong>{{ $ticket->requester_name ?? 'colleague' }}</strong>,</p>

    <p>
        <strong>{{ $agent->name }}</strong> replied on ticket
        <strong>{{ $ticket->ticket_number }}</strong> —
        <em>{{ $ticket->subject }}</em>.
    </p>

    <div class="details">
        <p class="details-title">Reply</p>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Message</span>
            <span class="detail-value">
                <div class="comment-box">{{ strip_tags($comment->body) }}</div>
            </span>
        </div>
    </div>

    <div class="note-box">
        Open the ticket in the Helpdesk (via the Staff portal) to read the full thread and reply if you need more help.
    </div>
@endsection

@section('action_url', $ticketUrl)
@section('action_label', 'View ticket & reply')
