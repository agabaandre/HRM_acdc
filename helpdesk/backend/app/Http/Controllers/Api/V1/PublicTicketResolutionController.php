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
            ->where('status', 'awaiting_requester_confirmation')
            ->firstOrFail();

        $resolverId = $ticket->resolution_submitted_by_user_id;
        $ticket->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_confirmed_at' => now(),
            'resolution_confirm_token' => null,
            'resolved_by_user_id' => $resolverId,
        ])->save();

        return response()->json([
            'message' => 'Thank you — this ticket is now marked resolved.',
            'data' => [
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
            ],
        ]);
    }
}
