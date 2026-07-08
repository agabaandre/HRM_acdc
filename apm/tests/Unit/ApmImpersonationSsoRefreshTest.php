<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;

it('does not replace an impersonated APM session during SSO refresh', function () {
    $admin = [
        'user_id' => 1,
        'staff_id' => 100,
        'role' => 10,
        'name' => 'Admin User',
        'sso_jwt' => 'old-token',
    ];
    $impersonated = [
        'user_id' => 2,
        'staff_id' => 200,
        'role' => 3,
        'name' => 'Target User',
        'is_impersonated' => true,
    ];

    session([
        'original_user' => $admin,
        'impersonation_start' => time(),
        'user' => $impersonated,
        'permissions' => [],
        'last_activity' => now(),
    ]);

    $controller = app(AuthController::class);
    $response = $controller->ssoRefresh(Request::create('/sso/refresh', 'POST', [
        'sso_token' => 'not-a-valid-jwt',
    ]));

    expect($response->getStatusCode())->toBe(200);
    $payload = $response->getData(true);
    expect($payload['success'])->toBeTrue()
        ->and($payload['impersonating'])->toBeTrue();

    expect(session('user'))->toMatchArray([
        'user_id' => 2,
        'staff_id' => 200,
        'is_impersonated' => true,
    ]);
    expect(session('original_user'))->toHaveKey('sso_jwt', 'old-token');
});
