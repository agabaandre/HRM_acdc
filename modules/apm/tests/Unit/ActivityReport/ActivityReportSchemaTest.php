<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('activity_report_related');
    Schema::dropIfExists('activity_reports');
    Schema::dropIfExists('reporting_registry_statuses');
    Schema::dropIfExists('permissions');

    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('definition');
        $table->string('module')->nullable();
    });

    $createTables = require __DIR__.'/../../../database/migrations/2026_10_12_100000_create_activity_reports_tables.php';
    $createTables->up();

    $seedPerm = require __DIR__.'/../../../database/migrations/2026_10_12_100100_seed_apm_reporting_finance_registry_permission.php';
    $seedPerm->up();
});

it('has activity report and registry tables', function () {
    expect(Schema::hasTable('activity_reports'))->toBeTrue()
        ->and(Schema::hasTable('activity_report_related'))->toBeTrue()
        ->and(Schema::hasTable('reporting_registry_statuses'))->toBeTrue();
});

it('seeds the reporting finance registry permission', function () {
    expect(
        DB::table('permissions')->where('name', 'apm_reporting_finance_registry')->exists()
    )->toBeTrue();
});
