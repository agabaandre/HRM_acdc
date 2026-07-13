@extends('layouts.app')

@section('title', 'Stale Draft Memos')

@section('header', 'Stale Draft Memos')

@section('content')
<div class="container-fluid py-2">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <p class="text-muted mb-2 small">
                Draft memos with budget that have not been updated for
                <strong>{{ $draftMaxAgeMonths }}</strong> month(s) are stale and tie up fund code balances.
                @if($autoArchiveEnabled)
                    Unacted stale drafts are auto-archived <strong>{{ $weeklyRunLabel }}</strong>
                    (next run: <strong>{{ $nextWeeklyRun }}</strong>).
                @endif
            </p>
            <p class="text-muted mb-0 small">
                As owner, responsible person, division focal person, or Head of Division you can archive stale drafts below to release budget immediately.
            </p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bx bx-time-five me-1 text-warning"></i> Your stale drafts ({{ count($pendingStale) }})</h5>
        </div>
        <div class="card-body p-0">
            @if(empty($pendingStale))
                <p class="text-muted p-4 mb-0">No stale draft memos on your account or in your division.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Type</th>
                                <th>Title</th>
                                <th>Document</th>
                                <th>Last updated</th>
                                <th>Budget</th>
                                <th>Scheduled archive</th>
                                <th class="pe-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingStale as $item)
                                <tr>
                                    <td class="ps-3">{{ $item['type_label'] ?? $item['type'] }}</td>
                                    <td>{{ $item['title'] ?? 'Untitled' }}</td>
                                    <td>{{ $item['document_number'] ?? '—' }}</td>
                                    <td>{{ $item['updated_at'] ?? '' }}</td>
                                    <td>${{ number_format((float) ($item['budget_total'] ?? 0), 2) }}</td>
                                    <td>{{ $item['scheduled_archive_at'] ?? '—' }}</td>
                                    <td class="pe-3 text-end text-nowrap">
                                        @if(!empty($item['edit_url']))
                                            <a href="{{ $item['edit_url'] }}" class="btn btn-sm btn-outline-primary me-1" wire:navigate>Open</a>
                                        @endif
                                        @include('partials.stale-draft-archive-button', [
                                            'item' => $item,
                                            'redirect' => route('stale-drafts.index'),
                                        ])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
