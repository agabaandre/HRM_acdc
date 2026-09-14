<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTicketCommentRequest;
use App\Http\Resources\Api\V1\TicketCommentResource;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Services\RequesterTicketFollowUpService;
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
        RequesterTicketFollowUpService $followUp,
    ): JsonResponse {
        $this->authorize('comment', $ticket);

        $user = $request->user();
        $profile = $user->helpdeskProfile;
        $wantsInternal = (bool) $request->validated('is_internal', false);

        if ($wantsInternal) {
            $this->authorize('commentInternal', $ticket);
        }

        $requestReopen = $request->boolean('reopen_ticket');
        if (
            $requestReopen
            && $profile
            && $profile->role === HelpdeskProfile::ROLE_USER
            && ! $wantsInternal
            && HelpdeskSetting::requesterUnsatisfiedFollowUpEnabled()
            && $followUp->isClosedStatus((string) $ticket->status)
        ) {
            $this->authorize('reopen', $ticket);
        }

        abort_unless($profile, 403);

        $result = $followUp->commentAndMaybeReopen(
            $ticket,
            $user,
            $profile,
            $request->validated('body'),
            $requestReopen,
            $wantsInternal,
        );

        $comment = $result['comment'];

        return (new TicketCommentResource($comment->load('user')))
            ->additional(['meta' => ['ticket_reopened' => $result['ticket_reopened']]])
            ->response()
            ->setStatusCode(201);
    }
}
