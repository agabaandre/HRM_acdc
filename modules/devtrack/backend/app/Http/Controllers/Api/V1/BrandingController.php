<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BrandingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        try {
            $branding = BrandingSetting::current();
        } catch (Throwable) {
            $branding = new BrandingSetting([
                'app_name' => config('app.name', 'Africa CDC DevTrack'),
                'primary_color' => '#0d7a3a',
                'secondary_color' => '#c9a227',
            ]);
        }

        return response()->json(['data' => $branding->toPublicArray()]);
    }

    /**
     * Serve public branding files under /api/v1/branding/assets/...
     * Host Nginx already proxies /api/ → Laravel (unlike /storage/ which can hit the SPA).
     */
    public function asset(string $path): StreamedResponse
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || str_contains($normalized, '..')) {
            abort(404);
        }

        // Only allow branding uploads (and legacy root public files)
        if (! str_starts_with($normalized, 'branding/') && ! preg_match('/^branding-logo\.(png|jpe?g|webp)$/i', $normalized)) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($normalized)) {
            abort(404);
        }

        return Storage::disk('public')->response($normalized);
    }
}
