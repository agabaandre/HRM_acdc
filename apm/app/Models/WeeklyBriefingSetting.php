<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyBriefingSetting extends Model
{
    protected $fillable = [
        'submission_weekday',
        'hod_reminder_time',
        'submission_close_time',
        'summary_send_time',
        'compiled_recipient_emails',
        'cc_division_hod_on_compiled',
        'reminders_enabled',
        'division_directors_can_access_module',
        'report_viewer_staff_ids',
    ];

    protected function casts(): array
    {
        return [
            'submission_weekday' => 'integer',
            'cc_division_hod_on_compiled' => 'boolean',
            'reminders_enabled' => 'boolean',
            'division_directors_can_access_module' => 'boolean',
            'report_viewer_staff_ids' => 'array',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'submission_weekday' => 5,
            'hod_reminder_time' => '09:00',
            'submission_close_time' => '14:00',
            'summary_send_time' => '14:10',
            'compiled_recipient_emails' => null,
            'cc_division_hod_on_compiled' => true,
            'reminders_enabled' => true,
            'division_directors_can_access_module' => true,
        ]);
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(WeeklyBriefingContributor::class, 'weekly_briefing_setting_id');
    }

    /**
     * Whether the current clock time (H:i) matches a stored time column (e.g. hod_reminder_time).
     */
    public function matchesTimeNow(string $attribute): bool
    {
        $value = $this->getAttribute($attribute);
        if ($value === null || $value === '') {
            return false;
        }
        $hm = is_string($value) ? substr($value, 0, 5) : Carbon::parse($value)->format('H:i');

        return Carbon::now()->format('H:i') === $hm;
    }
}
