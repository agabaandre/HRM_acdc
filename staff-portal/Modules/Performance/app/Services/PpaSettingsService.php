<?php

namespace Modules\Performance\Services;

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
     * @return array{start: ?int, end: ?int}
     */
    public function submissionMonths(PerformancePhase $phase): array
    {
        $s = $this->settings();

        return match ($phase) {
            PerformancePhase::Ppa => [
                'start' => PerformanceMonth::normalize($s->ppa_start ?? null),
                'end' => PerformanceMonth::normalize($s->ppa_deadline ?? null),
            ],
            PerformancePhase::Midterm => [
                'start' => PerformanceMonth::normalize($s->mid_term_start ?? null),
                'end' => PerformanceMonth::normalize($s->mid_term_deadline ?? null),
            ],
            PerformancePhase::Endterm => [
                'start' => PerformanceMonth::normalize($s->end_term_start ?? null),
                'end' => PerformanceMonth::normalize($s->end_term_deadline ?? null),
            ],
        };
    }

    public function isSubmissionOpen(PerformancePhase $phase, ?int $month = null): bool
    {
        $month ??= (int) date('n');
        ['start' => $start, 'end' => $end] = $this->submissionMonths($phase);

        if ($start === null && $end === null) {
            return true;
        }

        if ($start !== null && $end === null) {
            return $month >= $start;
        }

        if ($start === null && $end !== null) {
            return $month <= $end;
        }

        if ($start <= $end) {
            return $month >= $start && $month <= $end;
        }

        return $month >= $start || $month <= $end;
    }

    /**
     * @return array{open: bool, start: ?int, end: ?int, label: string, message: string}
     */
    public function submissionWindowStatus(PerformancePhase $phase, ?int $month = null): array
    {
        $month ??= (int) date('n');
        $window = $this->submissionMonths($phase);
        $open = $this->isSubmissionOpen($phase, $month);
        $label = $this->submissionWindowLabel($window['start'], $window['end']);

        return [
            'open' => $open,
            'start' => $window['start'],
            'end' => $window['end'],
            'label' => $label,
            'message' => $open
                ? "Submissions are open this month ({$label})."
                : "Submissions are closed this month. Window: {$label}.",
        ];
    }

    public function submissionWindowLabel(?int $start, ?int $end): string
    {
        if ($start === null && $end === null) {
            return 'Any month (no restriction)';
        }

        if ($start !== null && $end !== null) {
            return PerformanceMonth::label($start).' – '.PerformanceMonth::label($end);
        }

        if ($start !== null) {
            return 'From '.PerformanceMonth::label($start);
        }

        return 'Until '.PerformanceMonth::label($end);
    }

    /**
     * @return array<string, array{open: bool, start: ?int, end: ?int, label: string, message: string}>
     */
    public function allSubmissionWindowStatuses(): array
    {
        $out = [];
        foreach ([PerformancePhase::Ppa, PerformancePhase::Midterm, PerformancePhase::Endterm] as $phase) {
            $out[$phase->value] = $this->submissionWindowStatus($phase);
        }

        return $out;
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
        ];
    }

    protected function defaultSettings(): object
    {
        return (object) $this->defaultsArray();
    }
}
