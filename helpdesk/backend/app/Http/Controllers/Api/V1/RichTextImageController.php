<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Inline images for rich-text fields (KB articles, new tickets before save, etc.)
 * when no ticket exists yet. Ticket resolution should prefer
 * TicketInlineImageController so images stay on the ticket record.
 */
class RichTextImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'image' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $file = $validated['image'];
        $ext = $file->guessExtension() ?: 'png';
        $name = Str::uuid()->toString().'.'.$ext;
        $path = $file->storeAs('helpdesk/rich-text/'.$user->id, $name, 'public');

        return response()->json([
            'data' => [
                'url' => Storage::disk('public')->url($path),
                'original_name' => $file->getClientOriginalName(),
            ],
        ], 201);
    }
}
