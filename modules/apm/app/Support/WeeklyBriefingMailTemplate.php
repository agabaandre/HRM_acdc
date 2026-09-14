<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Support\Facades\View;

/**
 * Formal HTML wrapper for weekly brief emails (Africa CDC branding, salutation, closing).
 */
final class WeeklyBriefingMailTemplate
{
    public static function subjectSuffix(): string
    {
        return ' — Africa CDC';
    }

    /**
     * @param  string  $innerHtml  Trusted HTML only (caller must escape dynamic text).
     */
    public static function wrap(?Staff $recipient, string $headerTitle, string $innerHtml): string
    {
        return View::make('emails.weekly-briefing-notification', [
            'headerTitle' => $headerTitle,
            'recipient' => $recipient,
            'bodyHtml' => $innerHtml,
        ])->render();
    }
}
