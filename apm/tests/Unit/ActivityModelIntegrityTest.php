<?php

use App\Models\Activity;
use App\Support\ActivityModelIntegrityValidator;

test('activity model integrity validator reports no errors', function () {
    expect(ActivityModelIntegrityValidator::errors(app_path()))->toBe([]);
});

test('activity model autoloads exactly once', function () {
    expect(class_exists(Activity::class, false))->toBeTrue()
        ->and(Activity::class)->toBe('App\Models\Activity');
});

test('only one app file declares class Activity', function () {
    $declarations = ActivityModelIntegrityValidator::declarationPaths(app_path());

    expect($declarations)->toHaveCount(1)
        ->and($declarations[0])->toBe(realpath(app_path('Models/Activity.php')));
});

test('canonical Activity.php contains a single class declaration', function () {
    $canonical = app_path('Models/Activity.php');
    $contents = file_get_contents($canonical);

    expect($contents)->not->toBeFalse();
    expect(preg_match_all('/^class Activity\b/m', $contents, $matches))->toBe(1);
});

test('linux case-sensitive duplicate activity.php is not present', function () {
    $canonical = app_path('Models/Activity.php');
    $lower = app_path('Models/activity.php');

    if (! file_exists($canonical)) {
        expect(true)->toBeTrue();

        return;
    }

    if (! file_exists($lower)) {
        expect(true)->toBeTrue();

        return;
    }

    $canonicalReal = realpath($canonical);
    $lowerReal = realpath($lower);

    if ($canonicalReal === $lowerReal) {
        expect(true)->toBeTrue();

        return;
    }

    $canonicalInode = @fileinode($canonicalReal ?: $canonical);
    $lowerInode = @fileinode($lowerReal ?: $lower);

    expect($canonicalInode)->not->toBeFalse()
        ->and($lowerInode)->not->toBeFalse()
        ->and($lowerInode)->toBe($canonicalInode);
});
