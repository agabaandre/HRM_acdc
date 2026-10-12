<?php

use App\Models\ActivityReport;
use App\Services\ActivityReport\ActivityReportAccessService;

it('allows responsible staff and participants to edit a source memo', function () {
    $svc = new ActivityReportAccessService;
    $source = (object) [
        'staff_id' => 10,
        'responsible_person_id' => 20,
        'internal_participants' => [
            '30' => ['days' => 1],
            '40' => ['days' => 2],
        ],
    ];

    expect($svc->canEdit($source, 10))->toBeTrue()
        ->and($svc->canEdit($source, 20))->toBeTrue()
        ->and($svc->canEdit($source, 30))->toBeTrue()
        ->and($svc->canEdit($source, 99))->toBeFalse();
});

it('blocks edit when report is submitted but allows draft even if not executed', function () {
    $svc = new ActivityReportAccessService;
    $report = new ActivityReport([
        'staff_id' => 10,
        'responsible_person_id' => 20,
        'overall_status' => 'draft',
        'not_executed' => false,
    ]);

    expect($svc->canEdit($report, 10))->toBeTrue();

    $report->overall_status = 'approved';
    expect($svc->canEdit($report, 10))->toBeFalse();

    $report->overall_status = 'draft';
    $report->not_executed = true;
    expect($svc->canEdit($report, 10))->toBeTrue();
});

it('flattens array-of-objects participant formats', function () {
    $svc = new ActivityReportAccessService;
    $source = (object) [
        'staff_id' => 1,
        'responsible_person_id' => 1,
        'internal_participants' => [
            ['staff_id' => 55, 'days' => 2],
            ['id' => 66],
        ],
    ];

    expect($svc->participantStaffIds($source))->toContain(55, 66, 1);
});
