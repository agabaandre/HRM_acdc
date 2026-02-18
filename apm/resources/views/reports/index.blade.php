@extends('layouts.app')

@section('title', 'Reports')
@section('header', 'Reports')

@section('content')
<style>
.reports-index .card { border: none; }
.reports-index .card-body { padding: 1.25rem; }
.reports-index .parent-icon { font-size: 1.5rem; }
</style>
<div class="card shadow-sm mb-4 border-0 reports-index">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-chart-bar me-2 text-success"></i> Reports</h4>
        </div>
        <p class="text-muted mb-4 mt-3">Select a report to view.</p>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0 bg-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                <i class="bx bx-pie-chart-alt text-success" style="font-size: 1.5rem;"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-success">Division memo counts</h5>
                        </div>
                        <p class="text-muted small mb-3">Counts of memos by division with breakdown by status (Approved, Pending, Returned). Filter by division, year, quarter, and memo type.</p>
                        <a href="{{ route('reports.division-counts') }}" class="btn btn-success btn-sm"><i class="bx bx-right-arrow-circle me-1"></i> View report</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0 bg-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                <i class="bx bx-list-ul text-success" style="font-size: 1.5rem;"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-success">Memo list (details)</h5>
                        </div>
                        <p class="text-muted small mb-3">List of all memos with document number, title, division, type, status, and dates. Filter and paginate.</p>
                        <a href="{{ route('reports.memo-list') }}" class="btn btn-success btn-sm"><i class="bx bx-right-arrow-circle me-1"></i> View report</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0 bg-white border-dashed" style="border-style: dashed !important;">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3">
                                <i class="bx bx-plus-circle text-secondary" style="font-size: 1.5rem;"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-secondary">More reports</h5>
                        </div>
                        <p class="text-muted small mb-3">Additional reports will be added here.</p>
                        <span class="btn btn-outline-secondary btn-sm disabled">Coming soon</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
