<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateBrandingRequest;
use App\Models\BrandingSetting;
use Illuminate\Http\JsonResponse;

class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => BrandingSetting::current()->toPublicArray()]);
    }

    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        $branding = BrandingSetting::current();
        $data = $request->safe()->except(['logo', 'logo_dark', 'favicon']);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        if ($request->hasFile('logo_dark')) {
            $data['logo_dark_path'] = $request->file('logo_dark')->store('branding', 'public');
        }

        if ($request->hasFile('favicon')) {
            $data['favicon_path'] = $request->file('favicon')->store('branding', 'public');
        }

        $branding->update($data);

        return response()->json([
            'data' => $branding->fresh()->toPublicArray(),
            'message' => 'Branding updated.',
        ]);
    }
}
