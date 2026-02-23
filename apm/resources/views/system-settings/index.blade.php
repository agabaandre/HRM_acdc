@extends('layouts.app')

@section('title', 'App Settings')

@section('header', 'App Settings')

@section('content')
@if(session('msg'))
    <div class="alert alert-{{ session('type', 'info') }} alert-dismissible fade show" role="alert">
        {{ session('msg') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form action="{{ route('system-settings.update') }}" method="POST" class="mb-4">
    @csrf
    @method('PUT')

    @php
        $branding = $settings['branding'] ?? [];
        $app = $settings['app'] ?? [];
        $locale = $settings['locale'] ?? [];
        $ui = $settings['ui'] ?? [];
        $brandingFixed = ['primary_color', 'secondary_color', 'primary_color_dark', 'logo', 'logo_dark', 'favicon'];
        $appFixed = ['application_name', 'application_short_name', 'tagline', 'support_email', 'footer_text'];
        $localeFixed = ['default_currency', 'currency_symbol', 'timezone', 'date_format', 'date_time_format', 'locale'];
        $uiFixed = ['items_per_page', 'pagination_size', 'maintenance_mode'];
        $brandingCustom = array_diff_key($branding, array_flip($brandingFixed));
        $appCustom = array_diff_key($app, array_flip($appFixed));
        $localeCustom = array_diff_key($locale, array_flip($localeFixed));
        $uiCustom = array_diff_key($ui, array_flip($uiFixed));
    @endphp

    {{-- Branding --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-palette me-2 text-primary"></i>Branding</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Primary colour</label>
                    <div class="input-group">
                        <input type="color" class="form-control form-control-color w-auto color-picker" data-target="primary_color" value="{{ $branding['primary_color'] ?? '#119a48' }}" title="Primary colour">
                        <input type="text" name="primary_color" id="primary_color" class="form-control" value="{{ $branding['primary_color'] ?? '#119a48' }}" maxlength="7">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="primary_color" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Secondary colour</label>
                    <div class="input-group">
                        <input type="color" class="form-control form-control-color w-auto color-picker" data-target="secondary_color" value="{{ $branding['secondary_color'] ?? '#1bb85a' }}" title="Secondary colour">
                        <input type="text" name="secondary_color" id="secondary_color" class="form-control" value="{{ $branding['secondary_color'] ?? '#1bb85a' }}" maxlength="7">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="secondary_color" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Primary dark</label>
                    <div class="input-group">
                        <input type="color" class="form-control form-control-color w-auto color-picker" data-target="primary_color_dark" value="{{ $branding['primary_color_dark'] ?? '#0d7a3a' }}" title="Primary dark">
                        <input type="text" name="primary_color_dark" id="primary_color_dark" class="form-control" value="{{ $branding['primary_color_dark'] ?? '#0d7a3a' }}" maxlength="7">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="primary_color_dark" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo path</label>
                    <div class="input-group">
                        <input type="text" name="logo" class="form-control" value="{{ $branding['logo'] ?? '' }}" placeholder="/assets/images/logo.png">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="logo" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo (dark) path</label>
                    <div class="input-group">
                        <input type="text" name="logo_dark" class="form-control" value="{{ $branding['logo_dark'] ?? '' }}" placeholder="Optional">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="logo_dark" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Favicon path</label>
                    <div class="input-group">
                        <input type="text" name="favicon" class="form-control" value="{{ $branding['favicon'] ?? '' }}" placeholder="/favicon.ico">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="favicon" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
            @if(count($brandingCustom) > 0)
            <hr class="my-3">
            <h6 class="text-muted mb-2">Other branding settings</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Key</th><th>Value</th><th class="text-end" style="width: 100px;">Remove</th></tr></thead>
                    <tbody>
                        @foreach($brandingCustom as $k => $v)
                        <tr>
                            <td class="align-middle"><code>{{ $k }}</code></td>
                            <td class="align-middle"><input type="text" name="{{ $k }}" class="form-control form-control-sm" value="{{ $v }}" placeholder="Value"></td>
                            <td class="align-middle text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm system-setting-delete" title="Remove setting" data-key="{{ $k }}" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- App --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-cog me-2 text-primary"></i>Application</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Application name</label>
                    <div class="input-group">
                        <input type="text" name="application_name" class="form-control" value="{{ $app['application_name'] ?? '' }}" placeholder="Africa CDC APM">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="application_name" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Short name</label>
                    <div class="input-group">
                        <input type="text" name="application_short_name" class="form-control" value="{{ $app['application_short_name'] ?? '' }}" placeholder="APM">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="application_short_name" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Tagline</label>
                    <div class="input-group">
                        <input type="text" name="tagline" class="form-control" value="{{ $app['tagline'] ?? '' }}" placeholder="Approval & Programme Management">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="tagline" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Support email</label>
                    <div class="input-group">
                        <input type="email" name="support_email" class="form-control" value="{{ $app['support_email'] ?? '' }}" placeholder="support@africacdc.org">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="support_email" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Footer text</label>
                    <div class="input-group">
                        <input type="text" name="footer_text" class="form-control" value="{{ $app['footer_text'] ?? '' }}" placeholder="© {{ date('Y') }} Africa CDC. All rights reserved.">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="footer_text" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
            @if(count($appCustom) > 0)
            <hr class="my-3">
            <h6 class="text-muted mb-2">Other application settings</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Key</th><th>Value</th><th class="text-end" style="width: 100px;">Remove</th></tr></thead>
                    <tbody>
                        @foreach($appCustom as $k => $v)
                        <tr>
                            <td class="align-middle"><code>{{ $k }}</code></td>
                            <td class="align-middle"><input type="text" name="{{ $k }}" class="form-control form-control-sm" value="{{ $v }}" placeholder="Value"></td>
                            <td class="align-middle text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm system-setting-delete" title="Remove setting" data-key="{{ $k }}" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Locale --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-globe me-2 text-primary"></i>Locale & format</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Default currency</label>
                    <div class="input-group">
                        <input type="text" name="default_currency" class="form-control" value="{{ $locale['default_currency'] ?? 'USD' }}" placeholder="USD" maxlength="10">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="default_currency" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Currency symbol</label>
                    <div class="input-group">
                        <input type="text" name="currency_symbol" class="form-control" value="{{ $locale['currency_symbol'] ?? '$' }}" placeholder="$" maxlength="5">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="currency_symbol" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Timezone</label>
                    <div class="input-group">
                        <input type="text" name="timezone" class="form-control" value="{{ $locale['timezone'] ?? 'Africa/Nairobi' }}" placeholder="Africa/Nairobi">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="timezone" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date format</label>
                    <div class="input-group">
                        <input type="text" name="date_format" class="form-control" value="{{ $locale['date_format'] ?? 'd M Y' }}" placeholder="d M Y">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="date_format" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date/time format</label>
                    <div class="input-group">
                        <input type="text" name="date_time_format" class="form-control" value="{{ $locale['date_time_format'] ?? 'd M Y H:i' }}" placeholder="d M Y H:i">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="date_time_format" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Locale</label>
                    <div class="input-group">
                        <input type="text" name="locale" class="form-control" value="{{ $locale['locale'] ?? 'en' }}" placeholder="en" maxlength="10">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="locale" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
            @if(count($localeCustom) > 0)
            <hr class="my-3">
            <h6 class="text-muted mb-2">Other locale settings</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Key</th><th>Value</th><th class="text-end" style="width: 100px;">Remove</th></tr></thead>
                    <tbody>
                        @foreach($localeCustom as $k => $v)
                        <tr>
                            <td class="align-middle"><code>{{ $k }}</code></td>
                            <td class="align-middle"><input type="text" name="{{ $k }}" class="form-control form-control-sm" value="{{ $v }}" placeholder="Value"></td>
                            <td class="align-middle text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm system-setting-delete" title="Remove setting" data-key="{{ $k }}" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- UI --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-desktop me-2 text-primary"></i>UI</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Items per page</label>
                    <div class="input-group">
                        <input type="number" name="items_per_page" class="form-control" value="{{ $ui['items_per_page'] ?? '15' }}" min="5" max="100" step="1">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="items_per_page" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pagination size</label>
                    <div class="input-group">
                        <input type="number" name="pagination_size" class="form-control" value="{{ $ui['pagination_size'] ?? '10' }}" min="5" max="50" step="1">
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="pagination_size" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Maintenance mode</label>
                    <div class="input-group">
                        <select name="maintenance_mode" class="form-select">
                            <option value="0" {{ ($ui['maintenance_mode'] ?? '0') == '0' ? 'selected' : '' }}>Off</option>
                            <option value="1" {{ ($ui['maintenance_mode'] ?? '0') == '1' ? 'selected' : '' }}>On</option>
                        </select>
                        <button type="button" class="btn btn-outline-danger system-setting-delete" title="Remove setting" data-key="maintenance_mode" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
            @if(count($uiCustom) > 0)
            <hr class="my-3">
            <h6 class="text-muted mb-2">Other UI settings</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Key</th><th>Value</th><th class="text-end" style="width: 100px;">Remove</th></tr></thead>
                    <tbody>
                        @foreach($uiCustom as $k => $v)
                        <tr>
                            <td class="align-middle"><code>{{ $k }}</code></td>
                            <td class="align-middle"><input type="text" name="{{ $k }}" class="form-control form-control-sm" value="{{ $v }}" placeholder="Value"></td>
                            <td class="align-middle text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm system-setting-delete" title="Remove setting" data-key="{{ $k }}" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    @php $general = $settings['general'] ?? []; @endphp
    @if(count($general) > 0)
    {{-- Other / custom settings (editable in main form, removable via delete) --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Other settings</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Key</th><th>Value</th><th class="text-end" style="width: 100px;">Remove</th></tr>
                    </thead>
                    <tbody>
                        @foreach($general as $k => $v)
                        <tr>
                            <td class="align-middle"><code>{{ $k }}</code></td>
                            <td class="align-middle">
                                <input type="text" name="{{ $k }}" class="form-control form-control-sm" value="{{ $v }}" placeholder="Value">
                            </td>
                            <td class="align-middle text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm system-setting-delete" title="Remove setting" data-key="{{ $k }}" data-url="{{ route('system-settings.destroy') }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('system-settings.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save settings
        </button>
    </div>
</form>

{{-- Add new setting (separate form) --}}
<div class="card shadow-sm mb-4 border-primary">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-plus me-2 text-primary"></i>Add new setting</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('system-settings.store') }}" method="POST" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Key</label>
                <input type="text" name="key" class="form-control {{ $errors->has('key') ? 'is-invalid' : '' }}" value="{{ old('key') }}" placeholder="e.g. custom_feature_flag" pattern="[a-zA-Z0-9_.\-]+" maxlength="100" title="Letters, numbers, underscores, dots, hyphens only">
                @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Value</label>
                <input type="text" name="value" class="form-control" value="{{ old('value') }}" placeholder="Optional value">
            </div>
            <div class="col-md-3">
                <label class="form-label">Group</label>
                <select name="group" class="form-select">
                    <option value="general" {{ old('group', 'general') === 'general' ? 'selected' : '' }}>General</option>
                    <option value="branding" {{ old('group') === 'branding' ? 'selected' : '' }}>Branding</option>
                    <option value="app" {{ old('group') === 'app' ? 'selected' : '' }}>Application</option>
                    <option value="locale" {{ old('group') === 'locale' ? 'selected' : '' }}>Locale</option>
                    <option value="ui" {{ old('group') === 'ui' ? 'selected' : '' }}>UI</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-plus me-1"></i> Add
                </button>
            </div>
        </form>
        <p class="text-muted small mb-0 mt-2">Key may only contain letters, numbers, underscores, dots and hyphens. Use group <strong>General</strong> to edit or remove the setting from this page; other groups store the key for API/organization.</p>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.color-picker').forEach(function(colorInput) {
    var id = colorInput.getAttribute('data-target');
    var textInput = document.getElementById(id);
    if (!textInput) return;
    colorInput.addEventListener('input', function() { textInput.value = this.value; });
    textInput.addEventListener('input', function() { if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) colorInput.value = this.value; });
});

document.querySelectorAll('.system-setting-delete').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (!confirm('Remove this setting?')) return;
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = this.getAttribute('data-url');
        form.style.display = 'none';
        var method = document.createElement('input');
        method.name = '_method';
        method.value = 'DELETE';
        form.appendChild(method);
        var csrf = document.createElement('input');
        csrf.name = '_token';
        csrf.value = this.getAttribute('data-csrf');
        form.appendChild(csrf);
        var key = document.createElement('input');
        key.name = 'key';
        key.value = this.getAttribute('data-key');
        form.appendChild(key);
        document.body.appendChild(form);
        form.submit();
    });
});
</script>
@endpush
@endsection
