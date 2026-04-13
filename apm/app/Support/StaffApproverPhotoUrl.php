<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Absolute portrait URL for staff used in approval UIs/APIs.
 * APM public disk profile_photo when present; else authenticated staff-uploads/photo URL for legacy CI3 files.
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

        $url = StaffPhotoRoute::url($photo);

        return $url !== '' ? $url : null;
    }
}
