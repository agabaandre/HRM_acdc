<?php

namespace Modules\Settings\Livewire;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Core\Livewire\Concerns\InteractsWithPortalTable;
use Modules\Settings\Services\SettingsLookupService;

#[Layout('core::layouts.app')]
class LookupTableManager extends Component
{
    use ChecksPortalPermission;
    use InteractsWithPortalTable;

    public string $table;

    #[Url(as: 'q')]
    public string $search = '';

    protected function queryString(): array
    {
        return array_merge([
            'search' => ['except' => '', 'as' => 'q'],
        ], $this->queryStringTable());
    }

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $editId = null;

    public function mount(string $table): void
    {
        $this->authorizePortal(15);
        $this->table = $table;
        $cfg = app(SettingsLookupService::class)->config($table);
        if ($cfg === null && ! in_array($table, ['divisions', 'cbp_modules'], true)) {
            abort(404, 'Unknown settings table.');
        }
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetTablePage();
    }

    public function resetForm(): void
    {
        $this->editId = null;
        $cfg = app(SettingsLookupService::class)->config($this->table);
        $this->form = [];
        if ($cfg) {
            foreach ($cfg['columns'] as $col => $meta) {
                $this->form[$col] = ($meta['type'] ?? '') === 'checkbox' ? false : '';
            }
        }
    }

    public function edit(int $id): void
    {
        $cfg = app(SettingsLookupService::class)->config($this->table);
        if (! $cfg) {
            return;
        }
        $row = \Illuminate\Support\Facades\DB::table($this->table)->where($cfg['pk'], $id)->first();
        if (! $row) {
            return;
        }
        $this->editId = $id;
        foreach (array_keys($cfg['columns']) as $col) {
            $this->form[$col] = $row->{$col} ?? '';
            if (($cfg['columns'][$col]['type'] ?? '') === 'checkbox') {
                $this->form[$col] = (bool) ($row->{$col} ?? 0);
            }
        }
    }

    public function save(SettingsLookupService $service): void
    {
        if ($this->editId) {
            $service->update($this->table, $this->editId, $this->form);
            session()->flash('success', 'Record updated.');
        } else {
            $service->create($this->table, $this->form);
            session()->flash('success', 'Record added.');
        }
        $this->resetForm();
    }

    public function delete(int $id, SettingsLookupService $service): void
    {
        $service->delete($this->table, $id);
        session()->flash('success', 'Record deleted.');
    }

    public function render(SettingsLookupService $service)
    {
        if ($this->table === 'cbp_modules') {
            return view('settings::livewire.cbp-modules-manager', [
                'modules' => \Illuminate\Support\Facades\DB::table('cbp_modules')->orderBy('sort_order')->get(),
            ]);
        }

        $cfg = $service->config($this->table);

        $paginator = $cfg
            ? $service->paginate($this->table, $this->search, $this->perPage, $this->getPage())
            : new LengthAwarePaginator([], 0, $this->perPage, 1);
        $range = $this->tableRange($paginator);

        return view('settings::livewire.lookup-table-manager', [
            'cfg' => $cfg,
            'rows' => $paginator,
            'from' => $range['from'],
            'to' => $range['to'],
            'total' => $range['total'],
        ]);
    }
}
