@php
    $base = rtrim((string) config('helpdesk.frontend_url', 'http://localhost:5174'), '/');
    $ticketUrl = $base.'/tickets/'.$ticket->id;
@endphp
<x-mail::message>
# New comment from {{ $requester->name }}

@if($ticketReopened)
The requester added a comment and **reopened** ticket **{{ $ticket->ticket_number }}** because the issue is not resolved.
@else
The requester added a comment on ticket **{{ $ticket->ticket_number }}**.
@endif

**Subject:** {{ $ticket->subject }}

**Requester:** {{ $ticket->requester_name ?? $requester->name }} — {{ $ticket->requester_email ?? $requester->email }}

**Comment:**

{{ $comment->body }}

<x-mail::button :url="$ticketUrl">
Open ticket
</x-mail::button>

If the button does not work, copy this link into your browser:<br>
<span style="word-break: break-all;">{{ $ticketUrl }}</span>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
