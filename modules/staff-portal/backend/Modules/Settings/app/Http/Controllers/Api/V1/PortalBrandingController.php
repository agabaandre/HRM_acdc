<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\PortalPermission;
use Modules\Settings\Services\PortalBrandingService;

class PortalBrandingController extends Controller
{
    public function __construct(
        protected PortalBrandingService $branding,
    ) {}

    public function show(): JsonResponse
    {
        PortalPermission::authorize(15);

        return response()->json([
            'data' => $this->branding->publicPayload(),
        ]);
    }

    /** Chrome / PDF consumers (any authenticated portal user). */
    public function catalog(): JsonResponse
    {
        return response()->json([
            'data' => $this->branding->publicPayload(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:191'],
            'system_logo' => ['nullable', 'string', 'max:1024'],
            'footer_copyright' => ['nullable', 'string', 'max:500'],
            'print_footer' => ['nullable', 'string', 'max:5000'],
            'company_email' => ['nullable', 'string', 'max:191'],
            'company_phone' => ['nullable', 'string', 'max:64'],
            'company_website' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:2000'],
            'login_welcome_title' => ['nullable', 'string', 'max:191'],
            'login_welcome_text' => ['nullable', 'string', 'max:2000'],
            'login_background' => ['nullable', 'string', 'max:1024'],
        ]);

        $this->branding->save($validated);

        return response()->json([
            'message' => 'Branding settings saved.',
            'data' => $this->branding->publicPayload(),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $request->validate([
            'logo' => ['required', 'file', 'image', 'max:4096'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('logo');
        $payload = $this->branding->storeLogo($file);

        return response()->json([
            'message' => 'Logo uploaded.',
            'data' => $payload,
        ]);
    }

    public function uploadLoginBackground(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $request->validate([
            'image' => ['required', 'file', 'image', 'max:8192'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('image');
        $payload = $this->branding->storeLoginBackground($file);

        return response()->json([
            'message' => 'Login background uploaded.',
            'data' => $payload,
        ]);
    }
}
