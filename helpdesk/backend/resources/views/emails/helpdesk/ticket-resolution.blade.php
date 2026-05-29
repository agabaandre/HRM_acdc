<x-mail::message>
# Hello {{ $ticket->requester_name ?? 'colleague' }},

Your ticket **{{ $ticket->ticket_number }}** — *{{ $ticket->subject }}* — has been **closed** by the IT Service Desk.

**What we did**

<div style="font-size:14px; line-height:1.55; color:#1e293b;">
{{-- resolution_summary is sanitised server-side via App\Services\HtmlSanitizer --}}
{!! $ticket->resolution_summary !!}
</div>

Please review the resolution. If the issue is **not** fixed, open the ticket in the Helpdesk (sign in via the Staff portal) to **add a comment** or **reopen** the request so we can continue working on it.

<x-mail::button :url="$ticketUrl">
View ticket &amp; respond
</x-mail::button>

If the button does not work, copy this link into your browser:<br>
<span style="word-break: break-all;">{{ $ticketUrl }}</span>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
