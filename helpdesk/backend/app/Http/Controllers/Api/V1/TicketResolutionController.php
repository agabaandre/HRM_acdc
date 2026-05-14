<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\TicketResolutionMail;
use App\Models\HelpdeskSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TicketResolutionController extends Controller
{
    public function submit(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        $this->authorize('submitResolution', $ticket);

        $validated = $request->validate([
            'resolution_summary' => ['required', 'string', 'max:8000'],
        ]);

        $ticket->resolution_summary = $validated['resolution_summary'];
        $ticket->resolution_submitted_by_user_id = $request->user()->id;

        $requires = HelpdeskSetting::requireResolutionConfirmation();
        $frontend = rtrim((string) config('helpdesk.frontend_url', 'http://localhost:5174'), '/');
        $confirmUrl = '';
        $token = null;
        if ($requires) {
            $token = Str::random(48);
            $confirmUrl = $frontend.'/tickets/confirm-resolution?token='.$token;
            $ticket->resolution_confirm_token = $token;
        }

        if ($requires) {
            $ticket->status = 'awaiting_requester_confirmation';
            $ticket->resolved_at = null;
        } else {
            $ticket->status = 'resolved';
            $ticket->resolved_at = now();
            $ticket->resolution_confirmed_at = now();
            $ticket->resolution_confirm_token = null;
            $ticket->resolved_by_user_id = $request->user()->id;
        }

        $ticket->save();

        $email = $ticket->requester_email;
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Mail::to($email)->send(new TicketResolutionMail(
                $ticket->fresh(),
                $confirmUrl,
                $requires,
            ));
        }

        return response()->json([
            'message' => $requires
                ? 'Resolution recorded; the requester was emailed a confirmation link.'
                : 'Resolution recorded and the requester was notified.',
            'data' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'resolution_summary' => $ticket->resolution_summary,
            ],
        ]);
    }
}
