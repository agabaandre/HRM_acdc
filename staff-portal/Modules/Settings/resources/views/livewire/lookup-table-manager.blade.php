<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-success fw-bold mb-0">{{ $cfg['label'] ?? 'Lookup' }}</h4>
        <a href="{{ route('settings.hub') }}" class="btn btn-outline-secondary btn-sm">← Settings</a>
    </div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">{{ $editId ? 'Edit' : 'Add' }}</div>
                <div class="card-body">
                    <form wire:submit="save">
                        @foreach ($cfg['columns'] as $col => $meta)
                            <div class="mb-2">
                                <label class="form-label small">{{ $meta['label'] }}</label>
                                @if (($meta['type'] ?? '') === 'checkbox')
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" wire:model="form.{{ $col }}">
                                    </div>
                                @else
                                    <input type="{{ ($meta['type'] ?? '') === 'number' ? 'number' : 'text' }}"
                                           class="form-control form-control-sm" wire:model="form.{{ $col }}"
                                           @if (!empty($meta['required'])) required @endif>
                                @endif
                            </div>
                        @endforeach
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success btn-sm">Save</button>
                            @if ($editId)<button type="button" class="btn btn-outline-secondary btn-sm" wire:click="resetForm">Cancel</button>@endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <x-core::data-table :paginator="$rows" :from="$from" :to="$to" :total="$total" :compact="true">
                <x-slot:filters>
                    <div class="row g-2 align-items-end">
                        <x-core::filter-search label="Filter records" placeholder="Search columns…" col="col-md-6" />
                        <x-core::filter-per-page col="col-md-3" />
                    </div>
                </x-slot:filters>

                <x-slot:head>
                    <tr>
                        <th>#</th>
                        @foreach ($cfg['columns'] as $col => $meta)
                            <th>{{ $meta['label'] }}</th>
                        @endforeach
                        <th></th>
                    </tr>
                </x-slot:head>

                <x-slot:body>
                    @forelse ($rows as $index => $row)
                        <tr wire:key="lookup-{{ $table }}-{{ $row->{$cfg['pk']} }}">
                            <td>{{ $from + $index }}</td>
                            @foreach ($cfg['columns'] as $col => $meta)
                                <td>{{ $row->{$col} }}</td>
                            @endforeach
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $row->{$cfg['pk']} }})">Edit</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $row->{$cfg['pk']} }})" wire:confirm="Delete this record?">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="20" class="text-muted text-center py-3">No records</td></tr>
                    @endforelse
                </x-slot:body>
            </x-core::data-table>
        </div>
    </div>
</div>
