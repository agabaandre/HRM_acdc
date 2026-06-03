<?php

use App\Helpers\PrintHelper;
use App\Models\OtherMemo;

test('other memo pdf title uses subject from payload schema', function () {
    $memo = new OtherMemo([
        'payload' => ['subject' => 'Budget realignment'],
        'fields_schema_snapshot' => [
            ['field' => 'subject', 'display' => 'Subject', 'field_type' => 'text', 'enabled' => true],
        ],
    ]);

    expect(PrintHelper::otherMemoPdfHeading())->toBe('INTEROFFICE MEMORANDUM')
        ->and(PrintHelper::otherMemoSubjectText($memo))->toBe('Budget realignment')
        ->and(PrintHelper::otherMemoSubjectFieldKey($memo))->toBe('subject');
});

test('other memo subject falls back to memo type name', function () {
    $memo = new OtherMemo([
        'memo_type_name_snapshot' => 'General memo',
        'payload' => [],
        'fields_schema_snapshot' => [],
    ]);

    expect(PrintHelper::otherMemoSubjectText($memo))->toBe('General memo');
});

test('two approvers default to from and to without through', function () {
    $approvers = [
        ['sequence' => 1, 'staff_id' => 10, 'memo_section' => 'through'],
        ['sequence' => 2, 'staff_id' => 20, 'memo_section' => 'through'],
    ];

    $result = PrintHelper::applyOtherMemoDefaultSections($approvers);

    expect($result[0]['memo_section'])->toBe('from')
        ->and($result[1]['memo_section'])->toBe('to');
});

test('apply other memo default sections when to and from are not chosen', function () {
    $approvers = [
        ['sequence' => 1, 'staff_id' => 10, 'role_label' => 'HOD', 'memo_section' => 'through'],
        ['sequence' => 2, 'staff_id' => 20, 'role_label' => 'CoS', 'memo_section' => 'through'],
        ['sequence' => 3, 'staff_id' => 30, 'role_label' => 'DG', 'memo_section' => 'through'],
    ];

    $result = PrintHelper::applyOtherMemoDefaultSections($approvers);

    expect($result[0]['memo_section'])->toBe('from')
        ->and($result[1]['memo_section'])->toBe('through')
        ->and($result[2]['memo_section'])->toBe('to');
});

test('apply other memo default sections leaves explicit to or from untouched', function () {
    $approvers = [
        ['sequence' => 1, 'staff_id' => 10, 'memo_section' => 'through'],
        ['sequence' => 2, 'staff_id' => 20, 'memo_section' => 'to'],
    ];

    $result = PrintHelper::applyOtherMemoDefaultSections($approvers);

    expect($result[0]['memo_section'])->toBe('through')
        ->and($result[1]['memo_section'])->toBe('to');
});
