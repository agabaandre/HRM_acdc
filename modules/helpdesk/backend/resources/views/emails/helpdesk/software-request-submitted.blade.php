@extends('emails.helpdesk.layout')

@section('title', 'New software request')
@section('headline', 'New software request submitted')
@section('subheadline', $softwareRequest->request_number)

@section('content')
    <p>Hello <strong>{{ $recipientName }}</strong>,</p>

    <p>A new software request has been submitted and needs review:</p>

    <ul style="margin: 0; padding-left: 1.25rem;">
        <li style="margin-bottom: 0.35rem;"><strong>ID:</strong> {{ $softwareRequest->request_number }}</li>
        <li style="margin-bottom: 0.35rem;"><strong>Title:</strong> {{ $softwareRequest->request_title }}</li>
        <li style="margin-bottom: 0.35rem;"><strong>Requester:</strong> {{ $softwareRequest->requester_name }}</li>
        <li style="margin-bottom: 0.35rem;">
            <strong>Division:</strong>
            {{ $softwareRequest->division_name ?: ($softwareRequest->department ?: '—') }}
        </li>
        <li style="margin-bottom: 0.35rem;">
            <strong>Directorate:</strong> {{ $softwareRequest->directorate_name ?: '—' }}
        </li>
        <li style="margin-bottom: 0.35rem;"><strong>Priority:</strong> {{ $softwareRequest->priority }}</li>
    </ul>

    <p style="margin-top: 1rem;">Please review the request in the Helpdesk software requests module.</p>
@endsection

@section('action_url', $requestsUrl)
@section('action_label', 'Open software requests')
