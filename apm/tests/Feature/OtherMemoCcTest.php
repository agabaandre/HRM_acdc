<?php

use App\Models\MemoTypeDefinition;
use App\Support\OtherMemoCc;
use Illuminate\Http\Request;

test('other memo cc omitted when creator does not include cc', function () {
    $def = new MemoTypeDefinition(['cc_on_approval_enabled' => true]);

    $request = Request::create('/', 'POST', []);

    $attrs = OtherMemoCc::attributesFromRequest($request, $def);

    expect($attrs['cc_on_approval_enabled_snapshot'])->toBeTrue()
        ->and($attrs['cc_config'])->toBeNull();
});

test('other memo cc all staff config from request when included', function () {
    $def = new MemoTypeDefinition(['cc_on_approval_enabled' => true]);

    $request = Request::create('/', 'POST', [
        'cc_include' => '1',
        'cc_mode' => 'all',
        'cc_all_staff_heading' => 'Principal Advisor to the DG',
        'cc_all_staff_label' => 'All Africa CDC Staff',
    ]);

    $config = OtherMemoCc::buildConfigFromRequest($request);

    expect($config)->toMatchArray([
        'mode' => 'all',
        'all_staff_heading' => 'Principal Advisor to the DG',
        'all_staff_label' => 'All Africa CDC Staff',
    ]);
});

test('other memo cc requires staff when specific mode', function () {
    $request = Request::create('/', 'POST', [
        'cc_mode' => 'specific',
        'cc_staff_ids' => [],
    ]);

    expect(fn () => OtherMemoCc::buildConfigFromRequest($request))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('other memo cc not stored when type does not allow cc ui', function () {
    $def = new MemoTypeDefinition(['cc_on_approval_enabled' => false]);

    $request = Request::create('/', 'POST', [
        'cc_include' => '1',
        'cc_mode' => 'all',
    ]);

    $attrs = OtherMemoCc::attributesFromRequest($request, $def);

    expect($attrs['cc_on_approval_enabled_snapshot'])->toBeFalse()
        ->and($attrs['cc_config'])->toBeNull();
});
