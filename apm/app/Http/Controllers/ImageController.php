<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    /**
     * Handle image upload for Summernote editor
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            // Validate the uploaded file
            $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            ]);

            if (!$request->hasFile('file')) {
                return response()->json([
                    'error' => 'No file uploaded'
                ], 400);
            }

            $file = $request->file('file');

            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            // Store the file in public/uploads/summernote directory
            $path = $file->storeAs('uploads/summernote', $filename, 'public');

            // Get the full URL for the uploaded image
            $url = asset('storage/' . $path);

            // Return the URL for Summernote
            return response()->json([
                'url' => $url,
                'filename' => $filename,
                'path' => $path
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Image upload failed: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Image upload failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Delete uploaded image
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'filename' => 'required|string',
            ]);

            $filename = $request->input('filename');
            $path = 'uploads/summernote/' . $filename;

            // Check if file exists and delete it
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Image deleted successfully'
                ]);
            }

            return response()->json([
                'error' => 'File not found'
            ], 404);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Image deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Image deletion failed. Please try again.'
            ], 500);
        }
    }
}
