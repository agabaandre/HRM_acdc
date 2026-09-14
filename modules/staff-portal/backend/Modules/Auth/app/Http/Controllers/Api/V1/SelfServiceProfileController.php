<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Models\PortalUser;
use Modules\Auth\Services\SelfServiceProfileService;

class SelfServiceProfileController extends Controller
{
    public function __construct(
        protected SelfServiceProfileService $profiles,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profiles->show($this->user($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $this->profiles->update($this->user($request), $request->all());

        return response()->json(['data' => $payload, 'message' => 'Profile updated successfully.']);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);
        $payload = $this->profiles->storePhoto($this->user($request), $request->file('photo'));

        return response()->json(['data' => $payload, 'message' => 'Photo updated.']);
    }

    public function uploadPassport(Request $request): JsonResponse
    {
        $request->validate([
            'passport' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
        ]);
        $payload = $this->profiles->storePassport($this->user($request), $request->file('passport'));

        return response()->json(['data' => $payload, 'message' => 'Passport biodata updated.']);
    }

    public function uploadSignature(Request $request): JsonResponse
    {
        if ($request->hasFile('signature')) {
            $request->validate([
                'signature' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp'],
            ]);
            $payload = $this->profiles->storeSignatureFile($this->user($request), $request->file('signature'));
        } else {
            $validated = $request->validate(['data_url' => ['required', 'string']]);
            $payload = $this->profiles->storeSignatureDataUrl($this->user($request), $validated['data_url']);
        }

        return response()->json(['data' => $payload, 'message' => 'Signature updated.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->profiles->changePassword($this->user($request), $validated);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    protected function user(Request $request): PortalUser
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            abort(401);
        }

        return $user;
    }
}
