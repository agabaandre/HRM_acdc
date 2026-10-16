<?php

namespace App\Services;

use Carbon\Carbon;

final class LicenseExpiryService
{
    /**
     * @return array{
     *   days_until_expiry: int|null,
     *   expiry_status: string,
     *   is_expiring_soon: bool,
     *   is_expired: bool,
     * }
     */
    public function snapshot(?string $expiryDate, int $warningDaysBefore = 30): array
    {
        if (! $expiryDate) {
            return [
                'days_until_expiry' => null,
                'expiry_status' => 'no_expiry',
                'is_expiring_soon' => false,
                'is_expired' => false,
            ];
        }

        $expiry = Carbon::parse($expiryDate)->startOfDay();
        $today = now()->startOfDay();
        $days = (int) $today->diffInDays($expiry, false);

        if ($days < 0) {
            return [
                'days_until_expiry' => $days,
                'expiry_status' => 'expired',
                'is_expiring_soon' => false,
                'is_expired' => true,
            ];
        }

        $warningDaysBefore = max(1, $warningDaysBefore);
        $isSoon = $days <= $warningDaysBefore;

        return [
            'days_until_expiry' => $days,
            'expiry_status' => $isSoon ? 'expiring_soon' : 'active',
            'is_expiring_soon' => $isSoon,
            'is_expired' => false,
        ];
    }
}
