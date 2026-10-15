<?php

use App\Models\SystemSetting;
use App\Support\ActivityReportingModule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Cache::flush();
    if (! Schema::hasTable('system_settings')) {
        Schema::create('system_settings', function ($table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->string('group', 50)->nullable();
            $table->string('type', 20)->nullable();
            $table->timestamps();
        });
    }
    SystemSetting::query()->whereIn('key', [
        ActivityReportingModule::ENABLED_KEY,
        ActivityReportingModule::NOTIFICATIONS_KEY,
    ])->delete();
});

test('activity reporting module is disabled by default', function () {
    expect(ActivityReportingModule::isEnabled())->toBeFalse()
        ->and(ActivityReportingModule::notificationsEnabled())->toBeFalse();
});

test('notifications require module enabled', function () {
    SystemSetting::set(ActivityReportingModule::NOTIFICATIONS_KEY, '1', 'modules', 'boolean');
    expect(ActivityReportingModule::notificationsEnabled())->toBeFalse();

    SystemSetting::set(ActivityReportingModule::ENABLED_KEY, '1', 'modules', 'boolean');
    Cache::flush();
    expect(ActivityReportingModule::isEnabled())->toBeTrue()
        ->and(ActivityReportingModule::notificationsEnabled())->toBeTrue();
});

test('helper mirrors module enabled flag', function () {
    expect(activity_reporting_module_enabled())->toBeFalse();
    SystemSetting::set(ActivityReportingModule::ENABLED_KEY, '1', 'modules', 'boolean');
    Cache::flush();
    expect(activity_reporting_module_enabled())->toBeTrue();
});
