<?php

namespace Modules\Performance\Services;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Support\PerformanceMonth;

class PpaSettingsService
{
    public function settings(): object
    {
        $row = DB::table('ppa_configs')->orderBy('id')->first();

        return $row ?? $this->defaultSettings();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $row = DB::table('ppa_configs')->orderBy('id')->first();

        if ($row) {
            DB::table('ppa_configs')->where('id', $row->id)->update($data);
        } else {
            DB::table('ppa_configs')->insert(array_merge($this->defaultsArray(), $data));
        }
    }

    public function requiresSecondSupervisor(PerformancePhase $phase): bool
    {
        $s = $this->settings();

        return match ($phase) {
            PerformancePhase::Ppa => (bool) ($s->ppa_requires_second_supervisor ?? false),
            PerformancePhase::Midterm => (bool) ($s->midterm_requires_second_supervisor ?? false),
            PerformancePhase::Endterm => (bool) ($s->endterm_requires_second_supervisor ?? true),
        };
    }

    public function endtermRequiresEmployeeConsent(): bool
    {
        return (bool) ($this->settings()->endterm_requires_employee_consent ?? true);
    }

    /**
     * Human-readable workflow steps per phase (for settings UI and hub).
     *
     * @return array<string, list<string>>
     */
    public function workflowDescriptions(): array
    {
        $s = $this->settings();

        $ppa = ['Employee submits PPA'];
        $ppa[] = 'First supervisor approves';
        if ($s->ppa_requires_second_supervisor ?? false) {
            $ppa[] = 'Second supervisor approves';
        }
        $ppa[] = 'Approved';

        $midterm = ['Employee submits midterm review'];
        $midterm[] = 'First supervisor approves';
        if ($s->midterm_requires_second_supervisor ?? false) {
            $midterm[] = 'Second supervisor approves';
        }
        $midterm[] = 'Approved';

        $endterm = ['Employee submits end-of-year review'];
        $endterm[] = 'First supervisor approves';
        if ($s->endterm_requires_employee_consent ?? true) {
            $endterm[] = 'Employee consents to rating / results';
        }
        if ($s->endterm_requires_second_supervisor ?? true) {
            $endterm[] = 'Second supervisor approves';
        }
        $endterm[] = 'Approved';

        return [
            'ppa' => $ppa,
            'midterm' => $midterm,
            'endterm' => $endterm,
        ];
    }

    public function workflowSummaryLine(PerformancePhase $phase): string
    {
        return implode(' → ', $this->workflowDescriptions()[$phase->value]);
    }

    /**
     * Month window for a phase. PPA always starts in January of the current year
     * when no explicit start month is stored.
     *
     * @return array{start: ?int, end: ?int, override_days: int}
     */
    public function submissionMonths(PerformancePhase $phase): array
    {
        $s = $this->settings();

        return match ($phase) {
            PerformancePhase::Ppa => [
                // Each new year: PPA opens from January through the configured end month.
                'start' => PerformanceMonth::normalize($s->ppa_start ?? null) ?? 1,
                'end' => PerformanceMonth::normalize($s->ppa_deadline ?? null),
                'override_days' => max(0, (int) ($s->ppa_deadline_override_days ?? 0)),
            ],
            PerformancePhase::Midterm => [
                'start' => PerformanceMonth::normalize($s->mid_term_start ?? null),
                'end' => PerformanceMonth::normalize($s->mid_term_deadline ?? null),
                'override_days' => max(0, (int) ($s->mid_term_deadline_override_days ?? 0)),
            ],
            PerformancePhase::Endterm => [
                'start' => PerformanceMonth::normalize($s->end_term_start ?? null),
                'end' => PerformanceMonth::normalize($s->end_term_deadline ?? null),
                'override_days' => max(0, (int) ($s->end_term_deadline_override_days ?? 0)),
            ],
        };
    }

    public function isSubmissionOpen(PerformancePhase $phase, ?DateTimeImmutable $today = null): bool
    {
        $today ??= new DateTimeImmutable('today');
        $bounds = $this->activeWindowBounds($phase, $today);
        if ($bounds === null) {
            return true;
        }

        return $today >= $bounds['start'] && $today <= $bounds['end'];
    }

    /**
     * @return array{open: bool, start: ?int, end: ?int, override_days: int, label: string, message: string, opens_on: ?string, closes_on: ?string}
     */
    public function submissionWindowStatus(PerformancePhase $phase, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $window = $this->submissionMonths($phase);
        $bounds = $this->activeWindowBounds($phase, $today);
        $open = $this->isSubmissionOpen($phase, $today);
        $label = $this->submissionWindowLabel(
            $window['start'],
            $window['end'],
            $window['override_days'],
            $phase,
            $today,
        );

        $opensOn = $bounds !== null ? $bounds['start']->format('Y-m-d') : null;
        $closesOn = $bounds !== null ? $bounds['end']->format('Y-m-d') : null;

        $message = $open
            ? "Submissions are open ({$label}".($closesOn ? "; closes {$closesOn}" : '').').'
            : "Submissions are closed. Window: {$label}"
                .($opensOn && $closesOn ? " ({$opensOn} – {$closesOn})" : '').'.';

        return [
            'open' => $open,
            'start' => $window['start'],
            'end' => $window['end'],
            'override_days' => $window['override_days'],
            'label' => $label,
            'message' => $message,
            'opens_on' => $opensOn,
            'closes_on' => $closesOn,
        ];
    }

    public function submissionWindowLabel(
        ?int $start,
        ?int $end,
        int $overrideDays = 0,
        ?PerformancePhase $phase = null,
        ?DateTimeImmutable $today = null,
    ): string {
        $today ??= new DateTimeImmutable('today');
        $bounds = $phase !== null ? $this->activeWindowBounds($phase, $today) : null;
        $startYear = $bounds !== null ? (int) $bounds['start']->format('Y') : (int) $today->format('Y');
        $endYear = $bounds !== null ? (int) $bounds['end']->format('Y') : $startYear;

        if ($start === null && $end === null) {
            return 'Any month (no restriction)';
        }

        if ($start !== null && $end !== null) {
            if ($startYear !== $endYear) {
                $base = PerformanceMonth::label($start)." {$startYear} – ".PerformanceMonth::label($end).' '.$endYear;
            } else {
                $base = PerformanceMonth::label($start).' – '.PerformanceMonth::label($end)." {$startYear}";
            }
        } elseif ($start !== null) {
            $base = 'From '.PerformanceMonth::label($start)." {$startYear}";
        } else {
            $base = 'Until '.PerformanceMonth::label($end)." {$endYear}";
        }

        if ($overrideDays > 0) {
            $base .= " (+{$overrideDays} day".($overrideDays === 1 ? '' : 's').' override)';
        }

        return $base;
    }

    /**
     * @return array<string, array{open: bool, start: ?int, end: ?int, override_days: int, label: string, message: string, opens_on: ?string, closes_on: ?string}>
     */
    public function allSubmissionWindowStatuses(?DateTimeImmutable $today = null): array
    {
        $out = [];
        foreach ([PerformancePhase::Ppa, PerformancePhase::Midterm, PerformancePhase::Endterm] as $phase) {
            $out[$phase->value] = $this->submissionWindowStatus($phase, $today);
        }

        return $out;
    }

    /**
     * Resolve the concrete date range that applies for "today".
     * Endterm / wrap windows start in the current year and end in the next year.
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}|null null = unrestricted
     */
    public function activeWindowBounds(PerformancePhase $phase, ?DateTimeImmutable $today = null): ?array
    {
        $today ??= new DateTimeImmutable('today');
        ['start' => $startMonth, 'end' => $endMonth, 'override_days' => $overrideDays] = $this->submissionMonths($phase);

        if ($startMonth === null && $endMonth === null) {
            return null;
        }

        $year = (int) $today->format('Y');
        $month = (int) $today->format('n');

        // Endterm always crosses into the next year when both edges are set.
        $wraps = $startMonth !== null && $endMonth !== null && (
            $startMonth > $endMonth
            || $phase === PerformancePhase::Endterm
        );

        if ($wraps) {
            // Dec Y → Mar Y+1 (typical). Pick the cycle containing today.
            if ($startMonth !== null && $month >= $startMonth) {
                $startYear = $year;
                $endYear = $year + 1;
            } elseif ($endMonth !== null && $month <= $endMonth) {
                $startYear = $year - 1;
                $endYear = $year;
            } else {
                // Between end and start (e.g. Apr–Nov for Dec–Mar): use upcoming cycle.
                $startYear = $year;
                $endYear = $year + 1;
            }
        } else {
            $startYear = $year;
            $endYear = $year;
        }

        $start = $startMonth !== null
            ? new DateTimeImmutable(sprintf('%04d-%02d-01', $startYear, $startMonth))
            : new DateTimeImmutable(sprintf('%04d-01-01', $startYear));

        if ($endMonth !== null) {
            $end = (new DateTimeImmutable(sprintf('%04d-%02d-01', $endYear, $endMonth)))
                ->modify('last day of this month');
        } else {
            $end = new DateTimeImmutable(sprintf('%04d-12-31', $endYear));
        }

        if ($overrideDays > 0) {
            $end = $end->add(new DateInterval('P'.$overrideDays.'D'));
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultsArray(): array
    {
        return [
            'allow_supervisor_return' => 1,
            'allow_supervisor_comments' => 0,
            'allow_supervisor_ppa_edit' => 1,
            'allow_employee_comments' => 0,
            'ppa_requires_second_supervisor' => 0,
            'midterm_requires_second_supervisor' => 0,
            'endterm_requires_second_supervisor' => 1,
            'endterm_requires_employee_consent' => 1,
            'ppa_start' => 1,
            'ppa_deadline' => null,
            'mid_term_start' => null,
            'mid_term_deadline' => null,
            'end_term_start' => null,
            'end_term_deadline' => null,
            'ppa_deadline_override_days' => 0,
            'mid_term_deadline_override_days' => 0,
            'end_term_deadline_override_days' => 0,
        ];
    }

    protected function defaultSettings(): object
    {
        return (object) $this->defaultsArray();
    }
}
