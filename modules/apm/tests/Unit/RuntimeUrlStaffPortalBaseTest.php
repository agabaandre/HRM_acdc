<?php

use App\Support\RuntimeUrl;
use Illuminate\Http\Request;

test('staff portal base url ignores localhost session claims on production host', function () {
    session(['user' => ['base_url' => 'http://localhost/staff/']]);

    $request = Request::create('https://cbp.africacdc.org/staff/apm/home', 'GET');
    $request->headers->set('Host', 'cbp.africacdc.org');
    app()->instance('request', $request);

    expect(RuntimeUrl::staffPortalBaseUrl())->toBe('https://cbp.africacdc.org/staff');
});

test('sanitizeSessionUserBaseUrl rewrites localhost session base_url on production', function () {
    session(['user' => ['base_url' => 'http://localhost/staff/', 'staff_id' => 1]]);

    $request = Request::create('https://cbp.africacdc.org/staff/apm/home', 'GET');
    $request->headers->set('Host', 'cbp.africacdc.org');
    app()->instance('request', $request);

    RuntimeUrl::sanitizeSessionUserBaseUrl();

    expect(session('user.base_url'))->toBe('https://cbp.africacdc.org/staff/');
});
