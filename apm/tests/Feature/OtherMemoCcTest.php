<?php

use App\Models\MemoTypeDefinition;
use App\Support\OtherMemoCc;
use Illuminate\Http\Request;

test('other memo cc all staff config from request', function () {
    $def = new MemoTypeDefinition([
        'cc_on_approval_enabled' => true,
        'cc_all_staff_heading' => 'Principal Advisor to the DG',
        'cc_all_staff_label' => 'All Africa CDC Staff',
    ]);

    $request = Request::create('/', 'POST', ['cc_all_staff' => '1']);

    $config = OtherMemoCc::buildConfigFromRequest($request, $def);

    expect($config)->toMatchArray([
        'mode' => 'all',
        'all_staff_heading' => 'Principal Advisor to the DG',
        'all_staff_label' => 'All Africa CDC Staff',
    ]);
});

test('other memo cc requires staff when not all staff', function () {
    $def = new MemoTypeDefinition(['cc_on_approval_enabled' => true]);

    $request = Request::create('/', 'POST', ['cc_all_staff' => '0']);

    expect(fn () => OtherMemoCc::buildConfigFromRequest($request, $def))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
