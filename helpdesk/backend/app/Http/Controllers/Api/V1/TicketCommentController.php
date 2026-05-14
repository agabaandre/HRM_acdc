<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTicketCommentRequest;
use App\Http\Resources\Api\V1\TicketCommentResource;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
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

    public function store(StoreTicketCommentRequest $request, HelpdeskTicket $ticket): JsonResponse
    {
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

        return (new TicketCommentResource($comment->load('user')))
            ->response()
            ->setStatusCode(201);
    }
}
