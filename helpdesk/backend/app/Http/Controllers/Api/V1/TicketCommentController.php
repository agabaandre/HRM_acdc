<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTicketCommentRequest;
use App\Http\Resources\Api\V1\TicketCommentResource;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Services\TicketCommentNotifier;
use App\Services\TicketHistoryLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketCommentController extends Controller
{
    public function index(Request $request, HelpdeskTicket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        $profile = $request->user()->helpdeskProfile;
        $q = $ticket->comments()->with('user')->orderBy('id');

        if ($profile && $profile->role === HelpdeskProfile::ROLE_USER) {
            $q->where('is_internal', false);
        }

        $comments = $q->paginate(min((int) $request->get('per_page', 50), 100));

        return TicketCommentResource::collection($comments);
    }

    public function store(
        StoreTicketCommentRequest $request,
        HelpdeskTicket $ticket,
        TicketCommentNotifier $notifier,
        TicketHistoryLogger $logger,
    ): JsonResponse {
        $this->authorize('comment', $ticket);

        $user = $request->user();
        $profile = $user->helpdeskProfile;
        $wantsInternal = (bool) $request->validated('is_internal', false);

        if ($wantsInternal) {
            $this->authorize('commentInternal', $ticket);
        }

        $comment = $ticket->comments()->create([
            'user_id' => $user->id,
            'author_staff_id' => $profile?->staff_id,
            'is_internal' => $wantsInternal,
            'body' => $request->validated('body'),
        ]);

        $isRequesterComment = $profile
            && $profile->role === HelpdeskProfile::ROLE_USER
            && ! $wantsInternal;

        $ticketReopened = false;
        if (
            $isRequesterComment
            && $request->boolean('reopen_ticket')
            && HelpdeskSetting::requesterUnsatisfiedFollowUpEnabled()
            && in_array($ticket->status, ['closed', 'resolved', 'awaiting_requester_confirmation'], true)
        ) {
            $this->authorize('reopen', $ticket);

            $previousStatus = $ticket->status;
            $ticket->forceFill([
                'status' => 'open',
                'closed_at' => null,
                'resolved_at' => null,
                'resolution_confirmed_at' => null,
                'resolution_confirm_token' => null,
            ])->save();

            $logger->log($ticket, 'ticket.reopened', $user->id, [
                'previous_status' => $previousStatus,
                'via' => 'requester_comment',
            ]);
            $ticketReopened = true;
        }

        if ($isRequesterComment && HelpdeskSetting::requesterUnsatisfiedFollowUpEnabled()) {
            $notifier->notifyAssigneeOnRequesterComment($ticket, $comment, $user, $ticketReopened);
        }

        return (new TicketCommentResource($comment->load('user')))
            ->additional(['meta' => ['ticket_reopened' => $ticketReopened]])
            ->response()
            ->setStatusCode(201);
    }
}
