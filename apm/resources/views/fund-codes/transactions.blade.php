@extends('layouts.app')

@section('title', 'Fund Code Budget & Transactions')

@section('header')
    Fund Code: {{ $fundCode->code }}
    @if($fundCode->name)
        <small class="text-muted fw-normal">— {{ $fundCode->name }}</small>
    @endif
@endsection

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('fund-codes.show', $fundCode) }}" class="btn btn-outline-info">
        <i class="bx bx-show"></i> View Fund Code
    </a>
    <a wire:navigate href="{{ route('fund-codes.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
</div>
@endsection

@section('content')
@php
    $snap = $ledger['snapshot'] ?? ['approved_budget' => 0, 'committed_total' => 0, 'working_balance' => 0];
    $settings = $ledger['settings'] ?? [];
    $committed = $ledger['committed'] ?? [];
    $skipped = $ledger['skipped'] ?? [];
    $statusBadge = fn (string $status) => match ($status) {
        'approved', 'passed' => 'bg-success',
        'draft' => 'bg-secondary',
        'pending', 'submitted' => 'bg-warning text-dark',
        'returned', 'rejected' => 'bg-danger',
        'archived', 'cancelled', 'onhold' => 'bg-dark',
        default => 'bg-info text-dark',
    };
@endphp

{{-- Working balance summary --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Approved Budget</div>
                <div class="fs-4 fw-bold text-primary">${{ number_format($snap['approved_budget'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Committed</div>
                <div class="fs-4 fw-bold text-danger">− ${{ number_format($snap['committed_total'], 2) }}</div>
                <div class="small text-muted">{{ count($committed) }} memo(s)</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-success">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Working Balance</div>
                <div class="fs-4 fw-bold text-success">${{ number_format($snap['working_balance'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-light">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Legacy Ledger Balance</div>
                <div class="fs-5 fw-semibold">${{ number_format((float) ($fundCode->budget_balance ?? 0), 2) }}</div>
                <div class="small text-muted">DB field (may differ)</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0"><i class="bx bx-calculator me-2 text-success"></i>Balance Calculation</h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 fs-5 mb-3">
            <span class="badge bg-primary fs-6 px-3 py-2">${{ number_format($snap['approved_budget'], 2) }}</span>
            <span class="text-muted">Approved</span>
            <span class="fw-bold text-muted">−</span>
            <span class="badge bg-danger fs-6 px-3 py-2">${{ number_format($snap['committed_total'], 2) }}</span>
            <span class="text-muted">Committed</span>
            <span class="fw-bold text-muted">=</span>
            <span class="badge bg-success fs-6 px-3 py-2">${{ number_format($snap['working_balance'], 2) }}</span>
            <span class="text-muted">Available</span>
        </div>
        <p class="text-muted small mb-0">
            Commitments come from actual memos (activities, single memos, special memos, non-travel memos, and latest change requests)
            whose status is in the configured committed lists.
            Drafts older than {{ $settings['draft_max_age_months'] ?? 2 }} month(s) are excluded.
            Cached ~45s in Redis with live fallback when cache is unavailable.
        </p>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="fundCodeLedgerTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="committed-tab" data-bs-toggle="tab" data-bs-target="#committed-panel" type="button" role="tab">
            <i class="bx bx-check-circle me-1"></i> Committing Memos
            <span class="badge bg-success ms-1">{{ count($committed) }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="skipped-tab" data-bs-toggle="tab" data-bs-target="#skipped-panel" type="button" role="tab">
            <i class="bx bx-x-circle me-1"></i> Skipped Memos
            <span class="badge bg-secondary ms-1">{{ count($skipped) }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="legacy-tab" data-bs-toggle="tab" data-bs-target="#legacy-panel" type="button" role="tab">
            <i class="bx bx-history me-1"></i> Legacy Transactions
            @if($fundCodeTransactions->total() > 0)
                <span class="badge bg-info ms-1">{{ $fundCodeTransactions->total() }}</span>
            @endif
        </button>
    </li>
</ul>

<div class="tab-content mb-4" id="fundCodeLedgerTabContent">
    {{-- Committing memos --}}
    <div class="tab-pane fade show active" id="committed-panel" role="tabpanel">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-wallet me-2"></i>Memos Committing Budget</h5>
                <span class="text-muted small">Total: <strong>${{ number_format($ledger['totals']['committed_sum'] ?? 0, 2) }}</strong></span>
            </div>
            <div class="card-body p-0">
                @if(count($committed) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-success">
                                <tr>
                                    <th>Type</th>
                                    <th>Memo</th>
                                    <th>Doc #</th>
                                    <th>Status</th>
                                    <th class="text-end">Amount</th>
                                    <th>Last Updated</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($committed as $line)
                                    <tr>
                                        <td><span class="badge bg-light text-dark border">{{ $line['type_label'] }}</span></td>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width: 260px;" title="{{ $line['title'] }}">
                                                {{ $line['title'] }}
                                            </div>
                                        </td>
                                        <td><code class="small">{{ $line['document_number'] ?? '—' }}</code></td>
                                        <td><span class="badge {{ $statusBadge($line['status']) }}">{{ ucfirst($line['status']) }}</span></td>
                                        <td class="text-end fw-bold text-danger">${{ number_format($line['amount'], 2) }}</td>
                                        <td>
                                            @if($line['updated_at'])
                                                <span class="small">{{ \Carbon\Carbon::parse($line['updated_at'])->format('M d, Y H:i') }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($line['url'])
                                                <a wire:navigate href="{{ $line['url'] }}" class="btn btn-sm btn-outline-primary" title="View memo">
                                                    <i class="bx bx-link-external"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Committed total</th>
                                    <th class="text-end text-danger">${{ number_format($snap['committed_total'], 2) }}</th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bx bx-check-shield display-4 d-block mb-2"></i>
                        No memos are currently committing budget for this fund code.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Skipped memos --}}
    <div class="tab-pane fade" id="skipped-panel" role="tabpanel">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bx bx-info-circle me-2"></i>Skipped Memos (Not Counted Toward Commitment)</h5>
            </div>
            <div class="card-body p-0">
                @if(count($skipped) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Type</th>
                                    <th>Memo</th>
                                    <th>Status</th>
                                    <th class="text-end">Would-be Amount</th>
                                    <th>Skip Reason</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($skipped as $line)
                                    <tr>
                                        <td><span class="badge bg-light text-dark border">{{ $line['type_label'] }}</span></td>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width: 220px;" title="{{ $line['title'] }}">{{ $line['title'] }}</div>
                                            @if($line['document_number'])
                                                <code class="small text-muted">{{ $line['document_number'] }}</code>
                                            @endif
                                        </td>
                                        <td><span class="badge {{ $statusBadge($line['status']) }}">{{ ucfirst($line['status']) }}</span></td>
                                        <td class="text-end text-muted">${{ number_format($line['amount'], 2) }}</td>
                                        <td>
                                            <span class="small">{{ $line['skip_reason'] }}</span>
                                            @if($line['skip_code'])
                                                <br><code class="small text-muted">{{ $line['skip_code'] }}</code>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($line['url'])
                                                <a wire:navigate href="{{ $line['url'] }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-link-external"></i></a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bx bx-list-check display-4 d-block mb-2"></i>
                        No skipped memos reference this fund code.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Legacy fund_code_transactions --}}
    <div class="tab-pane fade" id="legacy-panel" role="tabpanel">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bx bx-filter me-2"></i> Filter Legacy Transactions</h5>
            </div>
            <div class="card-body py-3 bg-light">
                <form action="{{ route('fund-codes.transactions', $fundCode) }}" method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="tab" value="legacy">
                    <div class="col-md-2">
                        <label class="form-label fw-semibold mb-1">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold mb-1">From</label>
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold mb-1">To</label>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold mb-1">Type</label>
                        <select name="transaction_type" class="form-select">
                            <option value="">All</option>
                            <option value="credit" @selected(request('transaction_type') == 'credit')>Credit</option>
                            <option value="debit" @selected(request('transaction_type') == 'debit')>Debit</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a wire:navigate href="{{ route('fund-codes.transactions', $fundCode) }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-history me-2"></i>Legacy Transaction Log</h5>
                <a href="{{ route('fund-codes.transactions', array_merge([$fundCode], request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-success btn-sm">
                    <i class="bx bx-download me-1"></i> Export CSV
                </a>
            </div>
            <div class="card-body p-0">
                @if($fundCodeTransactions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-success">
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Balance Before</th>
                                    <th>Balance After</th>
                                    <th>Activity</th>
                                    <th>By</th>
                                    <th>Type</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($fundCodeTransactions as $transaction)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $transaction->created_at->format('M d, Y') }}</div>
                                            <small class="text-muted">{{ $transaction->created_at->format('H:i') }}</small>
                                        </td>
                                        <td class="text-truncate" style="max-width: 180px;" title="{{ $transaction->description }}">{{ $transaction->description }}</td>
                                        <td class="fw-bold {{ $transaction->amount > 0 ? 'text-success' : 'text-danger' }}">${{ number_format(abs($transaction->amount), 2) }}</td>
                                        <td><span class="badge bg-info text-dark">${{ number_format($transaction->balance_before, 2) }}</span></td>
                                        <td><span class="badge bg-primary">${{ number_format($transaction->balance_after, 2) }}</span></td>
                                        <td>{{ $transaction->activity->activity_title ?? 'N/A' }}</td>
                                        <td>{{ $transaction->createdBy->name ?? 'N/A' }}</td>
                                        <td>
                                            @if($transaction->is_reversal)
                                                <span class="badge bg-warning text-dark">Reversal</span>
                                            @else
                                                <span class="badge {{ $transaction->amount > 0 ? 'bg-success' : 'bg-danger' }}">{{ $transaction->amount > 0 ? 'Credit' : 'Debit' }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($transaction->activity && $transaction->matrix)
                                                <a wire:navigate href="{{ route('matrices.activities.show', [$transaction->matrix, $transaction->activity]) }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i></a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($fundCodeTransactions->hasPages())
                        <div class="card-footer">{{ $fundCodeTransactions->appends(request()->except('page'))->links() }}</div>
                    @endif
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bx bx-history display-4 d-block mb-2"></i>
                        No legacy transactions recorded for this fund code.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card border-0 bg-light shadow-sm">
    <div class="card-body small text-muted">
        <strong>Commitment rules</strong> (App settings → Budget):
        Activities: {{ implode(', ', $settings['activity_statuses'] ?? []) }} ·
        Memos: {{ implode(', ', $settings['memo_statuses'] ?? []) }} ·
        Change requests: {{ implode(', ', $settings['change_request_statuses'] ?? []) }} ·
        Stale draft cutoff: {{ $settings['draft_max_age_months'] ?? 2 }} month(s).
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        var tab = new URLSearchParams(window.location.search).get('tab');
        if (tab === 'legacy') {
            var el = document.querySelector('#legacy-tab');
            if (el) bootstrap.Tab.getOrCreateInstance(el).show();
        }
    });
</script>
@endpush
