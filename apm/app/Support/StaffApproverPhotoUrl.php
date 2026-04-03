<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Absolute portrait URL for staff used in approval UIs/APIs.
 * Matches ApmAuthController sources: APM public disk profile_photo, else Staff portal uploads/staff/{photo}.
 */
class StaffApproverPhotoUrl
{
    public static function resolve(?Staff $staff): ?string
    {
        if ($staff === null) {
            return null;
        }

        if (Schema::hasColumn('staff', 'profile_photo')) {
            $profilePath = trim((string) ($staff->getAttributes()['profile_photo'] ?? $staff->profile_photo ?? ''));
            if ($profilePath !== '' && !str_contains($profilePath, '..')) {
                try {
                    $disk = Storage::disk('public');
                    if ($disk->exists($profilePath)) {
                        return url($disk->url($profilePath));
                    }
                } catch (\Throwable $e) {
                    // fall through to legacy photo
                }
            }
        }

        $photo = trim((string) ($staff->photo ?? ''));
        if ($photo === '') {
            return null;
        }
        $filename = basename($photo);
        if ($filename === '' || str_contains($filename, '..')) {
            return null;
        }

        foreach (self::staffPortalBaseUrls() as $base) {
            if ($base !== '') {
                return rtrim($base, '/') . '/uploads/staff/' . rawurlencode($filename);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function staffPortalBaseUrls(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $staffBaseUrl = rtrim((string) config('services.staff_api.base_url', ''), '/');
        $urls = array_filter([$staffBaseUrl, $appUrl], fn ($u) => $u !== '');
        if ($appUrl !== '' && str_ends_with($appUrl, '/apm')) {
            $urls[] = preg_replace('#/apm$#', '', $appUrl);
        }

        return array_values(array_unique($urls));
    }
}
