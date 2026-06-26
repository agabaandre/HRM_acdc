<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketAttachment;
use App\Support\HelpdeskAttachmentUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function store(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        $this->authorize('attachFiles', $ticket);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
        ]);

        $file = $validated['file'];
        $path = $file->store('helpdesk/'.$ticket->id, 'public');

        $row = HelpdeskTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        $url = HelpdeskAttachmentUrl::forAttachment($row);

        return response()->json([
            'data' => [
                'id' => $row->id,
                'url' => $url,
                'original_name' => $row->original_name,
            ],
        ], 201);
    }

    public function destroy(Request $request, HelpdeskTicket $ticket, HelpdeskTicketAttachment $attachment): JsonResponse
    {
        $this->authorize('deleteRequestAttachment', $ticket);

        if ((int) $attachment->ticket_id !== (int) $ticket->id) {
            abort(404);
        }

        if (str_contains($attachment->path, '/inline/')) {
            abort(422, 'Only request attachments can be removed here. Use the editor to remove inline images.');
        }

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return response()->json([
            'data' => ['deleted' => true],
        ]);
    }
}
