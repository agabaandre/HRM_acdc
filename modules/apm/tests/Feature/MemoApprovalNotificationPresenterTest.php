<?php

use App\Models\ChangeRequest;
use App\Services\MemoApprovalNotificationPresenter;

test('builds forward message with title document number and approver', function () {
    $model = new ChangeRequest([
        'id' => 206,
        'activity_title' => 'Regional workshop date change',
        'document_number' => 'CR/2026/0042',
        'overall_status' => 'pending',
    ]);
    $model->setRelation('division', (object) ['division_name' => 'Public Health']);

    $result = MemoApprovalNotificationPresenter::forForwardToNextApprover($model);

    expect($result['message'])->toContain('Regional workshop date change')
        ->and($result['message'])->toContain('CR/2026/0042')
        ->and($result['message'])->toContain('Change request')
        ->and($result['message'])->toContain('Approved by')
        ->and($result['view']['memo_title'])->toBe('Regional workshop date change')
        ->and($result['view']['document_number_display'])->toBe('CR/2026/0042')
        ->and($result['view']['division_name'])->toBe('Public Health');
});

test('resource label is human readable for change request', function () {
    expect(MemoApprovalNotificationPresenter::resourceLabel(new ChangeRequest))
        ->toBe('Change request');
});
