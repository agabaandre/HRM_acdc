<?php

namespace App\Support;

/**
 * One-time SSO codes issued by the Staff portal (CodeIgniter home/launch_module).
 */
final class StaffSsoLaunchCode
{
    public static function consume(?string $code, ?string $expectedModuleKey = null): ?array
    {
        return StaffSsoCodeStore::consume($code, $expectedModuleKey);
    }
}
