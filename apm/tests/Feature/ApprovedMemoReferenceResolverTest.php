<?php

use App\Models\MemoTypeDefinition;
use App\Support\ApprovedMemoReferenceResolver;
use Illuminate\Validation\ValidationException;

test('memo type api exposes referenced_memos_max', function () {
    $def = new MemoTypeDefinition([
        'slug' => 'ref-test',
        'name' => 'Ref test',
        'signature_style' => 'top_right',
        'referenced_memos_max' => 3,
    ]);

    expect($def->toApiArray()['referenced_memos_max'])->toBe(3);
});

test('resolve many rejects more links than max before lookup', function () {
    expect(fn () => app(ApprovedMemoReferenceResolver::class)->resolveMany([
        'http://localhost/staff/apm/other-memos/1',
        'http://localhost/staff/apm/other-memos/2',
        'http://localhost/staff/apm/other-memos/3',
        'http://localhost/staff/apm/other-memos/4',
    ], 3))->toThrow(ValidationException::class);
});

test('resolve many returns empty when max is zero', function () {
    expect(app(ApprovedMemoReferenceResolver::class)->resolveMany([], 0))->toBe([]);
});

test('resolve many rejects invalid memo url', function () {
    expect(fn () => app(ApprovedMemoReferenceResolver::class)->resolveMany([
        'https://example.com/not-a-memo',
    ], 3))->toThrow(ValidationException::class);
});
