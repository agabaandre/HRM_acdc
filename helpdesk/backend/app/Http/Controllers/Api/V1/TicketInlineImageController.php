<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Handles inline image uploads from the rich-text editor (Quill image button,
 * drag-and-drop, paste). Stores the image as a regular ticket attachment
 * (so it is audited and cleaned up with the ticket) and returns the public
 * URL that the editor inserts at the cursor.
 *
 * Images-only on purpose: PDFs and Word docs cannot be embedded inline and
 * should continue to use the separate /attachments endpoint.
 */
class TicketInlineImageController extends Controller
{
    public function store(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        $this->authorize('attachFiles', $ticket);

        $validated = $request->validate([
            // 10 MB matches the regular attachment limit; image mimes only.
            'image' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $file = $validated['image'];
        $path = $file->store('helpdesk/'.$ticket->id.'/inline', 'public');

        $row = HelpdeskTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $row->id,
                'url' => Storage::disk('public')->url($row->path),
                'original_name' => $row->original_name,
            ],
        ], 201);
    }
}
