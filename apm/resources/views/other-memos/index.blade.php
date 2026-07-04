@extends('layouts.app')

@section('title', 'Other memos')

@section('header', 'Other memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('other-memos.create') }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Create New Memo
    </a>
</div>
@endsection

@section('content')
<style>
.table-responsive {
    font-size: 0.875rem;
}
.table th, .table td {
    padding: 0.5rem 0.25rem;
    vertical-align: middle;
}
.table th {
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}
.text-wrap {
    word-wrap: break-word;
    word-break: break-word;
}
.badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}
.btn-group .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
#otherMemoFilters select.other-memo-filter-select.select2-hidden-accessible {
    position: absolute !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important;
}
</style>

@php
    $activeTab = request('tab', 'mySubmitted');
    if (! in_array($activeTab, ['mySubmitted', 'myDivision', 'allMemos'], true)) {
        $activeTab = 'mySubmitted';
    }
@endphp

<div data-apm-livewire-page="other-memos-index">

@if (session('msg'))
    <div class="alert alert-{{ session('type', 'info') }}">{{ session('msg') }}</div>
@endif

<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-blank me-2 text-success"></i> Other Memo Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="otherMemoFilters" autocomplete="off">
            <input type="hidden" name="tab" id="filter_tab" value="{{ $activeTab }}">
            @include('partials.apm-memo-list-filters', [
                'filterId' => 'otherMemoFilters',
                'resetUrl' => route('other-memos.index'),
                'showFundType' => false,
                'statusDomId' => 'memo_status',
                'searchLabel' => 'Search title',
                'searchPlaceholder' => 'Title or type…',
                'staff' => $staff,
                'divisions' => $divisions,
                'years' => $years,
                'selectedYear' => $year ?? date('Y'),
            ])
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <ul class="nav nav-tabs nav-fill" id="otherMemoTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link @if($activeTab === 'mySubmitted') active @endif" id="otherMySubmitted-tab" data-bs-toggle="tab" data-bs-target="#otherMySubmitted" type="button" role="tab" aria-controls="otherMySubmitted" aria-selected="{{ $activeTab === 'mySubmitted' ? 'true' : 'false' }}">
                    <i class="bx bx-file-alt me-2"></i> My Submitted Memos
                    <span class="badge bg-success text-white ms-2" id="badge-other-mySubmitted">{{ $mySubmittedMemos->total() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link @if($activeTab === 'myDivision') active @endif" id="otherMyDivision-tab" data-bs-toggle="tab" data-bs-target="#otherMyDivision" type="button" role="tab" aria-controls="otherMyDivision" aria-selected="{{ $activeTab === 'myDivision' ? 'true' : 'false' }}">
                    <i class="bx bx-building me-2"></i> My Division Memos
                    <span class="badge bg-info text-white ms-2" id="badge-other-myDivision">{{ $myDivisionMemos->total() }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($activeTab === 'allMemos') active @endif" id="otherAllMemos-tab" data-bs-toggle="tab" data-bs-target="#otherAllMemos" type="button" role="tab" aria-controls="otherAllMemos" aria-selected="{{ $activeTab === 'allMemos' ? 'true' : 'false' }}">
                        <i class="bx bx-grid me-2"></i> All Other Memos
                        <span class="badge bg-primary text-white ms-2" id="badge-other-allMemos">{{ $allMemos instanceof \Illuminate\Pagination\LengthAwarePaginator ? $allMemos->total() : $allMemos->count() }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <div class="tab-content" id="otherMemoTabsContent">
            <div class="tab-pane fade @if($activeTab === 'mySubmitted') show active @endif" id="otherMySubmitted" role="tabpanel" aria-labelledby="otherMySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-alt me-2"></i> My Submitted Memos
                            </h6>
                            <small class="text-muted">Other memos you have created</small>
                        </div>
                    </div>
                    @include('other-memos.partials.my-submitted-tab')
                </div>
            </div>
            <div class="tab-pane fade @if($activeTab === 'myDivision') show active @endif" id="otherMyDivision" role="tabpanel" aria-labelledby="otherMyDivision-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-info fw-bold">
                                <i class="bx bx-building me-2"></i> My Division Memos
                            </h6>
                            <small class="text-muted">Other memos in your division (latest first)</small>
                        </div>
                    </div>
                    @include('other-memos.partials.my-division-tab')
                </div>
            </div>
            @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade @if($activeTab === 'allMemos') show active @endif" id="otherAllMemos" role="tabpanel" aria-labelledby="otherAllMemos-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-primary fw-bold">
                                    <i class="bx bx-grid me-2"></i> All Other Memos
                                </h6>
                                <small class="text-muted">All other memos in the system</small>
                            </div>
                        </div>
                        @include('other-memos.partials.all-memos-tab')
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

</div>
{{-- Index behaviour: public/js/apm-other-memo-index-livewire.js (livewire:navigated + AbortController) --}}
@endsection
