<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="text-success fw-bold mb-1">Permissions management</h4>
            <p class="text-muted small mb-0">Assign access by user group or individual user (CI3 parity)</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#permNewGroupModal">+ Group</button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#permNewPermissionModal">+ Permission</button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <button type="button" class="nav-link @if($mode === 'group') active @endif" wire:click="$set('mode', 'group')">By group</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link @if($mode === 'user') active @endif" wire:click="$set('mode', 'user')">By user</button>
        </li>
    </ul>

    <div class="row g-3">
        <div class="col-lg-4">
            @if ($mode === 'group')
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white fw-semibold">User groups</div>
                    <div class="list-group list-group-flush" style="max-height: 420px; overflow-y: auto;">
                        @foreach ($groups as $g)
                            <button type="button"
                                    wire:key="grp-{{ $g->id }}"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center @if($selectedGroupId == $g->id) active @endif"
                                    wire:click="$set('selectedGroupId', {{ $g->id }})">
                                <span>{{ ucwords($g->group_name ?? '') }}</span>
                                <span class="badge @if($selectedGroupId == $g->id) bg-light text-dark @else bg-light text-muted @endif">
                                    {{ $service->groupUserCount((int) $g->id) }} users
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">Users</div>
                    <div class="card-body p-2 border-bottom">
                        <x-core::filter-search model="userSearch" label="Find user" placeholder="Name or ID…" col="col-12" />
                        <div class="mt-2">
                            <label class="form-label small mb-1">Group filter</label>
                            <select class="form-select form-select-sm" wire:model.live="userGroupFilter">
                                <option value="">All groups</option>
                                @foreach ($groups as $g)
                                    <option value="{{ $g->id }}">{{ $g->group_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mt-2">
                            <x-core::filter-per-page col="col-12" />
                        </div>
                    </div>
                    @if ($users)
                        <div class="small text-muted px-3 py-1">
                            Showing {{ $userFrom }}–{{ $userTo }} of {{ number_format($userTotal) }}
                        </div>
                        <div class="list-group list-group-flush" style="max-height: 360px; overflow-y: auto;">
                            @forelse ($users as $u)
                                <button type="button"
                                        class="list-group-item list-group-item-action @if($selectedUserId == $u->user_id) active @endif"
                                        wire:key="usr-{{ $u->user_id }}"
                                        wire:click="selectUser({{ $u->user_id }})">
                                    <div class="fw-semibold">{{ $u->name ?? 'User #'.$u->user_id }}</div>
                                    <small class="@if($selectedUserId == $u->user_id) text-white-50 @else text-muted @endif">
                                        {{ $u->group_name ?? 'No group' }} · {{ (int) $u->custom_permission_count }} custom
                                    </small>
                                </button>
                            @empty
                                <div class="text-muted small p-3">No users found</div>
                            @endforelse
                        </div>
                        @if ($users->hasPages())
                            <div class="p-2">{{ $users->links() }}</div>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold text-success">
                        @if ($mode === 'group')
                            @if ($selectedGroupId)
                                Group permissions
                            @else
                                Select a group
                            @endif
                        @else
                            @if ($selectedUser)
                                User: {{ $selectedUser->name }}
                                <span class="text-muted fw-normal small">({{ $selectedUser->group_name ?? 'no group' }} · {{ $groupPermCount }} from group)</span>
                            @else
                                Select a user
                            @endif
                        @endif
                    </span>
                    @if (($mode === 'group' && $selectedGroupId) || ($mode === 'user' && $selectedUserId))
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            @if ($mode === 'user' && $selectedUserId)
                                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="copyGroupPermissions">Copy from group</button>
                            @endif
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="enableAssignment" wire:model.live="assignmentEnabled">
                                <label class="form-check-label small" for="enableAssignment">Enable editing</label>
                            </div>
                            <button type="button" class="btn btn-success btn-sm" wire:click="saveAssignments" @disabled(! $assignmentEnabled)>Save</button>
                        </div>
                    @endif
                </div>
                <div class="card-body">
                    @if (($mode === 'group' && ! $selectedGroupId) || ($mode === 'user' && ! $selectedUserId))
                        <p class="text-muted text-center py-5 mb-0">
                            Choose {{ $mode === 'group' ? 'a group' : 'a user' }} on the left to view and assign permissions.
                        </p>
                    @else
                        <p class="small text-muted">
                            @if ($mode === 'group')
                                Permissions apply to all users in the selected group (via <code>group_permissions</code>).
                            @else
                                Custom permissions are stored in <code>user_permissions</code> and merged with the user's group on login.
                            @endif
                        </p>
                        <div class="row g-3">
                            @foreach ($categories as $category => $perms)
                                <div class="col-md-6">
                                    <div class="border rounded h-100">
                                        <div class="px-3 py-2 fw-semibold small text-white" style="background:#119A48;">{{ $category }}</div>
                                        <div class="p-3" style="max-height: 280px; overflow-y: auto;">
                                            @foreach ($perms as $perm)
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input"
                                                           type="checkbox"
                                                           id="perm_{{ $perm->id }}"
                                                           value="{{ $perm->id }}"
                                                           wire:model="selectedPermissionIds"
                                                           @disabled(! $assignmentEnabled)>
                                                    <label class="form-check-label small" for="perm_{{ $perm->id }}">
                                                        <span class="fw-medium">{{ ucwords(str_replace('_', ' ', $perm->name)) }}</span>
                                                        <span class="text-muted">[{{ $perm->id }}]</span>
                                                        <br><span class="text-muted">{{ $perm->definition }}</span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="permNewGroupModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit="createGroup">
                    <div class="modal-header">
                        <h5 class="modal-title">Create user group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" class="form-control" wire:model="newGroupName" placeholder="Group name" required minlength="3">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="permNewPermissionModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit="createPermission">
                    <div class="modal-header">
                        <h5 class="modal-title">Add permission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small">Name (letters and underscores)</label>
                            <input type="text" class="form-control" wire:model="newPermName" required>
                        </div>
                        <div>
                            <label class="form-label small">Description</label>
                            <input type="text" class="form-control" wire:model="newPermDefinition" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
