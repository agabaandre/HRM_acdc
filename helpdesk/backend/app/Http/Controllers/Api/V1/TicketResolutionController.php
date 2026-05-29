<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\TicketResolutionMail;
use App\Models\HelpdeskKbArticle;
use App\Models\HelpdeskTicket;
use App\Services\HtmlSanitizer;
use App\Services\TicketHistoryLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TicketResolutionController extends Controller
{
    public function submit(Request $request, HelpdeskTicket $ticket, TicketHistoryLogger $logger): JsonResponse
    {
        $this->authorize('submitResolution', $ticket);

        // 65000 chars matches the `description` ceiling and gives ample room for
        // HTML markup (Quill stores formatting + embedded image URLs).
        $validated = $request->validate([
            'resolution_summary' => ['required', 'string', 'max:65000'],
            'publish_to_kb' => ['sometimes', 'boolean'],
            'kb_question' => ['required_if:publish_to_kb,true', 'nullable', 'string', 'max:255'],
        ]);

        $clean = HtmlSanitizer::sanitize($validated['resolution_summary']);
        if ($clean === null) {
            throw ValidationException::withMessages([
                'resolution_summary' => 'Resolution notes are empty after sanitisation.',
            ]);
        }

        $ticket->resolution_summary = $clean;
        $ticket->resolution_submitted_by_user_id = $request->user()->id;

        $now = now();
        $ticket->status = 'closed';
        $ticket->resolved_at = $now;
        $ticket->closed_at = $now;
        $ticket->resolved_by_user_id = $request->user()->id;
        $ticket->resolution_confirm_token = null;
        $ticket->resolution_confirmed_at = null;

        $ticket->save();

        $logger->log($ticket, 'ticket.closed', $request->user()->id, [
            'resolution_submitted' => true,
        ]);

        $kbArticleId = null;
        if (! empty($validated['publish_to_kb'])) {
            $profile = $request->user()?->helpdeskProfile;
            abort_unless(
                $profile && $profile->canManageKnowledgeBase(),
                403,
                'You need the admin role or the “manage knowledge base” permission to publish articles.'
            );

            $question = trim((string) ($validated['kb_question'] ?? ''));
            if ($question === '') {
                throw ValidationException::withMessages([
                    'kb_question' => 'A knowledge base subject is required when publishing.',
                ]);
            }

            $article = HelpdeskKbArticle::query()->create([
                'category_id' => $ticket->category_id,
                'question' => $question,
                'answer' => $clean,
                'sort_order' => 0,
                'is_active' => true,
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $kbArticleId = $article->id;
        }

        $frontend = rtrim((string) config('helpdesk.frontend_url', 'http://localhost:5174'), '/');
        $ticketUrl = $frontend.'/tickets/'.$ticket->id;

        $email = $ticket->requester_email;
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Mail::to($email)->send(new TicketResolutionMail(
                $ticket->fresh(),
                $ticketUrl,
            ));
        }

        $message = 'Resolution recorded; the ticket is closed and the requester was notified by email.';
        if ($kbArticleId !== null) {
            $message .= ' A knowledge base article was published.';
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'resolution_summary' => $ticket->resolution_summary,
                'kb_article_id' => $kbArticleId,
            ],
        ]);
    }
}
