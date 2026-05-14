<x-mail::message>
# Hello {{ $ticket->requester_name ?? 'colleague' }},

@if($requiresConfirmation)
Your ticket **{{ $ticket->ticket_number }}** — *{{ $ticket->subject }}* — has been marked resolved by the service desk.

**What was done**

{!! nl2br(e($ticket->resolution_summary)) !!}

Please confirm that this resolves your issue:

<x-mail::button :url="$confirmUrl">
Confirm resolution
</x-mail::button>

If the button does not work, copy this link into your browser:<br>
<span style="word-break: break-all;">{{ $confirmUrl }}</span>
@else
Your ticket **{{ $ticket->ticket_number }}** has been resolved.

**What was done**

{!! nl2br(e($ticket->resolution_summary)) !!}
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
