@extends('emails.helpdesk.layout')

@section('title', 'Open tickets reminder')
@section('headline', 'Open tickets assigned to you')
@section('subheadline', count($tickets).' ticket(s) still need attention on the agent desk.')

@section('content')
    <p>Hello <strong>{{ $agent->name }}</strong>,</p>

    <p>This is your daily reminder of tickets still assigned to you on the Service Desk:</p>

    <ul style="margin: 0; padding-left: 1.25rem;">
        @foreach ($tickets as $row)
            <li style="margin-bottom: 0.5rem;">
                <strong>{{ $row['ticket_number'] }}</strong> — {{ $row['subject'] }}
                <span style="color: #64748b;">({{ ucfirst(str_replace('_', ' ', $row['status'])) }}, {{ $row['priority'] }} priority)</span>
            </li>
        @endforeach
    </ul>

    <p style="margin-top: 1rem;">Please review, update status, or resolve these tickets when possible.</p>
@endsection

@section('action_url', $deskUrl)
@section('action_label', 'Open agent desk')
