<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicTicketResolutionController extends Controller
{
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $ticket = HelpdeskTicket::query()
            ->where('resolution_confirm_token', $validated['token'])
            ->whereIn('status', ['resolved', 'awaiting_requester_confirmation'])
            ->firstOrFail();

        $ticket->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
            'resolution_confirmed_at' => now(),
            'resolution_confirm_token' => null,
            'resolved_by_user_id' => $ticket->resolved_by_user_id ?? $ticket->resolution_submitted_by_user_id,
        ])->save();

        return response()->json([
            'message' => 'Thank you — this ticket is now closed.',
            'data' => [
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
            ],
        ]);
    }
}
