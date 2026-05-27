<x-mail::message>
# Hello {{ $ticket->requester_name ?? 'colleague' }},

@if($requiresConfirmation)
Your ticket **{{ $ticket->ticket_number }}** — *{{ $ticket->subject }}* — has been marked resolved by the service desk.

**What was done**

<div style="font-size:14px; line-height:1.55; color:#1e293b;">
{{-- resolution_summary is sanitised server-side via App\Services\HtmlSanitizer --}}
{!! $ticket->resolution_summary !!}
</div>

Please confirm that this resolves your issue:

<x-mail::button :url="$confirmUrl">
Confirm resolution
</x-mail::button>

If the button does not work, copy this link into your browser:<br>
<span style="word-break: break-all;">{{ $confirmUrl }}</span>
@else
Your ticket **{{ $ticket->ticket_number }}** has been resolved.

**What was done**

<div style="font-size:14px; line-height:1.55; color:#1e293b;">
{!! $ticket->resolution_summary !!}
</div>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
