@extends('layouts.app')

@section('title', 'Fund Code Transactions')

@section('header', 'Fund Code Transactions')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('fund-codes.show', $fundCode) }}" class="btn btn-outline-info">
        <i class="bx bx-show"></i> View Fund Code
    </a>
    <a href="{{ route('fund-codes.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
</div>
@endsection

@section('content')
<!-- Filters Section -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 text-dark">
            <i class="bx bx-filter me-2"></i> Filter Transactions
        </h5>
    </div>
    <div class="card-body py-3 px-4 bg-light">
        <form action="{{ route('fund-codes.transactions', $fundCode) }}" method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="search" class="form-label fw-semibold mb-1"><i class="bx bx-search me-1 text-success"></i> Search</label>
                <div class="input-group w-100">
                    <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search transactions..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label fw-semibold mb-1"><i class="bx bx-calendar me-1 text-success"></i> From Date</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label fw-semibold mb-1"><i class="bx bx-calendar me-1 text-success"></i> To Date</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-2">
                <label for="amount_from" class="form-label fw-semibold mb-1"><i class="bx bx-dollar me-1 text-success"></i> Min Amount</label>
                <input type="number" step="0.01" name="amount_from" class="form-control" placeholder="0.00" value="{{ request('amount_from') }}">
            </div>
            <div class="col-md-2">
                <label for="amount_to" class="form-label fw-semibold mb-1"><i class="bx bx-dollar me-1 text-success"></i> Max Amount</label>
                <input type="number" step="0.01" name="amount_to" class="form-control" placeholder="0.00" value="{{ request('amount_to') }}">
            </div>
            <div class="col-md-2">
                <label for="transaction_type" class="form-label fw-semibold mb-1"><i class="bx bx-transfer me-1 text-success"></i> Type</label>
                <select name="transaction_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="credit" {{ request('transaction_type') == 'credit' ? 'selected' : '' }}>Credit</option>
                    <option value="debit" {{ request('transaction_type') == 'debit' ? 'selected' : '' }}>Debit</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="is_reversal" class="form-label fw-semibold mb-1"><i class="bx bx-refresh me-1 text-success"></i> Reversal</label>
                <select name="is_reversal" class="form-select">
                    <option value="">All</option>
                    <option value="1" {{ request('is_reversal') == '1' ? 'selected' : '' }}>Reversals Only</option>
                    <option value="0" {{ request('is_reversal') == '0' ? 'selected' : '' }}>Non-Reversals</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100 fw-bold" id="applyFilters">
                    <i class="bx bx-search-alt-2 me-1"></i> Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('fund-codes.transactions', $fundCode) }}" class="btn btn-outline-secondary w-100 fw-bold">
                    <i class="bx bx-reset me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-dark">
                <i class="bx bx-history me-2"></i> Transactions for {{ $fundCode->code }}
            </h5>
            <div class="d-flex gap-2">
                <a href="{{ route('fund-codes.transactions', array_merge([$fundCode], request()->query(), ['export' => 'csv'])) }}" 
                   class="btn btn-outline-success btn-sm">
                    <i class="bx bx-download me-1"></i> Export CSV
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-0">

        @if($fundCodeTransactions->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-success">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th> 
                            <th>Balance Before</th>
                            <th>Balance After</th>
                            <th>Activity</th>
                            <th>Created By</th>
                            <th>Type</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $count = 1; @endphp
                        @foreach($fundCodeTransactions as $transaction)     
                            <tr>
                                <td>{{ $count++ }}</td>
                                <td>
                                    <div class="fw-bold">{{ $transaction->created_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $transaction->created_at->format('H:i:s') }}</small>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;" title="{{ $transaction->description }}">
                                        {{ $transaction->description }}
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $amountClass = $transaction->amount > 0 ? 'text-success' : 'text-danger';
                                        $amountIcon = $transaction->amount > 0 ? 'bx-trending-up' : 'bx-trending-down';
                                    @endphp
                                    <span class="fw-bold {{ $amountClass }}">
                                        <i class="bx {{ $amountIcon }} me-1"></i>
                                        {{ number_format($transaction->amount, 2) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        {{ number_format($transaction->balance_before, 2) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary text-white">
                                        {{ number_format($transaction->balance_after, 2) }}
                                    </span>
                                </td>
                                <td>
                                    @if($transaction->activity)
                                        <div class="fw-bold text-primary">{{ $transaction->activity->name }}</div>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>{{ $transaction->createdBy->name ?? 'N/A' }}</td>
                                <td>
                                    @if($transaction->is_reversal)
                                        <span class="badge bg-warning text-dark">
                                            <i class="bx bx-refresh me-1"></i>Reversal
                                        </span>
                                    @else
                                        <span class="badge {{ $transaction->amount > 0 ? 'bg-success' : 'bg-danger' }} text-white">
                                            <i class="bx {{ $transaction->amount > 0 ? 'bx-plus' : 'bx-minus' }} me-1"></i>
                                            {{ $transaction->amount > 0 ? 'Credit' : 'Debit' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($transaction->activity && $transaction->matrix)
                                        <a href="{{ route('matrices.activities.show', [$transaction->matrix, $transaction->activity]) }}" 
                                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View Activity">
                                            <i class="bx bx-show"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if($fundCodeTransactions->hasPages())
                <div class="card-footer">
                    {{ $fundCodeTransactions->appends(request()->except('page'))->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-5">
                <div class="d-flex flex-column align-items-center">
                    <div class="mb-3">
                        <i class="bx bx-history text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-muted mb-3">No transactions found</h5>
                    <p class="text-muted mb-4">No transactions match your current filters</p>
                    <a href="{{ route('fund-codes.transactions', $fundCode) }}" class="btn btn-primary">
                        <i class="bx bx-refresh me-1"></i> Clear Filters
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
@endpush