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
    @if (session('upload_error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('upload_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Progress bar (shown during AJAX submit) --}}
    <div id="verificationProgressWrap" class="mb-3" style="display: none;">
        <div class="progress" style="height: 6px;">
            <div id="verificationProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
        </div>
        <p id="verificationProgressText" class="small text-muted mt-1 mb-0">Processing…</p>
    </div>

    <div class="row">
        {{-- Validate uploaded document --}}
        <div class="col-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0 text-white"><i class="fas fa-file-upload me-2"></i>Validate uploaded document</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Upload an APM PDF. The document number and signature hashes will be read from the file and validated against the system. The file is not stored on the server.</p>
                    <form id="form-upload" action="{{ route('signature-verify.validate-upload') }}" method="POST" enctype="multipart/form-data" class="row g-3 verification-form">
                        @csrf
                        <div class="col-12">
                            <label for="upload_document" class="form-label">PDF document</label>
                            <input type="file" class="form-control" id="upload_document" name="document" accept=".pdf,application/pdf" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-info text-white btn-submit">
                                <i class="fas fa-check-circle me-1"></i> Validate document
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Lookup by document number + year --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0 text-white"><i class="fas fa-search me-2"></i>Look up document & signatory hashes</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Enter the document number and year of creation to view the document and all signatories with their verification hashes.</p>
                    <form id="form-lookup" action="{{ route('signature-verify.lookup') }}" method="POST" class="row g-3 verification-form">
                        @csrf
                        <div class="col-12">
                            <label for="lookup_document_number" class="form-label">Document number</label>
                            <input type="text" class="form-control" id="lookup_document_number" name="document_number" placeholder="e.g. AU/CDC/DHIS/IM/SM/001" required>
                        </div>
                        <div class="col-12">
                            <label for="lookup_year" class="form-label">Year of creation</label>
                            <input type="number" class="form-control" id="lookup_year" name="year" min="2000" max="2100" value="{{ date('Y') }}" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-submit">
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
                    <h5 class="mb-0 text-white"><i class="fas fa-fingerprint me-2"></i>Verify a signature hash</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Enter a verification hash and document number to see which signatory and action it corresponds to.</p>
                    <form id="form-verify" action="{{ route('signature-verify.verify') }}" method="POST" class="row g-3 verification-form">
                        @csrf
                        <div class="col-12">
                            <label for="verify_hash" class="form-label">Verification hash</label>
                            <input type="text" class="form-control font-monospace" id="verify_hash" name="hash" maxlength="32" placeholder="16-character hash" required>
                        </div>
                        <div class="col-12">
                            <label for="verify_document_number" class="form-label">Document number</label>
                            <input type="text" class="form-control" id="verify_document_number" name="document_number" required>
                        </div>
                        <div class="col-12">
                            <label for="verify_year" class="form-label">Year (optional)</label>
                            <input type="number" class="form-control" id="verify_year" name="year" min="2000" max="2100" placeholder="Leave blank to search all years">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-submit">
                                <i class="fas fa-check-double me-1"></i> Verify hash
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Result modal (unified layout for all methods) --}}
    <div class="modal fade" id="verificationResultModal" tabindex="-1" aria-labelledby="verificationResultModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered mx-auto">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-white" id="verificationResultModalLabel"><i class="fas fa-file-alt me-2"></i>Verification result</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="verificationResultContent" class="modal-body verification-print-content">
                    {{-- Filled by JS from JSON response --}}
                </div>
                <div class="modal-footer">
                    <a href="#" id="verificationResultPdf" class="btn btn-outline-primary me-2" style="display: none;" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-1"></i> Download PDF</a>
                    <button type="button" class="btn btn-outline-secondary" id="verificationResultPrint"><i class="fas fa-print me-1"></i> Print</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#verificationResultModal .modal-dialog { margin: 1.75rem auto; }
@media print {
    body * { visibility: hidden; }
    .verification-print-content, .verification-print-content * { visibility: visible; }
    .verification-print-content { position: absolute; left: 0; top: 0; width: 100%; }
    .modal-footer, .btn-close { display: none !important; }
}
</style>
@endsection

@push('scripts')
<script>
(function() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function showProgress(show, text) {
        const wrap = document.getElementById('verificationProgressWrap');
        const bar = document.getElementById('verificationProgressBar');
        const txt = document.getElementById('verificationProgressText');
        wrap.style.display = show ? 'block' : 'none';
        if (bar) bar.style.width = show ? '30%' : '0%';
        if (txt) txt.textContent = text || 'Processing…';
    }

    function setProgressPercent(pct) {
        const bar = document.getElementById('verificationProgressBar');
        if (bar) bar.style.width = pct + '%';
    }

    function renderResult(data) {
        const doc = data.document || {};
        const meta = doc.metadata || {};
        const signatories = data.signatories || [];
        const hashValidations = data.hash_validations || [];
        const matchedSignatory = data.matched_signatory || null;
        const resultType = data.result_type || '';

        let html = '';

        if (data.message) {
            html += '<div class="alert alert-warning mb-3">' + escapeHtml(data.message) + '</div>';
        }

        if (data.result_type === 'upload_validation') {
            if (data.valid) {
                html += '<p class="mb-3"><span class="badge bg-success fs-6 px-3 py-2"><i class="fas fa-check-circle me-1"></i> Final state: Valid</span></p>';
            } else {
                html += '<p class="mb-3"><span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="fas fa-exclamation-triangle me-1"></i> Final state: Not valid</span></p>';
            }
            if ((data.extracted_document_numbers || []).length) {
                html += '<p class="mb-1"><strong>Document number(s) from PDF:</strong></p><p class="mb-2">' + (data.extracted_document_numbers.map(function(d) { return '<code class="me-1">' + escapeHtml(d) + '</code>'; }).join('')) + '</p>';
            }
            if ((data.extracted_hashes || []).length) {
                html += '<p class="mb-1"><strong>Hash(es) from PDF:</strong></p><p class="mb-3">' + (data.extracted_hashes.map(function(h) { return '<code class="user-select-all me-1">' + escapeHtml(h) + '</code>'; }).join('')) + '</p>';
            }
        }

        var documentsList = data.documents || [];
        if (documentsList.length > 1) {
            html += '<h6 class="mb-2">Documents matched (' + documentsList.length + ')</h6>';
            documentsList.forEach(function(d, i) {
                var m = d.metadata || {};
                html += '<div class="border rounded p-2 mb-2 small">';
                html += '<strong>' + escapeHtml(d.doc_type || 'Document') + '</strong> — <code>' + escapeHtml(d.document_number || 'N/A') + '</code>';
                if (m.activity_title) html += '<br><span class="d-block mt-1">' + escapeHtml(m.activity_title) + '</span>';
                html += '<br><span class="text-muted">Creator: ' + escapeHtml(m.creator || 'N/A') + ' | Division: ' + escapeHtml(m.division || 'N/A') + ' | Created: ' + escapeHtml(m.date_created || 'N/A') + '</span>';
                html += '</div>';
            });
            html += '<p class="text-muted small mb-3">Hashes below may belong to any of these documents (e.g. parent memo and ARF).</p>';
        } else if (doc.document_number || doc.doc_type) {
            html += '<h6 class="mb-2">Document</h6>';
            html += '<dl class="row mb-3 small">';
            html += '<dt class="col-sm-3 text-muted">Document type</dt><dd class="col-sm-9">' + escapeHtml(doc.doc_type || 'N/A') + '</dd>';
            html += '<dt class="col-sm-3 text-muted">Document number</dt><dd class="col-sm-9"><code>' + escapeHtml(doc.document_number || 'N/A') + '</code></dd>';
            if (meta.activity_title) {
                html += '<dt class="col-sm-3 text-muted">Activity title</dt><dd class="col-sm-9">' + escapeHtml(meta.activity_title) + '</dd>';
            }
            html += '<dt class="col-sm-3 text-muted">Creator</dt><dd class="col-sm-9">' + escapeHtml(meta.creator || 'N/A') + '</dd>';
            html += '<dt class="col-sm-3 text-muted">Division</dt><dd class="col-sm-9">' + escapeHtml(meta.division || 'N/A') + '</dd>';
            html += '<dt class="col-sm-3 text-muted">Date created</dt><dd class="col-sm-9">' + escapeHtml(meta.date_created || 'N/A') + '</dd>';
            html += '</dl>';
        }

        if (matchedSignatory && data.hash_matched) {
            html += '<h6 class="mb-2">Matched signatory (hash verified)</h6>';
            html += '<dl class="row mb-3 small">';
            html += '<dt class="col-sm-3 text-muted">Role</dt><dd class="col-sm-9">' + escapeHtml(matchedSignatory.role || '') + '</dd>';
            html += '<dt class="col-sm-3 text-muted">Name</dt><dd class="col-sm-9">' + escapeHtml(matchedSignatory.name || '') + '</dd>';
            html += '<dt class="col-sm-3 text-muted">Action</dt><dd class="col-sm-9"><span class="badge bg-secondary">' + escapeHtml(matchedSignatory.action || '') + '</span></dd>';
            html += '<dt class="col-sm-3 text-muted">Date / time</dt><dd class="col-sm-9">' + escapeHtml(matchedSignatory.date || '') + '</dd>';
            html += '<dt class="col-sm-3 text-muted">Verify hash</dt><dd class="col-sm-9"><code class="user-select-all">' + escapeHtml(matchedSignatory.hash || '') + '</code></dd>';
            html += '</dl>';
        }

        if (hashValidations.length > 0) {
            html += '<h6 class="mb-2">Hash validation (from PDF)</h6>';
            html += '<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="table-light"><tr><th>Hash</th><th>Status</th><th>Signatory</th></tr></thead><tbody>';
            hashValidations.forEach(function(hv) {
                var signatoryText = '—';
                if (hv.signatory) {
                    signatoryText = escapeHtml(hv.signatory.name + ' — ' + hv.signatory.role + ' (' + hv.signatory.action + ')');
                    if (hv.signatory.document_number && documentsList.length > 1) signatoryText += ' <span class="text-muted small">[' + escapeHtml(hv.signatory.document_number) + ']</span>';
                }
                html += '<tr><td><code class="user-select-all">' + escapeHtml(hv.hash) + '</code></td>';
                html += '<td>' + (hv.matched ? '<span class="badge bg-success">Valid</span>' : '<span class="badge bg-warning text-dark">No match</span>') + '</td>';
                html += '<td>' + signatoryText + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }

        if (signatories.length > 0) {
            html += '<h6 class="mb-2 mt-3">Signatories and verification hashes</h6>';
            var showDocCol = signatories.some(function(s) { return s.document_number || s.doc_type; });
            html += '<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="table-light"><tr><th>Role</th><th>Name</th><th>Action</th><th>Date / time</th>' + (showDocCol ? '<th>Document</th>' : '') + '<th>Verify hash</th></tr></thead><tbody>';
            signatories.forEach(function(s) {
                html += '<tr><td>' + escapeHtml(s.role) + '</td><td>' + escapeHtml(s.name) + '</td><td><span class="badge bg-secondary">' + escapeHtml(s.action) + '</span></td><td>' + escapeHtml(s.date) + '</td>';
                if (showDocCol) html += '<td class="small">' + (s.doc_type ? escapeHtml(s.doc_type) : '') + (s.document_number ? ' <code>' + escapeHtml(s.document_number) + '</code>' : '') + '</td>';
                html += '<td><code class="user-select-all">' + escapeHtml(s.hash) + '</code></td></tr>';
            });
            html += '</tbody></table></div>';
        }

        if (!html) html = '<p class="text-muted mb-0">No data to display.</p>';
        return html;
    }

    function escapeHtml(s) {
        if (s == null) return '';
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    var verificationModalEl = document.getElementById('verificationResultModal');
    var verificationModalInstance = verificationModalEl ? new bootstrap.Modal(verificationModalEl, { backdrop: 'static', keyboard: true }) : null;

    if (verificationModalEl) {
        verificationModalEl.addEventListener('shown.bs.modal', function() {
            verificationModalEl.setAttribute('aria-hidden', 'false');
        });
        verificationModalEl.addEventListener('hidden.bs.modal', function() {
            verificationModalEl.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            var backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(function(b) { b.remove(); });
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        });
    }

    var pdfBaseUrl = '{{ route("signature-verify.print", [], false) }}';

    function setPdfLink(data) {
        var btn = document.getElementById('verificationResultPdf');
        if (!btn) return;
        var doc = data && data.document;
        var docNum = doc && doc.document_number;
        var year = data && data.year;
        if (docNum && year) {
            var params = new URLSearchParams({ document_number: docNum, year: year });
            if (data.matched_signatory && data.matched_signatory.hash) params.set('hash', data.matched_signatory.hash);
            btn.href = pdfBaseUrl + '?' + params.toString();
            btn.style.display = 'inline-block';
        } else {
            btn.href = '#';
            btn.style.display = 'none';
        }
    }

    function showModal(content, data) {
        document.getElementById('verificationResultContent').innerHTML = content;
        if (data) setPdfLink(data);
        if (verificationModalInstance) verificationModalInstance.show();
    }

    document.getElementById('verificationResultPrint').addEventListener('click', function() {
        var pdfLink = document.getElementById('verificationResultPdf');
        if (pdfLink && pdfLink.href && pdfLink.href !== '#' && pdfLink.style.display !== 'none') {
            window.open(pdfLink.href, '_blank', 'noopener');
        } else {
            window.print();
        }
    });

    document.querySelectorAll('.verification-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const isUpload = form.id === 'form-upload';
            const url = form.action;
            const submitBtn = form.querySelector('.btn-submit');

            showProgress(true, isUpload ? 'Uploading and validating…' : 'Verifying…');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            function done() {
                showProgress(false);
                if (submitBtn) submitBtn.disabled = false;
            }

            if (isUpload) {
                const fd = new FormData(form);
                const xhr = new XMLHttpRequest();
                xhr.open('POST', url);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                xhr.upload.addEventListener('progress', function(ev) {
                    if (ev.lengthComputable) setProgressPercent(30 + Math.round(70 * ev.loaded / ev.total));
                });
                xhr.onload = function() {
                    setProgressPercent(100);
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (xhr.status === 422 && data.errors) {
                            let msg = data.message || 'Validation failed.';
                            for (const k in data.errors) { msg += ' ' + (Array.isArray(data.errors[k]) ? data.errors[k].join(' ') : data.errors[k]); }
                            showModal('<div class="alert alert-danger">' + escapeHtml(msg) + '</div>');
                        } else if (data.success !== false) {
                            showModal(renderResult(data), data);
                        } else {
                            showModal('<div class="alert alert-danger">' + escapeHtml(data.message || 'An error occurred.') + '</div>');
                        }
                    } catch (err) {
                        showModal('<div class="alert alert-danger">Invalid response from server.</div>');
                    }
                    done();
                };
                xhr.onerror = function() {
                    showModal('<div class="alert alert-danger">Network error. Please try again.</div>');
                    done();
                };
                xhr.send(fd);
            } else {
                const fd = new FormData(form);
                fetch(url, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    }
                }).then(function(r) {
                    return r.json().then(function(data) { return { status: r.status, data: data }; });
                }).then(function(res) {
                    setProgressPercent(100);
                    const data = res.data;
                    if (res.status === 422 && data.errors) {
                        let msg = data.message || 'Validation failed.';
                        for (const k in data.errors) { msg += ' ' + (Array.isArray(data.errors[k]) ? data.errors[k].join(' ') : data.errors[k]); }
                        showModal('<div class="alert alert-danger">' + escapeHtml(msg) + '</div>');
                    } else if (data.success !== false) {
                        showModal(renderResult(data), data);
                    } else {
                        showModal('<div class="alert alert-danger">' + escapeHtml(data.message || 'An error occurred.') + '</div>');
                    }
                    done();
                }).catch(function() {
                    showModal('<div class="alert alert-danger">Network error. Please try again.</div>');
                    done();
                });
                setProgressPercent(50);
            }
        });
    });

    @if(isset($result_for_display))
    showModal(renderResult(@json($result_for_display)), @json($result_for_display));
    @endif
})();
</script>
@endpush
