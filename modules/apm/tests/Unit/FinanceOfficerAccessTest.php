<?php

use App\Support\FinanceOfficerAccess;

test('job title matching recognizes finance officers', function () {
    expect(FinanceOfficerAccess::jobTitleLooksLikeFinanceOfficer('Finance Officer'))->toBeTrue()
        ->and(FinanceOfficerAccess::jobTitleLooksLikeFinanceOfficer('Senior Finance Officer'))->toBeTrue()
        ->and(FinanceOfficerAccess::jobTitleLooksLikeFinanceOfficer('Administrative and Finance officer'))->toBeTrue()
        ->and(FinanceOfficerAccess::jobTitleLooksLikeFinanceOfficer('Access to Finance Expert PAVM'))->toBeFalse()
        ->and(FinanceOfficerAccess::jobTitleLooksLikeFinanceOfficer('Advisor-Finance and Grants Management'))->toBeFalse()
        ->and(FinanceOfficerAccess::jobTitleLooksLikeFinanceOfficer(null))->toBeFalse()
        ->and(FinanceOfficerAccess::jobTitleLooksLikeFinanceOfficer(''))->toBeFalse();
});
