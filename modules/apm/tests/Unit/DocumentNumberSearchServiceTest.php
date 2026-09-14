<?php

use App\Models\DocumentCounter;
use App\Services\DocumentNumberSearchService;

it('maps show urls for known document types', function () {
    $svc = new DocumentNumberSearchService;

    expect($svc->showUrl(DocumentCounter::TYPE_SPECIAL_MEMO, 12))
        ->toContain('/special-memo/12')
        ->and($svc->showUrl(DocumentCounter::TYPE_SINGLE_MEMO, 5))
        ->toContain('single-memos')
        ->and($svc->showUrl(DocumentCounter::TYPE_QUARTERLY_MATRIX, 9, 3))
        ->toContain('/matrices/3/activities/9')
        ->and($svc->showUrl('UNKNOWN', 1))
        ->toBe('#');
});

it('treats permission 87 as unrestricted divisions', function () {
    $svc = new DocumentNumberSearchService;
    expect($svc->resolveDivisionIds(1, 10, [87]))->toBeNull();
});

it('returns empty division list when no division and no staff context', function () {
    $svc = new DocumentNumberSearchService;
    expect($svc->resolveDivisionIds(null, null, []))->toBe([]);
});

it('includes primary division id when present', function () {
    $svc = new DocumentNumberSearchService;
    expect($svc->resolveDivisionIds(null, 42, []))->toBe([42]);
});

it('returns empty search results for short queries', function () {
    $svc = new DocumentNumberSearchService;
    expect($svc->search('AB', 2026, null, 1, []))->toBe([]);
});
