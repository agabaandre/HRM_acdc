<?php

namespace Modules\Settings\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Services\PpaSettingsService;
use Modules\Performance\Support\PerformanceMonth;

#[Layout('core::layouts.app')]
class PerformanceSettings extends Component
{
    use ChecksPortalPermission;

    public string $tab = 'workflow';

    public bool $allow_supervisor_return = true;

    public bool $allow_supervisor_comments = false;

    public bool $allow_supervisor_ppa_edit = true;

    public bool $allow_employee_comments = false;

    public bool $ppa_requires_second_supervisor = false;

    public bool $midterm_requires_second_supervisor = false;

    public bool $endterm_requires_second_supervisor = true;

    public bool $endterm_requires_employee_consent = true;

    public ?int $ppa_start = null;

    public ?int $ppa_deadline = null;

    public ?int $mid_term_start = null;

    public ?int $mid_term_deadline = null;

    public ?int $end_term_start = null;

    public ?int $end_term_deadline = null;

    public function mount(PpaSettingsService $settings): void
    {
        $this->authorizePortal(15);
        $s = $settings->settings();

        $this->allow_supervisor_return = (bool) ($s->allow_supervisor_return ?? true);
        $this->allow_supervisor_comments = (bool) ($s->allow_supervisor_comments ?? false);
        $this->allow_supervisor_ppa_edit = (bool) ($s->allow_supervisor_ppa_edit ?? true);
        $this->allow_employee_comments = (bool) ($s->allow_employee_comments ?? false);
        $this->ppa_requires_second_supervisor = (bool) ($s->ppa_requires_second_supervisor ?? false);
        $this->midterm_requires_second_supervisor = (bool) ($s->midterm_requires_second_supervisor ?? false);
        $this->endterm_requires_second_supervisor = (bool) ($s->endterm_requires_second_supervisor ?? true);
        $this->endterm_requires_employee_consent = (bool) ($s->endterm_requires_employee_consent ?? true);

        $this->ppa_start = PerformanceMonth::normalize($s->ppa_start ?? null);
        $this->ppa_deadline = PerformanceMonth::normalize($s->ppa_deadline ?? null);
        $this->mid_term_start = PerformanceMonth::normalize($s->mid_term_start ?? null);
        $this->mid_term_deadline = PerformanceMonth::normalize($s->mid_term_deadline ?? null);
        $this->end_term_start = PerformanceMonth::normalize($s->end_term_start ?? null);
        $this->end_term_deadline = PerformanceMonth::normalize($s->end_term_deadline ?? null);
    }

    public function save(PpaSettingsService $settings): void
    {
        $this->validate([
            'ppa_start' => 'nullable|integer|min:1|max:12',
            'ppa_deadline' => 'nullable|integer|min:1|max:12',
            'mid_term_start' => 'nullable|integer|min:1|max:12',
            'mid_term_deadline' => 'nullable|integer|min:1|max:12',
            'end_term_start' => 'nullable|integer|min:1|max:12',
            'end_term_deadline' => 'nullable|integer|min:1|max:12',
        ]);

        $settings->save([
            'allow_supervisor_return' => $this->allow_supervisor_return ? 1 : 0,
            'allow_supervisor_comments' => $this->allow_supervisor_comments ? 1 : 0,
            'allow_supervisor_ppa_edit' => $this->allow_supervisor_ppa_edit ? 1 : 0,
            'allow_employee_comments' => $this->allow_employee_comments ? 1 : 0,
            'ppa_requires_second_supervisor' => $this->ppa_requires_second_supervisor ? 1 : 0,
            'midterm_requires_second_supervisor' => $this->midterm_requires_second_supervisor ? 1 : 0,
            'endterm_requires_second_supervisor' => $this->endterm_requires_second_supervisor ? 1 : 0,
            'endterm_requires_employee_consent' => $this->endterm_requires_employee_consent ? 1 : 0,
            'ppa_start' => $this->ppa_start,
            'ppa_deadline' => $this->ppa_deadline,
            'mid_term_start' => $this->mid_term_start,
            'mid_term_deadline' => $this->mid_term_deadline,
            'end_term_start' => $this->end_term_start,
            'end_term_deadline' => $this->end_term_deadline,
        ]);

        session()->flash('success', 'Performance & workflow settings saved.');
    }

    public function render(PpaSettingsService $settings)
    {
        return view('settings::livewire.performance-settings', [
            'workflowPreview' => $settings->workflowDescriptions(),
            'monthOptions' => PerformanceMonth::options(),
            'windowStatuses' => $settings->allSubmissionWindowStatuses(),
            'currentMonthLabel' => PerformanceMonth::label((int) date('n')),
        ]);
    }
}
