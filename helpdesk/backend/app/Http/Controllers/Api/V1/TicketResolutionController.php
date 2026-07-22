<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\TicketResolutionMail;
use App\Models\HelpdeskItAsset;
use App\Models\HelpdeskKbArticle;
use App\Models\HelpdeskTicket;
use App\Services\HtmlSanitizer;
use App\Services\RichTextDataUriExternalizer;
use App\Services\TicketFirstResponseService;
use App\Services\TicketHistoryLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TicketResolutionController extends Controller
{
    public function submit(Request $request, HelpdeskTicket $ticket, TicketHistoryLogger $logger, TicketFirstResponseService $firstResponse): JsonResponse
    {
        $this->authorize('submitResolution', $ticket);

        $ticket->loadMissing(['businessUnit']);

        // Externalize pasted base64 images before the 65k HTML ceiling is applied.
        $request->merge([
            'resolution_summary' => RichTextDataUriExternalizer::externalize(
                $request->input('resolution_summary'),
                ticket: $ticket,
                user: $request->user(),
            ),
        ]);

        $allowsAssetLink = (bool) ($ticket->businessUnit?->allows_asset_link_on_resolve);

        // 65000 chars matches the `description` ceiling and gives ample room for
        // HTML markup (Quill stores formatting + embedded image URLs).
        $validated = $request->validate([
            'resolution_summary' => ['required', 'string', 'max:65000'],
            'publish_to_kb' => ['sometimes', 'boolean'],
            'kb_question' => ['required_if:publish_to_kb,true', 'nullable', 'string', 'max:255'],
            'linked_it_asset_id' => [
                $allowsAssetLink ? 'nullable' : 'prohibited',
                'integer',
                'exists:helpdesk_it_assets,id',
            ],
        ]);

        $clean = HtmlSanitizer::sanitize($validated['resolution_summary']);
        if ($clean === null) {
            throw ValidationException::withMessages([
                'resolution_summary' => 'Resolution notes are empty after sanitisation.',
            ]);
        }

        if ($allowsAssetLink && ! empty($validated['linked_it_asset_id'])) {
            $assetId = (int) $validated['linked_it_asset_id'];
            $staffId = (int) ($ticket->requester_staff_id ?? 0);
            $assetQuery = HelpdeskItAsset::query()->where('id', $assetId);
            if ($staffId > 0) {
                $assetQuery->where('assigned_staff_id', $staffId);
            }
            if (! $assetQuery->exists()) {
                throw ValidationException::withMessages([
                    'linked_it_asset_id' => 'Choose an IT asset assigned to the ticket requester.',
                ]);
            }
            $ticket->linked_it_asset_id = $assetId;
        }

        $ticket->resolution_summary = $clean;
        $ticket->resolution_submitted_by_user_id = $request->user()->id;

        $now = now();
        $ticket->status = 'resolved';
        $ticket->resolved_at = $now;
        $ticket->closed_at = null;
        $ticket->resolved_by_user_id = $request->user()->id;
        $ticket->resolution_confirm_token = bin2hex(random_bytes(32));
        $ticket->resolution_confirmed_at = null;

        $firstResponse->markIfEmpty($ticket, $now);

        $ticket->save();

        $logger->log($ticket, 'ticket.resolved', $request->user()->id, [
            'resolution_submitted' => true,
            'linked_it_asset_id' => $ticket->linked_it_asset_id,
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

        $message = 'Resolution recorded; the ticket is resolved and the requester was notified by email.';
        if ($kbArticleId !== null) {
            $message .= ' A knowledge base article was published.';
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'resolution_summary' => $ticket->resolution_summary,
                'linked_it_asset_id' => $ticket->linked_it_asset_id,
                'kb_article_id' => $kbArticleId,
            ],
        ]);
    }
}
