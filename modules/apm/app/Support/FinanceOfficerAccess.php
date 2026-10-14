<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Who may view org-wide budget execution (all divisions), not only their home unit.
 */
final class FinanceOfficerAccess
{
    /**
     * Division assignment, Finance Directorate membership, or finance-officer job title.
     */
    public static function grantsOrgWideBudgetExecution(int $staffId): bool
    {
        if ($staffId < 1) {
            return false;
        }

        try {
            return self::isAssignedOnDivision($staffId)
                || self::belongsToFinanceDirectorate($staffId)
                || self::hasFinanceOfficerJobTitle($staffId);
        } catch (Throwable) {
            return false;
        }
    }

    public static function jobTitleLooksLikeFinanceOfficer(?string $jobName): bool
    {
        $job = trim((string) $jobName);
        if ($job === '') {
            return false;
        }

        return (bool) preg_match('/finance\s+officer/i', $job);
    }

    private static function isAssignedOnDivision(int $staffId): bool
    {
        if (! Schema::hasTable('divisions') || ! Schema::hasColumn('divisions', 'finance_officer')) {
            return false;
        }

        if (DB::table('divisions')->where('finance_officer', $staffId)->exists()) {
            return true;
        }

        if (! Schema::hasColumn('divisions', 'finance_officer_oic_id')) {
            return false;
        }

        $today = now()->toDateString();
        $q = DB::table('divisions')->where('finance_officer_oic_id', $staffId);
        if (Schema::hasColumn('divisions', 'finance_officer_oic_start_date')) {
            $q->where(function ($inner) use ($today) {
                $inner->whereNull('finance_officer_oic_start_date')
                    ->orWhereDate('finance_officer_oic_start_date', '<=', $today);
            });
        }
        if (Schema::hasColumn('divisions', 'finance_officer_oic_end_date')) {
            $q->where(function ($inner) use ($today) {
                $inner->whereNull('finance_officer_oic_end_date')
                    ->orWhereDate('finance_officer_oic_end_date', '>=', $today);
            });
        }

        return $q->exists();
    }

    private static function belongsToFinanceDirectorate(int $staffId): bool
    {
        if (! Schema::hasTable('staff') || ! Schema::hasColumn('staff', 'division_id')) {
            return false;
        }
        if (! Schema::hasTable('divisions')) {
            return false;
        }

        $divisionId = (int) (DB::table('staff')->where('staff_id', $staffId)->value('division_id') ?? 0);
        if ($divisionId < 1) {
            return false;
        }

        $row = DB::table('divisions')->where('id', $divisionId)->first(['division_name', 'division_short_name']);
        if ($row === null) {
            return false;
        }

        $name = strtolower(trim((string) ($row->division_name ?? '')));
        $short = strtolower(trim((string) ($row->division_short_name ?? '')));

        return str_contains($name, 'directorate of finance')
            || $short === 'dof'
            || $short === 'finance';
    }

    private static function hasFinanceOfficerJobTitle(int $staffId): bool
    {
        if (! Schema::hasTable('staff') || ! Schema::hasColumn('staff', 'job_name')) {
            return false;
        }

        $job = DB::table('staff')->where('staff_id', $staffId)->value('job_name');

        return self::jobTitleLooksLikeFinanceOfficer(is_string($job) ? $job : null);
    }
}
