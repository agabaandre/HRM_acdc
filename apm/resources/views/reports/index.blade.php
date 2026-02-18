@extends('layouts.app')

@section('title', 'Reports')
@section('header', 'Reports')

@section('content')
<style>
.reports-index .card { border: none; }
.reports-index .card-body { padding: 0.75rem 1rem; }
.reports-index .report-card-icon { font-size: 1.25rem; width: 2.25rem; height: 2.25rem; display: inline-flex; align-items: center; justify-content: center; }
.reports-index .report-card h5 { font-size: 1rem; margin-bottom: 0; }
.reports-index .report-card .text-muted { font-size: 0.8rem; margin-bottom: 0.5rem !important; }
.reports-index .report-card .btn { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
</style>
<div class="card shadow-sm mb-4 border-0 reports-index">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top py-2">
            <h5 class="mb-0 text-success fw-bold"><i class="bx bx-chart-bar me-2 text-success"></i> Reports</h5>
        </div>
        <p class="text-muted mb-3 mt-2 small">Select a report to view.</p>

        <div class="row g-3">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0 bg-white report-card">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-success bg-opacity-10 report-card-icon me-2">
                                <i class="bx bx-chart-bar text-success"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-success">Division memo counts</h5>
                        </div>
                        <p class="text-muted small mb-2">Counts of memos by division (Approved, Pending, Returned, Draft). Filter by division, year, quarter, memo type.</p>
                        <a href="{{ route('reports.division-counts') }}" class="btn btn-success btn-sm"><i class="bx bx-right-arrow-circle me-1"></i> View report</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0 bg-white report-card">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-success bg-opacity-10 report-card-icon me-2">
                                <i class="bx bx-chart-bar text-success"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-success">Memo list (details)</h5>
                        </div>
                        <p class="text-muted small mb-2">List of all memos with document number, title, division, type, status. Filter and paginate.</p>
                        <a href="{{ route('reports.memo-list') }}" class="btn btn-success btn-sm"><i class="bx bx-right-arrow-circle me-1"></i> View report</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0 bg-white border-dashed report-card" style="border-style: dashed !important;">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-secondary bg-opacity-10 report-card-icon me-2">
                                <i class="bx bx-chart-bar text-secondary"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-secondary">More reports</h5>
                        </div>
                        <p class="text-muted small mb-2">Additional reports will be added here.</p>
                        <span class="btn btn-outline-secondary btn-sm disabled">Coming soon</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
