<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Eagerly load Activity and related models once during app boot.
 *
 * Prevents "Cannot declare class App\Models\Activity" on some PHP setups when
 * morph eager-loading tries to include Activity.php while it is already loading.
 */
final class ActivityModelWarmup
{
    /**
     * @var list<class-string>
     */
    private const CLASSES = [
        \App\Models\ActivityApprovalTrail::class,
        \App\Models\ActivityBudget::class,
        \App\Models\ChangeRequest::class,
        \App\Models\Matrix::class,
        \App\Models\Activity::class,
    ];

    public static function run(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        foreach (self::CLASSES as $class) {
            if (! class_exists($class, false)) {
                class_exists($class);
            }
        }
    }
}
