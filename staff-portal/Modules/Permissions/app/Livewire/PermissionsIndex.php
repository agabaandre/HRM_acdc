<?php

namespace Modules\Permissions\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Core\Livewire\Concerns\InteractsWithPortalTable;
use Modules\Permissions\Services\PermissionsService;

#[Layout('core::layouts.app')]
class PermissionsIndex extends Component
{
    use ChecksPortalPermission;
    use InteractsWithPortalTable;

    /** group | user */
    #[Url]
    public string $mode = 'group';

    #[Url(as: 'group')]
    public ?int $selectedGroupId = null;

    #[Url(as: 'user')]
    public ?int $selectedUserId = null;

    /** @var list<int> */
    public array $selectedPermissionIds = [];

    public bool $assignmentEnabled = false;

    #[Url(as: 'q')]
    public string $userSearch = '';

    #[Url]
    public ?int $userGroupFilter = null;

    public string $newGroupName = '';

    public string $newPermName = '';

    public string $newPermDefinition = '';

    protected function queryString(): array
    {
        return array_merge([
            'userSearch' => ['except' => '', 'as' => 'q'],
        ], $this->queryStringTable());
    }

    public function mount(): void
    {
        $this->authorizePortal(17);

        if (! in_array($this->mode, ['group', 'user'], true)) {
            $this->mode = 'group';
        }

        $this->loadSelectedPermissions();
    }

    public function updatedMode(): void
    {
        $this->resetTablePage();
        $this->loadSelectedPermissions();
    }

    public function updatedSelectedGroupId(): void
    {
        $this->assignmentEnabled = false;
        $this->loadSelectedPermissions();
    }

    public function updatedSelectedUserId(): void
    {
        $this->assignmentEnabled = false;
        $this->loadSelectedPermissions();
    }

    public function updatedUserSearch(): void
    {
        $this->resetTablePage();
    }

    public function updatedUserGroupFilter(): void
    {
        $this->resetTablePage();
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->mode = 'user';
        $this->assignmentEnabled = false;
        $this->loadSelectedPermissions();
    }

    protected function loadSelectedPermissions(): void
    {
        $service = app(PermissionsService::class);

        if ($this->mode === 'group' && $this->selectedGroupId) {
            $this->selectedPermissionIds = $service->groupPermissionIds($this->selectedGroupId);

            return;
        }

        if ($this->mode === 'user' && $this->selectedUserId) {
            $this->selectedPermissionIds = $service->userPermissionIds($this->selectedUserId);

            return;
        }

        $this->selectedPermissionIds = [];
    }

    public function saveAssignments(PermissionsService $service): void
    {
        if (! $this->assignmentEnabled) {
            session()->flash('error', 'Enable permission assignment before saving.');

            return;
        }

        if ($this->mode === 'group') {
            if (! $this->selectedGroupId) {
                session()->flash('error', 'Select a user group first.');

                return;
            }
            $service->assignGroupPermissions($this->selectedGroupId, $this->selectedPermissionIds);
            session()->flash('success', 'Group permissions saved. Users in this group receive these permissions on next request.');

            return;
        }

        if (! $this->selectedUserId) {
            session()->flash('error', 'Select a user first.');

            return;
        }

        $service->assignUserPermissions($this->selectedUserId, $this->selectedPermissionIds);
        session()->flash('success', 'User-specific permissions saved.');
    }

    public function copyGroupPermissions(PermissionsService $service): void
    {
        if (! $this->selectedUserId) {
            session()->flash('error', 'Select a user first.');

            return;
        }

        $service->copyGroupPermissionsToUser($this->selectedUserId);
        $this->loadSelectedPermissions();
        session()->flash('success', 'Copied permissions from the user\'s group.');
    }

    public function createGroup(PermissionsService $service): void
    {
        if (! $service->createGroup($this->newGroupName)) {
            session()->flash('error', 'Could not create group. Name must be unique and at least 3 characters.');

            return;
        }

        $this->newGroupName = '';
        session()->flash('success', 'User group created.');
    }

    public function createPermission(PermissionsService $service): void
    {
        if (! $service->createPermission($this->newPermName, $this->newPermDefinition)) {
            session()->flash('error', 'Could not create permission. Use letters/underscores for the name.');

            return;
        }

        $this->newPermName = '';
        $this->newPermDefinition = '';
        session()->flash('success', 'Permission created.');
    }

    public function render(PermissionsService $service)
    {
        $permissions = $service->permissions();
        $categories = $service->permissionsByCategory($permissions);
        $groups = $service->groups();

        $users = null;
        $userRange = ['from' => 0, 'to' => 0, 'total' => 0];
        if ($this->mode === 'user') {
            $users = $service->paginateUsers(
                $this->userSearch,
                $this->userGroupFilter,
                $this->perPage,
                $this->getPage()
            );
            $userRange = $this->tableRange($users);
        }

        $selectedUser = null;
        $groupPermCount = 0;
        if ($this->selectedUserId) {
            $selectedUser = \Illuminate\Support\Facades\DB::table('user as u')
                ->leftJoin('user_groups as ug', 'ug.id', '=', 'u.role')
                ->where('u.user_id', $this->selectedUserId)
                ->select('u.*', 'ug.group_name')
                ->first();
            if ($selectedUser && $selectedUser->role) {
                $groupPermCount = count($service->groupPermissionIds((int) $selectedUser->role));
            }
        }

        return view('permissions::livewire.permissions-index', [
            'groups' => $groups,
            'categories' => $categories,
            'users' => $users,
            'userFrom' => $userRange['from'],
            'userTo' => $userRange['to'],
            'userTotal' => $userRange['total'],
            'selectedUser' => $selectedUser,
            'groupPermCount' => $groupPermCount,
            'service' => $service,
        ]);
    }
}
