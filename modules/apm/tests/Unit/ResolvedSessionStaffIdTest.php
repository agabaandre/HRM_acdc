<?php

test('user_session staff_id falls back to auth_staff_id', function () {
    session(['user' => ['auth_staff_id' => 600, 'division_id' => 21]]);

    expect(user_session('staff_id'))->toBe(600)
        ->and(resolved_session_staff_id())->toBe(600);
});

test('user_session auth_staff_id falls back to staff_id', function () {
    session(['user' => ['staff_id' => 74, 'division_id' => 21]]);

    expect(user_session('auth_staff_id'))->toBe(74)
        ->and(resolved_session_staff_id())->toBe(74);
});

test('normalize_session_staff_ids persists both aliases on the session', function () {
    session(['user' => ['staff_id' => 91]]);

    normalize_session_staff_ids();

    $user = session('user');
    expect($user['staff_id'])->toBe(91)
        ->and($user['auth_staff_id'])->toBe(91);
});

test('resolved_session_staff_id returns null when session has no staff identity', function () {
    session(['user' => ['division_id' => 21]]);

    expect(resolved_session_staff_id())->toBeNull();
});
