@php
    $currentPeriod = \Modules\Performance\Support\PerformancePeriod::currentSlug();
    $ppaEntryId = md5($staffId . '_' . str_replace([' ', '-'], '', $currentPeriod));
    $ppaExists = \Illuminate\Support\Facades\DB::table('ppa_entries')->where('entry_id', $ppaEntryId)->exists();
@endphp
<style>
    .nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 500; padding: 0.75rem 1.5rem; }
    .nav-tabs .nav-link:hover { color: rgba(52, 143, 65, 1); background-color: rgba(52, 143, 65, 0.1); }
    .nav-tabs .nav-link.active { color: rgba(52, 143, 65, 1); background-color: rgba(52, 143, 65, 0.1); border-bottom: 3px solid rgba(52, 143, 65, 1); }
</style>
<ul class="nav nav-tabs mb-3" role="tablist">
    @php
        $ppaWindowOpen = ($submissionWindows['ppa']['open'] ?? true);
    @endphp
    @if (portal_can(74) && ! $ppaExists && $ppaWindowOpen)
        <li class="nav-item">
            <a class="nav-link @if(request()->routeIs('performance.ppa.create')) active @endif" href="{{ route('performance.ppa.create') }}">
                <i class="fa-solid fa-circle-plus"></i> Create PPA
            </a>
        </li>
    @endif
    @if (portal_can(74) && $ppaExists)
        <li class="nav-item">
            <a class="nav-link @if(request()->routeIs('performance.ppa.form')) active @endif"
               href="{{ route('performance.ppa.form', ['entryId' => $ppaEntryId, 'staffId' => $staffId]) }}">
                <i class="fa-solid fa-file"></i> Current PPA
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if(request()->routeIs('performance.midterm.*')) active @endif"
               href="{{ route('performance.midterm.form', ['entryId' => $ppaEntryId, 'staffId' => $staffId]) }}">
                <i class="fa-solid fa-file"></i> Current Midterm
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if(request()->routeIs('performance.endterm.*')) active @endif"
               href="{{ route('performance.endterm.form', ['entryId' => $ppaEntryId, 'staffId' => $staffId]) }}">
                <i class="fa-solid fa-file"></i> Current Endterm
            </a>
        </li>
    @endif
    <li class="nav-item">
        <a class="nav-link @if(request()->routeIs('performance.my-ppas')) active @endif" href="{{ route('performance.my-ppas') }}">
            <i class="fa-solid fa-folder-open"></i> PPAs
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if(request()->routeIs('performance.pending')) active @endif" href="{{ route('performance.pending') }}">
            <i class="fa-solid fa-clock"></i> Pending Action
            @if (($pendingCount ?? 0) > 0)<span class="badge bg-danger">{{ $pendingCount }}</span>@endif
        </a>
    </li>
</ul>
