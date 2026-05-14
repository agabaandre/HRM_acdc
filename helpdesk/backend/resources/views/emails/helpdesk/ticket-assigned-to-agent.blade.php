@php
    $base = rtrim((string) config('helpdesk.frontend_url', 'http://localhost:5174'), '/');
    $ticketUrl = $base.'/tickets/'.$ticket->id;
@endphp
<x-mail::message>
# Hello {{ $assignee->name }},

@if($isReassignment)
Ticket **{{ $ticket->ticket_number }}** has been **reassigned to you**. Please review and take the next step.
@else
Ticket **{{ $ticket->ticket_number }}** is **assigned to you**. Please review and take the next step.
@endif

**Subject:** {{ $ticket->subject }}

**Requester:** {{ $ticket->requester_name ?? '—' }} — {{ $ticket->requester_email ?? '—' }}

**Priority:** {{ $ticket->priority }}

**Status:** {{ $ticket->status }}

<x-mail::button :url="$ticketUrl">
Open ticket
</x-mail::button>

If the button does not work, copy this link into your browser:<br>
<span style="word-break: break-all;">{{ $ticketUrl }}</span>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
