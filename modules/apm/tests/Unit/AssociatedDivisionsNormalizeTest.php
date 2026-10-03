<?php

use App\Support\AssociatedDivisions;

test('normalize associated divisions from array json and null', function () {
    expect(AssociatedDivisions::normalize(null))->toBe([]);
    expect(AssociatedDivisions::normalize(''))->toBe([]);
    expect(AssociatedDivisions::normalize([2, '3', 0, 'x']))->toBe([2, 3]);
    expect(AssociatedDivisions::normalize('[2,3]'))->toBe([2, 3]);
});
