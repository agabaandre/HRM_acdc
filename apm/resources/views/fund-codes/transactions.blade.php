@extends('layouts.app')

@section('title', 'Fund Code Details')

@section('header', 'Fund Code Details')

@section('header-actions')
<div class="d-flex gap-2">

    <a href="{{ route('fund-codes.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
</div>
@endsection

@section('content')
<div class="row">

 <div class="col-md-12">
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-3"><i class="bx bx-list-ul me-2 text-primary"></i>Fund Code Transactions</h5>
        </div>

        <div class="card-body">

        @if($fundCodeTransactions->count() > 0)
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Amount</th> 
                        <th>Balance Before</th>
                        <th>Balance After</th>
                        <th>Created By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fundCodeTransactions as $transaction)     
                        <tr>
                            <td>{{ $transaction->created_at->format('d/m/Y') }}</td>
                            <td>{{ $transaction->description }}</td>
                            <td>{{ number_format($transaction->amount, 2) }}</td>
                            <td>{{ number_format(   $transaction->balance_before, 2) }}</td>
                            <td>{{ number_format($transaction->balance_after, 2) }}</td>
                            <td>{{ $transaction->createdByName ?? 'N/A' }}</td>
                            <td><a href="{{ route("matrices.activities.show", [$transaction->matrix, $transaction->activity]) }}" class="btn btn-sm btn-primary">View Activity</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="alert alert-info"> <i class="bx bx-info-circle"></i> No transactions found for this fund code</div>
            @endif
        </div>
    </div>
    </div>
 </div>

</div>
@endsection