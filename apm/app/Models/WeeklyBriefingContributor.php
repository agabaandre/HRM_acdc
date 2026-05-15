<?php

namespace App\Models;

use App\Services\WeeklyBriefingCompletionSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyBriefingContributor extends Model
{
    protected $fillable = [
        'weekly_briefing_setting_id',
        'staff_id',
        'apm_division_id',
        'contribution_key',
        'display_name',
    ];

    protected function casts(): array
    {
        return [
            'weekly_briefing_setting_id' => 'integer',
            'staff_id' => 'integer',
            'apm_division_id' => 'integer',
        ];
    }

    public static function contributionKeyForDivision(int $divisionId): string
    {
        return 'd-'.$divisionId;
    }

    public static function contributionKeyForDirectorate(int $directorateId): string
    {
        return 'dr-'.$directorateId;
    }

    /**
     * Custom PDF / UI label for a reporting unit (first non-empty among contributor rows for this key).
     */
    public static function displayNameForContributionKey(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }
        $v = WeeklyBriefingSetting::current()
            ->contributors()
            ->where('contribution_key', $key)
            ->whereNotNull('display_name')
            ->where('display_name', '!=', '')
            ->orderBy('id')
            ->value('display_name');

        if ($v === null) {
            return null;
        }
        $t = trim((string) $v);

        return $t !== '' ? $t : null;
    }

    public static function presentationLabelForContributionKey(string $key): string
    {
        return self::displayNameForContributionKey($key)
            ?? \App\Services\WeeklyBriefingCompletionSummary::labelForKey($key);
    }

    /**
     * Label for this contributor row on the Weekly brief hub (PDF display name when set, else division/directorate name for this key).
     */
    public function hubLabel(): string
    {
        $dn = trim((string) ($this->display_name ?? ''));
        if ($dn !== '') {
            return $dn;
        }

        return WeeklyBriefingCompletionSummary::labelForKey($this->effectiveContributionKey());
    }

    /**
     * Division-scoped storage key for this row (never a shared directorate {@code dr-*} key when APM division is set).
     */
    public function effectiveContributionKey(): string
    {
        return \App\Services\WeeklyBriefingContributionKeyResolver::effectiveKeyForContributor($this);
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(WeeklyBriefingSetting::class, 'weekly_briefing_setting_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function apmDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'apm_division_id');
    }
}
