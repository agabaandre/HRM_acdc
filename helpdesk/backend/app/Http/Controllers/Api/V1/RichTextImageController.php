<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\RichTextImagePath;
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
            'image' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/gif,image/webp'],
        ]);

        $file = $validated['image'];
        $ext = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'png';
        $name = Str::uuid()->toString().'.'.$ext;

        try {
            $path = $file->storeAs('helpdesk/rich-text/'.$user->id, $name, 'public');
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not save the image on the server. Check storage permissions.',
            ], 500);
        }

        if (! $path) {
            return response()->json([
                'message' => 'Could not save the image on the server. Check storage permissions.',
            ], 500);
        }

        return response()->json([
            'data' => [
                'url' => Storage::disk('public')->url($path),
                'original_name' => $file->getClientOriginalName(),
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $path = RichTextImagePath::pathForUser($validated['url'], (int) $user->id);
        if ($path === null) {
            abort(404, 'Image not found.');
        }

        Storage::disk('public')->delete($path);

        return response()->json([
            'data' => ['deleted' => true],
        ]);
    }
}
