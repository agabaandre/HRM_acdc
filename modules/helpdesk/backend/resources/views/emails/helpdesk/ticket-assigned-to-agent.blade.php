@php
    $base = rtrim((string) config('helpdesk.frontend_url', 'http://localhost:5174'), '/');
    $ticketUrl = $base.'/tickets/'.$ticket->id;
@endphp

@extends('emails.helpdesk.layout')

@section('title', $isReassignment ? 'Ticket reassigned to you' : 'Ticket assigned to you')
@section('headline', $isReassignment ? 'Ticket reassigned to you' : 'New ticket assigned to you')
@section('subheadline', 'Please review and take the next step.')

@section('content')
    <p>Hello <strong>{{ $assignee->name }}</strong>,</p>

    @if($isReassignment)
        <p>Ticket <strong>{{ $ticket->ticket_number }}</strong> has been reassigned to you.</p>
    @else
        <p>Ticket <strong>{{ $ticket->ticket_number }}</strong> is now assigned to you.</p>
    @endif

    <div class="details">
        <p class="details-title">Ticket details</p>
        <div class="detail-row">
            <span class="detail-label">Subject</span>
            <span class="detail-value">{{ $ticket->subject }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Requester</span>
            <span class="detail-value">
                {{ $ticket->requester_name ?? '—' }}
                @if($ticket->requester_email)
                    <br><a href="mailto:{{ $ticket->requester_email }}">{{ $ticket->requester_email }}</a>
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Priority</span>
            <span class="detail-value">{{ ucfirst($ticket->priority) }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
        </div>
    </div>
@endsection

@section('action_url', $ticketUrl)
@section('action_label', 'Open ticket')
