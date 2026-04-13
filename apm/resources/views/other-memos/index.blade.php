@extends('layouts.app')

@section('title', 'Other memos')

@section('header', 'Other memos')

@section('header-actions')
    <a href="{{ route('other-memos.create') }}" class="btn btn-success" wire:navigate>
        <i class="bx bx-plus me-1"></i>New memo
    </a>
@endsection

@section('content')
    @if (session('msg'))
        <div class="alert alert-{{ session('type', 'info') }}">{{ session('msg') }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted small me-2">Filter:</span>
            <a href="{{ route('other-memos.index') }}" class="btn btn-sm {{ request('filter') ? 'btn-outline-primary' : 'btn-primary' }}">My activity</a>
            <a href="{{ route('other-memos.index', ['filter' => 'mine']) }}" class="btn btn-sm {{ request('filter') === 'mine' ? 'btn-primary' : 'btn-outline-primary' }}">Created by me</a>
            <a href="{{ route('other-memos.index', ['filter' => 'pending_me']) }}" class="btn btn-sm {{ request('filter') === 'pending_me' ? 'btn-primary' : 'btn-outline-primary' }}">Pending my action</a>
            <a href="{{ route('other-memos.index', ['filter' => 'all']) }}" class="btn btn-sm {{ request('filter') === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Document #</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Creator</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($memos as $memo)
                            <tr>
                                <td>
                                    @if ($memo->document_number)
                                        <code>{{ $memo->document_number }}</code>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $memo->memo_type_name_snapshot }}</td>
                                <td>
                                    @if ($memo->overall_status === 'approved')
                                        <span class="badge bg-success">Approved</span>
                                    @elseif($memo->overall_status === 'pending')
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @elseif($memo->overall_status === 'returned')
                                        <span class="badge bg-secondary">Returned</span>
                                    @elseif($memo->overall_status === 'draft')
                                        <span class="badge bg-light text-dark">Draft</span>
                                    @else
                                        <span class="badge bg-dark">{{ $memo->overall_status }}</span>
                                    @endif
                                </td>
                                <td>{{ $memo->creator->fname ?? '' }} {{ $memo->creator->lname ?? '' }}</td>
                                <td class="text-nowrap small">{{ $memo->updated_at->format('M j, Y g:i a') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('other-memos.show', $memo) }}" class="btn btn-sm btn-outline-primary" wire:navigate>Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No memos in this view.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($memos->hasPages())
            <div class="card-footer">{{ $memos->links() }}</div>
        @endif
    </div>
@endsection
