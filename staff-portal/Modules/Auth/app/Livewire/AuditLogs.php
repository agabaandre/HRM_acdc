<?php

namespace Modules\Auth\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Core\Livewire\Concerns\InteractsWithPortalTable;
use Modules\Core\Support\PortalTable;
use Illuminate\Pagination\LengthAwarePaginator;

#[Layout('core::layouts.app')]
class AuditLogs extends Component
{
    use ChecksPortalPermission;
    use InteractsWithPortalTable;

    #[Url(as: 'q')]
    public string $search = '';

    protected function queryString(): array
    {
        return array_merge([
            'search' => ['except' => '', 'as' => 'q'],
        ], $this->queryStringTable());
    }

    public function mount(): void
    {
        $this->authorizePortal(17);
    }

    public function updatedSearch(): void
    {
        $this->resetTablePage();
    }

    public function render()
    {
        if (! Schema::hasTable('user_logs')) {
            $empty = new LengthAwarePaginator([], 0, $this->perPage, 1);

            return view('auth::livewire.audit-logs', [
                'logs' => $empty,
                'from' => 0,
                'to' => 0,
                'total' => 0,
            ]);
        }

        $query = DB::table('user_logs as ul')
            ->leftJoin('user as u', 'u.user_id', '=', 'ul.user_id')
            ->select('ul.*', 'u.name as user_name')
            ->orderByDesc('ul.id');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($w) use ($term): void {
                $w->where('ul.action', 'like', $term)
                    ->orWhere('ul.request_uri', 'like', $term)
                    ->orWhere('ul.target_table', 'like', $term)
                    ->orWhere('ul.event_type', 'like', $term)
                    ->orWhere('u.name', 'like', $term);
            });
        }

        $paginator = PortalTable::paginateDistinct($query, 'ul.id', $this->perPage, $this->getPage());
        $range = $this->tableRange($paginator);

        return view('auth::livewire.audit-logs', [
            'logs' => $paginator,
            'from' => $range['from'],
            'to' => $range['to'],
            'total' => $range['total'],
        ]);
    }
}
