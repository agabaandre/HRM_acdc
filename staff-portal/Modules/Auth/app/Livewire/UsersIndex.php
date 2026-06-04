<?php

namespace Modules\Auth\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Core\Livewire\Concerns\InteractsWithPortalTable;
use Modules\Core\Support\PortalTable;

#[Layout('core::layouts.app')]
class UsersIndex extends Component
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
        $query = DB::table('user as u')
            ->leftJoin('staff as s', 's.staff_id', '=', 'u.auth_staff_id')
            ->select('u.*', 's.fname', 's.lname', 's.work_email')
            ->orderBy('u.name');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($w) use ($term): void {
                $w->where('u.name', 'like', $term)
                    ->orWhere('s.work_email', 'like', $term)
                    ->orWhere('s.fname', 'like', $term)
                    ->orWhere('s.lname', 'like', $term);
            });
        }

        $paginator = PortalTable::paginateDistinct($query, 'u.user_id', $this->perPage, $this->getPage());
        $range = $this->tableRange($paginator);

        return view('auth::livewire.users-index', [
            'users' => $paginator,
            'from' => $range['from'],
            'to' => $range['to'],
            'total' => $range['total'],
        ]);
    }
}
