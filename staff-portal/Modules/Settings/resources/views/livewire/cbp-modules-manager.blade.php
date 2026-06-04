<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-success fw-bold mb-0">CBP Modules</h4>
        <a href="{{ route('settings.hub') }}" class="btn btn-outline-secondary btn-sm">← Settings</a>
    </div>
    <div class="table-responsive card border-0 shadow-sm">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Order</th><th>System</th><th>Key</th><th>Enabled</th><th>Production</th></tr>
            </thead>
            <tbody>
                @foreach ($modules as $m)
                    <tr>
                        <td>{{ $m->sort_order }}</td>
                        <td>{{ $m->system_name }}</td>
                        <td><code>{{ $m->module_key }}</code></td>
                        <td>{{ $m->is_enabled ? 'Yes' : 'No' }}</td>
                        <td>{{ $m->is_production ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-muted small mt-2">Full CBP module editor (URLs, permissions) — use CI3 settings/cbp_modules for advanced edits until ported.</p>
</div>
