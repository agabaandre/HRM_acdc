<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExchangeTokenRequest;
use App\Http\Resources\Api\V1\MeResource;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ExchangeTokenController extends Controller
{
    public function __invoke(ExchangeTokenRequest $request): JsonResponse
    {
        $secret = (string) config('helpdesk.bridge_secret', '');
        if ($secret === '') {
            abort(503, 'Helpdesk bridge is not configured.');
        }

        $staffId = (int) $request->validated('staff_id');
        $ts = (int) $request->validated('ts');
        $email = strtolower($request->validated('email'));
        $sig = strtolower($request->validated('sig'));

        if (abs(time() - $ts) > 300) {
            abort(403, 'Stale signature timestamp.');
        }

        $message = $staffId.'|'.$ts.'|'.$email;
        $expected = hash_hmac('sha256', $message, $secret);
        if (! hash_equals($expected, $sig)) {
            abort(403, 'Invalid signature.');
        }

        $role = $request->validated('role') ?? HelpdeskProfile::ROLE_USER;
        if (! in_array($role, [
            HelpdeskProfile::ROLE_USER,
            HelpdeskProfile::ROLE_AGENT,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_AUDITOR,
        ], true)) {
            $role = HelpdeskProfile::ROLE_USER;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $request->validated('email')],
            [
                'name' => $request->validated('name'),
                'password' => Hash::make(Str::random(40)),
            ]
        );

        $attrs = ['name' => $request->validated('name')];
        $photoRaw = $request->validated('photo');
        if (is_string($photoRaw) && trim($photoRaw) !== '') {
            $attrs['photo'] = basename(str_replace('\\', '/', $photoRaw));
        }

        $user->forceFill($attrs)->save();

        $profileAttrs = [
            'staff_id' => $staffId,
            'role' => $role,
            'directorate_id' => $request->validated('directorate_id'),
            'division_id' => $request->validated('division_id'),
            'synced_at' => now(),
        ];
        if ($request->has('sap_no')) {
            $raw = trim((string) $request->input('sap_no', ''));
            $profileAttrs['sap_no'] = $raw === '' ? null : Str::limit($raw, 64, '');
        }

        $user->helpdeskProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileAttrs
        );

        $user->tokens()->where('name', 'helpdesk-bridge')->delete();
        $plain = $user->createToken('helpdesk-bridge', ['helpdesk:*'])->plainTextToken;

        return response()->json([
            'token' => $plain,
            'token_type' => 'Bearer',
            'user' => new MeResource($user->fresh(['helpdeskProfile'])),
        ]);
    }
}
