<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

test('nested attachments index accepts pdf file', function () {
    $pdf = UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf');

    $request = Request::create('/other-memos', 'POST', [
        'attachments' => [
            0 => ['type' => 'Signed contract'],
        ],
    ], [], [
        'attachments' => [
            0 => ['file' => $pdf],
        ],
    ]);

    $validator = Validator::make($request->all(), [
        'attachments' => 'sometimes|array',
        'attachments.*.type' => 'nullable|string|max:255',
        'attachments.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,ppt,pptx,xls,xlsx,doc,docx|max:10240',
    ]);

    expect($validator->passes())->toBeTrue();
    expect($request->file('attachments.0.file'))->not->toBeNull();
    expect($request->file('attachments.0.file')->isValid())->toBeTrue();
});

test('legacy mismatched attachments index fails file rule on type row', function () {
    $pdf = UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf');

    $request = Request::create('/other-memos', 'POST', [
        'attachments' => [
            1 => ['type' => 'Signed contract'],
        ],
    ], [], [
        'attachments' => [
            0 => $pdf,
        ],
    ]);

    $validator = Validator::make($request->all(), [
        'attachments.*' => 'nullable|file|mimes:pdf|max:10240',
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('attachments.1'))->toBeTrue();
});

test('upload memo requires attachments.0.file', function () {
    $pdf = UploadedFile::fake()->create('upload.pdf', 32, 'application/pdf');

    $request = Request::create('/other-memos', 'POST', [
        'attachments' => [
            0 => ['type' => 'Main document'],
        ],
    ], [], [
        'attachments' => [
            0 => ['file' => $pdf],
        ],
    ]);

    $validator = Validator::make($request->all(), [
        'attachments' => 'required|array|size:1',
        'attachments.0.file' => 'required|file|mimes:pdf|max:10240',
    ]);

    expect($validator->passes())->toBeTrue();
});
