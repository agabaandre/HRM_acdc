@extends('layouts.app')

@section('title', 'Validate APM Document Signature Hashes')
@section('header', 'Validate APM Document Signature Hashes')

@section('content')
<div class="container-fluid">
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('verify_error') || !empty($verify_error))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ $verify_error ?? session('verify_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        {{-- Lookup by document number + year --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i>Look up document & signatory hashes</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Enter the document number and year of creation to view the document and all signatories with their verification hashes.</p>
                    <form action="{{ route('signature-verify.lookup') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label for="lookup_document_number" class="form-label">Document number</label>
                            <input type="text" class="form-control @error('document_number') is-invalid @enderror"
                                   id="lookup_document_number" name="document_number"
                                   value="{{ old('document_number', $document_number ?? '') }}" placeholder="e.g. AU/CDC/DHIS/IM/STM/001" required>
                            @error('document_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="lookup_year" class="form-label">Year of creation</label>
                            <input type="number" class="form-control @error('year') is-invalid @enderror"
                                   id="lookup_year" name="year" min="2000" max="2100" step="1"
                                   value="{{ old('year', $year ?? date('Y')) }}" required>
                            @error('year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Look up
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Verify by hash + document number --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-fingerprint me-2"></i>Verify a signature hash</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Enter a verification hash and document number to see which signatory and action it corresponds to.</p>
                    <form action="{{ route('signature-verify.verify') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label for="verify_hash" class="form-label">Verification hash</label>
                            <input type="text" class="form-control font-monospace @error('hash') is-invalid @enderror"
                                   id="verify_hash" name="hash" maxlength="32"
                                   value="{{ old('hash', $hash ?? '') }}" placeholder="16-character hash (e.g. A1B2C3D4E5F67890)" required>
                            @error('hash')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="verify_document_number" class="form-label">Document number</label>
                            <input type="text" class="form-control @error('document_number') is-invalid @enderror"
                                   id="verify_document_number" name="document_number"
                                   value="{{ old('document_number', $document_number ?? '') }}" required>
                        </div>
                        <div class="col-12">
                            <label for="verify_year" class="form-label">Year (optional)</label>
                            <input type="number" class="form-control" id="verify_year" name="year"
                                   min="2000" max="2100" step="1" value="{{ old('year', $year ?? '') }}" placeholder="Leave blank to search all years">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check-double me-1"></i> Verify hash
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Lookup result: document + signatories with hashes --}}
    @if (!empty($lookup_result))
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Document & signatory hashes</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-4">
                    <dt class="col-sm-3">Document type</dt>
                    <dd class="col-sm-9">{{ $lookup_result['doc_type'] }}</dd>
                    <dt class="col-sm-3">Document number</dt>
                    <dd class="col-sm-9"><code>{{ $lookup_result['document']->document_number ?? 'N/A' }}</code></dd>
                    <dt class="col-sm-3">Created</dt>
                    <dd class="col-sm-9">{{ isset($lookup_result['document']->created_at) ? \Carbon\Carbon::parse($lookup_result['document']->created_at)->format('j F Y') : 'N/A' }}</dd>
                </dl>
                <h6 class="mb-3">Signatories and verification hashes</h6>
                @if (count($lookup_result['signatories']) > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Role</th>
                                    <th>Name</th>
                                    <th>Action</th>
                                    <th>Date / time</th>
                                    <th>Verify hash</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lookup_result['signatories'] as $s)
                                    <tr>
                                        <td>{{ $s['role'] }}</td>
                                        <td>{{ $s['name'] }}</td>
                                        <td><span class="badge bg-secondary">{{ $s['action'] }}</span></td>
                                        <td>{{ $s['date'] }}</td>
                                        <td><code class="user-select-all">{{ $s['hash'] }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No signatories found for this document (no approval actions recorded).</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Verify result: single signatory match --}}
    @if (!empty($verify_result))
        <div class="card shadow-sm border-success mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Hash verified</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">The provided hash matches the following signatory on this document.</p>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Document type</dt>
                    <dd class="col-sm-9">{{ $verify_result['doc_type'] }}</dd>
                    <dt class="col-sm-3">Document number</dt>
                    <dd class="col-sm-9"><code>{{ $verify_result['document']->document_number ?? 'N/A' }}</code></dd>
                    <dt class="col-sm-3">Role</dt>
                    <dd class="col-sm-9">{{ $verify_result['signatory']['role'] }}</dd>
                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9">{{ $verify_result['signatory']['name'] }}</dd>
                    <dt class="col-sm-3">Action</dt>
                    <dd class="col-sm-9"><span class="badge bg-secondary">{{ $verify_result['signatory']['action'] }}</span></dd>
                    <dt class="col-sm-3">Date / time</dt>
                    <dd class="col-sm-9">{{ $verify_result['signatory']['date'] }}</dd>
                    <dt class="col-sm-3">Verify hash</dt>
                    <dd class="col-sm-9"><code class="user-select-all">{{ $verify_result['signatory']['hash'] }}</code></dd>
                </dl>
            </div>
        </div>
    @endif
</div>
@endsection
