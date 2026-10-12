<?php

use App\Models\ActivityReport;
use App\Services\ActivityReport\ActivityReportAccessService;
use App\Services\ActivityReport\ActivityReportRegistryService;
use App\Services\ActivityReport\ActivityReportReminderService;
use Carbon\Carbon;

function reminderSvc(): ActivityReportReminderService
{
    return new ActivityReportReminderService(
        new ActivityReportRegistryService,
        new ActivityReportAccessService,
    );
}

it('schedules reminders seven days after end date for all types', function () {
    $svc = reminderSvc();
    $end = Carbon::parse('2026-10-01');
    expect($svc->reminderDueOn($end)->toDateString())->toBe('2026-10-08');
});

it('uses memo_date as end date for non-travel style sources', function () {
    $svc = reminderSvc();
    $source = new class extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
        public $date_to = null;
        public $end_date = null;
        public $memo_date = '2026-10-05';
    };

    expect($svc->endDateForSource($source)->toDateString())->toBe('2026-10-05');
});

it('is not eligible when report has been submitted', function () {
    $svc = reminderSvc();
    $source = new class extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
        public $date_to = '2026-10-01';
    };
    $report = new ActivityReport([
        'has_been_submitted' => true,
        'not_executed' => false,
    ]);

    expect($svc->isEligible($source, $report, Carbon::parse('2026-10-20')))->toBeFalse();
});

it('is not eligible when not_executed', function () {
    $svc = reminderSvc();
    $source = new class extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
        public $date_to = '2026-10-01';
    };
    $report = new ActivityReport([
        'has_been_submitted' => false,
        'not_executed' => true,
    ]);

    expect($svc->isEligible($source, $report, Carbon::parse('2026-10-20')))->toBeFalse();
});

it('is eligible eight days after end within quarter with draft or no report', function () {
    $svc = reminderSvc();
    $source = new class extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
        public $date_to = '2026-10-01';
    };

    expect($svc->isEligible($source, null, Carbon::parse('2026-10-08')))->toBeTrue()
        ->and($svc->isEligible($source, null, Carbon::parse('2026-10-07')))->toBeFalse();
});

it('treats fund types 2 and 3 as external funded in registry constant', function () {
    expect(ActivityReportRegistryService::EXTERNAL_FUND_TYPE_IDS)->toBe([2, 3]);
});
