<?php

use App\Services\StaffPortalShareClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.staff_api.base_url' => 'http://staff.test',
        'services.staff_api.token' => 'testtoken',
        'services.staff_api.username' => '',
        'services.staff_api.password' => '',
        'services.staff_api.endpoints.cbp_modules' => '/share/cbp_modules',
    ]);

    if (! Schema::hasTable('system_settings')) {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
});

test('fetch cbp modules accepts staff share envelope with success and data', function () {
    Http::fake([
        'http://staff.test/share/cbp_modules*' => Http::response([
            'success' => true,
            'data' => [
                'home' => ['label' => 'CBP Home', 'href' => 'http://localhost/staff/'],
                'modules' => [
                    [
                        'module_key' => 'helpdesk_itsm',
                        'label' => 'HelpDesk',
                        'href' => 'http://localhost/staff/helpdesk',
                    ],
                ],
            ],
        ], 200),
    ]);

    $payload = (new StaffPortalShareClient)->fetchCbpModules(11, 'approvals_management', 'approvals_management');

    expect($payload['home']['label'])->toBe('CBP Home')
        ->and($payload['modules'])->toHaveCount(1)
        ->and($payload['modules'][0]['module_key'])->toBe('helpdesk_itsm');
});

test('fetch cbp modules accepts already unwrapped nav payload from StaffShareHttp', function () {
    Http::fake([
        'http://staff.test/share/cbp_modules*' => Http::response([
            'home' => ['label' => 'CBP Home', 'href' => 'http://localhost/staff/'],
            'modules' => [
                ['module_key' => 'risk_register', 'label' => 'Risk Register', 'href' => 'http://localhost/staff/risk-register'],
                ['module_key' => 'finance_management', 'label' => 'Finance', 'href' => 'http://localhost/staff/finance'],
            ],
        ], 200),
    ]);

    $payload = (new StaffPortalShareClient)->fetchCbpModules(11);

    expect($payload['modules'])->toHaveCount(2)
        ->and($payload['modules'][0]['module_key'])->toBe('risk_register');
});
