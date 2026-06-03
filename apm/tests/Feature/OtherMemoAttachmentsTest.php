<?php

use App\Support\OtherMemoAttachments;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

test('extractUploadedFiles reads nested attachments index file', function () {
    $pdf = UploadedFile::fake()->create('doc.pdf', 64, 'application/pdf');

    $request = Request::create('/other-memos', 'POST', [
        'attachments' => [
            0 => ['type' => 'Supporting letter'],
        ],
    ], [], [
        'attachments' => [
            0 => ['file' => $pdf],
        ],
    ]);

    $files = OtherMemoAttachments::extractUploadedFiles($request);

    expect($files)->toHaveCount(1)
        ->and($files[0]->getClientOriginalName())->toBe('doc.pdf');
});

test('collectFromCreateRequest stores metadata rows', function () {
    $pdf = UploadedFile::fake()->create('doc.pdf', 64, 'application/pdf');

    $request = Request::create('/other-memos', 'POST', [
        'attachments' => [
            0 => ['type' => 'Invoice'],
        ],
    ], [], [
        'attachments' => [
            0 => ['file' => $pdf],
        ],
    ]);

    $rows = OtherMemoAttachments::collectFromCreateRequest($request, true);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['type'])->toBe('Invoice')
        ->and($rows[0]['path'])->toContain('uploads/other-memos/');
});

test('normalizeStored drops rows without path', function () {
    $rows = OtherMemoAttachments::normalizeStored([
        ['type' => 'X'],
        ['type' => 'Y', 'path' => 'uploads/other-memos/test.pdf'],
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['path'])->toBe('uploads/other-memos/test.pdf');
});
