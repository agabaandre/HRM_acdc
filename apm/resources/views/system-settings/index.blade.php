@extends('layouts.app')

@section('title', 'App Settings')

@section('header', 'App Settings')

@section('content')
<div id="settings-alert" class="alert alert-dismissible fade show d-none" role="alert">
    <span id="settings-alert-msg"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@if(session('msg'))
    <div class="alert alert-{{ session('type', 'info') }} alert-dismissible fade show" role="alert">
        {{ session('msg') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Add new setting (AJAX submit, append row on success) - theme green, not blue --}}
<div class="card shadow-sm mb-4 border-2 system-settings-theme-card">
    <div class="card-header system-settings-theme-header py-3">
        <h5 class="mb-0"><i class="fas fa-plus me-2 system-settings-theme-icon"></i>Add new setting</h5>
    </div>
    <div class="card-body">
        <form id="system-settings-add-form" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label fw-semibold">Key</label>
                <input type="text" name="key" class="form-control form-control-sm" placeholder="e.g. API_SECRET" pattern="[a-zA-Z0-9_.\-]+" maxlength="100">
                <div class="invalid-feedback" data-invalid="key"></div>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Field type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="text">Text</option>
                    <option value="password">Password (hidden)</option>
                    <option value="number">Number</option>
                    <option value="boolean">Yes/No</option>
                    <option value="color">Color</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Value</label>
                <input type="text" name="value" class="form-control form-control-sm" placeholder="Optional">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Group</label>
                <select name="group" class="form-select form-select-sm">
                    <option value="general">Other</option>
                    <option value="branding">Branding</option>
                    <option value="app">Application</option>
                    <option value="locale">Locale</option>
                    <option value="ui">UI</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm w-100 system-settings-add-btn" id="add-setting-btn"><i class="fas fa-plus me-1"></i> Add</button>
            </div>
        </form>
        <p class="text-muted small mb-0 mt-2">Use <strong>Password (hidden)</strong> for secrets. Add multiple settings by filling the form and clicking Add again; each is appended to the table.</p>
    </div>
</div>

<form id="system-settings-update-form" action="{{ route('system-settings.update') }}" method="POST">
    @csrf
    @method('PUT')

    @php
        $groupIcons = ['branding' => 'fa-palette', 'app' => 'fa-cog', 'locale' => 'fa-globe', 'ui' => 'fa-desktop', 'general' => 'fa-list'];
        $typeLabels = ['text' => 'Text', 'password' => 'Password', 'number' => 'Number', 'boolean' => 'Yes/No', 'color' => 'Color'];
        $typeClass = ['text' => 'secondary', 'password' => 'warning', 'number' => 'info', 'boolean' => 'primary', 'color' => 'success'];
    @endphp
    @foreach($groups as $groupKey => $groupData)
    @php
        $groupLabel = $groupData['label'];
        $items = $groupData['items'];
        $icon = $groupIcons[$groupKey] ?? 'fa-sliders-h';
    @endphp
    <div class="card shadow-sm mb-4 settings-group-card" id="settings-group-{{ $groupKey }}" data-group="{{ $groupKey }}">
        <div class="card-header bg-light py-2 px-3">
            <h6 class="mb-0"><i class="fas {{ $icon }} me-2 system-settings-theme-icon"></i>{{ $groupLabel }}</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 settings-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 18%;">Key</th>
                            <th style="width: 11%;">Type</th>
                            <th class="setting-value-th">Value</th>
                            <th class="pe-3 text-end" style="width: 90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-group="{{ $groupKey }}">
                        @forelse($items as $item)
                        <tr data-key="{{ $item['key'] }}">
                            <td class="ps-3"><code class="text-dark">{{ $item['key'] }}</code></td>
                            <td>
                                @php $t = $item['type'] ?? 'text'; @endphp
                                <span class="badge bg-{{ $typeClass[$t] ?? 'secondary' }}">{{ $typeLabels[$t] ?? $t }}</span>
                            </td>
                            <td class="pe-2 setting-value-cell">
                                @if(($item['type'] ?? 'text') === 'password')
                                    <input type="password" name="{{ $item['key'] }}" class="form-control form-control-sm" placeholder="•••••••• (leave blank to keep)" autocomplete="new-password">
                                @elseif(($item['type'] ?? 'text') === 'color')
                                    <div class="input-group input-group-sm">
                                        <input type="color" class="form-control form-control-color w-auto color-picker-sm" data-target="val_{{ $item['key'] }}" value="{{ $item['value'] ?? '#119a48' }}" title="Color">
                                        <input type="text" name="{{ $item['key'] }}" id="val_{{ $item['key'] }}" class="form-control" value="{{ $item['value'] ?? '#119a48' }}" maxlength="7">
                                    </div>
                                @elseif(($item['type'] ?? 'text') === 'boolean')
                                    <select name="{{ $item['key'] }}" class="form-select form-select-sm" style="max-width: 120px;">
                                        <option value="0" {{ ($item['value'] ?? '') == '0' ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ ($item['value'] ?? '') == '1' ? 'selected' : '' }}>Yes</option>
                                    </select>
                                @elseif(($item['type'] ?? 'text') === 'number')
                                    <input type="number" name="{{ $item['key'] }}" class="form-control form-control-sm" value="{{ $item['value'] ?? '' }}" placeholder="—" style="max-width: 120px;">
                                @else
                                    <input type="text" name="{{ $item['key'] }}" class="form-control form-control-sm" value="{{ $item['value'] ?? '' }}" placeholder="—">
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm system-setting-delete" title="Remove setting" data-key="{{ $item['key'] }}"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                        @empty
                        <tr class="settings-empty-row"><td colspan="4" class="text-muted text-center py-3">No settings in this group</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('system-settings.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn system-settings-save-btn" id="save-settings-btn"><i class="fas fa-save me-1"></i> Save settings</button>
    </div>
</form>

<script>
window.SystemSettings = {
    destroyUrl: "{{ route('system-settings.destroy') }}",
    storeUrl: "{{ route('system-settings.store') }}",
    updateUrl: "{{ route('system-settings.update') }}",
    csrf: "{{ csrf_token() }}",
    typeLabels: { text: 'Text', password: 'Password', number: 'Number', boolean: 'Yes/No', color: 'Color' },
    typeClass: { text: 'secondary', password: 'warning', number: 'info', boolean: 'primary', color: 'success' }
};
</script>
@push('scripts')
<script>
(function() {
    var C = window.SystemSettings;
    if (!C) return;

    function showAlert(msg, type) {
        type = type || 'success';
        var el = document.getElementById('settings-alert');
        var msgEl = document.getElementById('settings-alert-msg');
        if (!el || !msgEl) return;
        el.className = 'alert alert-' + type + ' alert-dismissible fade show';
        msgEl.textContent = msg;
        el.classList.remove('d-none');
    }

    function buildValueCell(key, type, value) {
        value = value || '';
        if (type === 'password') {
            return '<input type="password" name="' + escapeHtml(key) + '" class="form-control form-control-sm" placeholder="•••••••• (leave blank to keep)" autocomplete="new-password">';
        }
        if (type === 'color') {
            var v = value && /^#[0-9A-Fa-f]{6}$/.test(value) ? value : '#119a48';
            return '<div class="input-group input-group-sm">' +
                '<input type="color" class="form-control form-control-color w-auto color-picker-sm" data-target="val_' + escapeHtml(key) + '" value="' + v + '" title="Color">' +
                '<input type="text" name="' + escapeHtml(key) + '" id="val_' + escapeHtml(key) + '" class="form-control" value="' + v + '" maxlength="7">' +
                '</div>';
        }
        if (type === 'boolean') {
            var sel = (value === '1' || value === 1) ? ' selected' : '';
            return '<select name="' + escapeHtml(key) + '" class="form-select form-select-sm" style="max-width:120px">' +
                '<option value="0">No</option><option value="1"' + sel + '>Yes</option></select>';
        }
        if (type === 'number') {
            return '<input type="number" name="' + escapeHtml(key) + '" class="form-control form-control-sm" value="' + escapeHtml(value) + '" placeholder="—" style="max-width:120px">';
        }
        return '<input type="text" name="' + escapeHtml(key) + '" class="form-control form-control-sm" value="' + escapeHtml(value) + '" placeholder="—">';
    }

    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function buildRow(setting) {
        var key = setting.key;
        var type = setting.type || 'text';
        var value = setting.value;
        var typeLabel = C.typeLabels[type] || type;
        var typeCls = C.typeClass[type] || 'secondary';
        var valueCell = buildValueCell(key, type, value);
        var tr = document.createElement('tr');
        tr.setAttribute('data-key', key);
        tr.innerHTML = '<td class="ps-3"><code class="text-dark">' + escapeHtml(key) + '</code></td>' +
            '<td><span class="badge bg-' + typeCls + '">' + escapeHtml(typeLabel) + '</span></td>' +
            '<td class="pe-2 setting-value-cell">' + valueCell + '</td>' +
            '<td class="pe-3 text-end"><button type="button" class="btn btn-outline-danger btn-sm system-setting-delete" title="Remove setting" data-key="' + escapeHtml(key) + '"><i class="fas fa-trash-alt"></i></button></td>';
        return tr;
    }

    function bindColorPickers(container) {
        if (!container) return;
        container.querySelectorAll('.color-picker-sm').forEach(function(colorInput) {
            var id = colorInput.getAttribute('data-target');
            var textInput = document.getElementById(id);
            if (!textInput) return;
            colorInput.addEventListener('input', function() { textInput.value = this.value; });
            textInput.addEventListener('input', function() { if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) colorInput.value = this.value; });
        });
    }

    // Add new setting (AJAX, append row)
    var addForm = document.getElementById('system-settings-add-form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('add-setting-btn');
            if (btn) btn.disabled = true;
            var fd = new FormData(addForm);
            fd.append('_token', C.csrf);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', C.storeUrl);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.onload = function() {
                if (btn) btn.disabled = false;
                var data;
                try { data = JSON.parse(xhr.responseText); } catch (err) { data = {}; }
                if (xhr.status >= 200 && xhr.status < 300 && data.success && data.setting) {
                    var group = data.setting.group;
                    var tbody = document.querySelector('tbody[data-group="' + group + '"]');
                    if (tbody) {
                        var emptyRow = tbody.querySelector('.settings-empty-row');
                        if (emptyRow) emptyRow.remove();
                        var newTr = buildRow(data.setting);
                        tbody.appendChild(newTr);
                        bindColorPickers(newTr);
                    }
                    showAlert(data.message || 'Setting added.');
                    addForm.reset();
                    if (data.errors) {
                        Object.keys(data.errors).forEach(function(k) {
                            var inp = addForm.querySelector('[name="' + k + '"]');
                            if (inp) { inp.classList.add('is-invalid'); }
                        });
                    }
                } else {
                    var msg = (data.message || data.errors) ? (data.message || (data.errors && data.errors.key && data.errors.key[0])) : 'Failed to add setting.';
                    showAlert(msg || 'Failed to add setting.', 'danger');
                    if (data.errors && data.errors.key) {
                        var keyInp = addForm.querySelector('[name="key"]');
                        if (keyInp) { keyInp.classList.add('is-invalid'); keyInp.nextElementSibling && (keyInp.nextElementSibling.textContent = data.errors.key[0]); }
                    }
                }
            };
            xhr.onerror = function() { if (btn) btn.disabled = false; showAlert('Network error.', 'danger'); };
            xhr.send(fd);
        });
    }

    // Save all (AJAX)
    var updateForm = document.getElementById('system-settings-update-form');
    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('save-settings-btn');
            if (btn) btn.disabled = true;
            var fd = new FormData(updateForm);
            fd.set('_method', 'PUT');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', C.updateUrl);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', C.csrf);
            xhr.onload = function() {
                if (btn) btn.disabled = false;
                var data;
                try { data = JSON.parse(xhr.responseText); } catch (err) { data = {}; }
                if (xhr.status >= 200 && xhr.status < 300 && data.success) {
                    showAlert(data.message || 'Settings updated.');
                } else {
                    showAlert(data.message || 'Update failed.', 'danger');
                }
            };
            xhr.onerror = function() { if (btn) btn.disabled = false; showAlert('Network error.', 'danger'); };
            xhr.send(fd);
        });
    }

    // Delete (AJAX, remove row)
    document.body.addEventListener('click', function(e) {
        var btn = e.target.closest('.system-setting-delete');
        if (!btn) return;
        e.preventDefault();
        var key = btn.getAttribute('data-key');
        if (!key || !confirm('Remove this setting permanently?')) return;
        var row = btn.closest('tr');
        var fd = new FormData();
        fd.append('_token', C.csrf);
        fd.append('_method', 'DELETE');
        fd.append('key', key);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', C.destroyUrl);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', C.csrf);
        xhr.onload = function() {
            var data;
            try { data = JSON.parse(xhr.responseText); } catch (err) { data = {}; }
            if (xhr.status >= 200 && xhr.status < 300 && data.success && row && row.parentNode) {
                row.remove();
                var tbody = row.parentNode;
                if (tbody && tbody.querySelectorAll('tr[data-key]').length === 0) {
                    var empty = tbody.querySelector('.settings-empty-row');
                    if (empty) empty.classList.remove('d-none');
                    else {
                        var tr = document.createElement('tr');
                        tr.className = 'settings-empty-row';
                        tr.innerHTML = '<td colspan="4" class="text-muted text-center py-3">No settings in this group</td>';
                        tbody.appendChild(tr);
                    }
                }
                showAlert(data.message || 'Setting removed.');
            } else {
                showAlert(data.message || 'Delete failed.', 'danger');
            }
        };
        xhr.onerror = function() { showAlert('Network error.', 'danger'); };
        xhr.send(fd);
    });

    // Color pickers (initial)
    document.querySelectorAll('.color-picker-sm').forEach(function(colorInput) {
        var id = colorInput.getAttribute('data-target');
        var textInput = document.getElementById(id);
        if (!textInput) return;
        colorInput.addEventListener('input', function() { textInput.value = this.value; });
        textInput.addEventListener('input', function() { if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) colorInput.value = this.value; });
    });
})();
</script>
@endpush

<style>
/* System Settings page: use theme green instead of Bootstrap blue */
.system-settings-theme-card { border-color: var(--primary-color, #119a48) !important; }
.system-settings-theme-header { background-color: rgba(17, 154, 72, 0.12) !important; }
.system-settings-theme-icon { color: var(--primary-color, #119a48) !important; }
.system-settings-add-btn,
.system-settings-save-btn {
    background-color: var(--primary-color, #119a48) !important;
    border-color: var(--primary-color, #119a48) !important;
    color: #fff !important;
}
.system-settings-add-btn:hover,
.system-settings-save-btn:hover {
    background-color: var(--primary-dark, #0d7a3a) !important;
    border-color: var(--primary-dark, #0d7a3a) !important;
    color: #fff !important;
}
.settings-table th { font-size: 0.8rem; font-weight: 600; }
.settings-table th.setting-value-th { min-width: 220px; }
.settings-table td { font-size: 0.9rem; vertical-align: middle !important; }
.settings-table td.setting-value-cell { min-width: 220px; overflow: visible; }
.settings-table .form-control-sm, .settings-table .form-select-sm { min-height: 31px; }
.settings-table .setting-value-cell .form-control,
.settings-table .setting-value-cell .form-select { width: 100%; min-width: 0; max-width: 100%; }
.settings-table .setting-value-cell .input-group { min-width: 0; }
.form-control-color { height: 31px; padding: 2px; cursor: pointer; }
</style>
@endsection
