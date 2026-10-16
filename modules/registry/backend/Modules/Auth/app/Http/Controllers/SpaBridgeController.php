<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Auth\Models\PortalUser;

class SpaBridgeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            abort(401);
        }

        $user->tokens()->where('name', 'staff-portal-spa')->delete();

        $token = $user->createToken(
            'staff-portal-spa',
            ['*'],
            now()->addHours(8)
        )->plainTextToken;

        $redirect = rtrim((string) config('staff-portal.spa_url', '/'), '/').'/';

        return view('auth::spa-bridge', [
            'token' => $token,
            'redirect' => $redirect,
        ]);
    }
}
