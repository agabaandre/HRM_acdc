{{--
  Map an existing APM document to an RBM activity (sets pra_activity_id only).

  Required:
    $rbmMapDocumentType  activity|non_travel|special_memo
    $rbmMapDocumentId    int
  Optional:
    $rbmMapPraActivityId int|null  current mapping
    $rbmMapYear          int
    $rbmMapQuarter       string Q1-Q4
    $rbmMapDivisionId    int|null
--}}
@php
    $rbmMapEnabled = function_exists('pra_create_enabled') ? pra_create_enabled() : true;
    $rbmMapPraActivityId = isset($rbmMapPraActivityId) ? (int) $rbmMapPraActivityId : 0;
    $rbmMapYear = (int) ($rbmMapYear ?? now()->year);
    $rbmMapQuarter = strtoupper((string) ($rbmMapQuarter ?? ''));
    if (! in_array($rbmMapQuarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
        $rbmMapQuarter = 'Q'.(int) ceil(now()->month / 3);
    }
    $rbmMapDivisionId = isset($rbmMapDivisionId) ? (int) $rbmMapDivisionId : 0;
@endphp

@if($rbmMapEnabled)
<div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4" id="rbm-map-banner">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <i class="bx bx-cloud-download text-success fs-5"></i>
        <div>
            <div class="fw-semibold">RBM mapping</div>
            <div class="small text-muted" id="rbm-map-status">
                @if($rbmMapPraActivityId > 0)
                    Linked to RBM activity <strong id="rbm-map-current-id">{{ $rbmMapPraActivityId }}</strong>
                @else
                    Not linked to an RBM activity
                @endif
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        @if($rbmMapPraActivityId > 0)
            <button type="button" class="btn btn-outline-secondary btn-sm" id="rbm-map-clear-btn">
                Clear mapping
            </button>
        @endif
        <button type="button" class="btn btn-success btn-sm" id="rbm-map-open-btn">
            <i class="bx bx-link-alt me-1"></i>
            {{ $rbmMapPraActivityId > 0 ? 'Change RBM mapping' : 'Map to RBM' }}
        </button>
    </div>
</div>

<style>
    /* Force viewport centering; page wrappers often break Bootstrap fixed modals. */
    #rbmMapModal.modal {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100% !important;
        height: 100% !important;
        margin: 0 !important;
        padding: 1rem !important;
        z-index: 1065 !important;
        overflow-x: hidden;
        overflow-y: auto;
    }
    #rbmMapModal.modal.show {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    #rbmMapModal .modal-dialog {
        position: relative !important;
        margin: 0 auto !important;
        max-width: min(1140px, 96vw) !important;
        width: 100%;
        transform: none !important;
        pointer-events: auto;
    }
    #rbmMapModal table {
        table-layout: fixed;
        width: 100%;
    }
    #rbmMapModal th:nth-child(1),
    #rbmMapModal td:nth-child(1) { width: 120px; }
    #rbmMapModal th:nth-child(2),
    #rbmMapModal td:nth-child(2) { width: 120px; }
    #rbmMapModal th:nth-child(3),
    #rbmMapModal td:nth-child(3) { width: 42%; }
    #rbmMapModal th:nth-child(4),
    #rbmMapModal td:nth-child(4) { width: 22%; }
    #rbmMapModal th:nth-child(5),
    #rbmMapModal td:nth-child(5) { width: 18%; }
    #rbmMapModal .rbm-map-title,
    #rbmMapModal .rbm-map-outcome {
        white-space: normal !important;
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.35;
    }
    #rbmMapModal tr.rbm-map-locked {
        opacity: 0.85;
        cursor: not-allowed !important;
        background: #fafafa;
    }
</style>
<div class="modal fade" id="rbmMapModal" tabindex="-1" aria-labelledby="rbmMapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2" id="rbmMapModalLabel">
                    <i class="bx bx-cloud-download text-success"></i>
                    Map to RBM activity
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Year</label>
                        <input type="number" class="form-control form-control-sm" id="rbm-map-year" value="{{ $rbmMapYear }}" min="2020" max="2035">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Quarter filter</label>
                        <select class="form-select form-select-sm" id="rbm-map-quarter-filter">
                            <option value="">All quarters</option>
                            @foreach(['Q1','Q2','Q3','Q4'] as $q)
                                <option value="{{ $q }}" @selected($rbmMapQuarter === $q)>{{ $q }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Search</label>
                        <input type="search" class="form-control form-control-sm" id="rbm-map-search" placeholder="Search title, code, outcome…">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100" id="rbm-map-refresh-btn">
                            <i class="bx bx-refresh me-1"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="small text-muted mb-2" id="rbm-map-meta">Loading RBM activities…</div>
                <div class="progress mb-3 d-none" id="rbm-map-progress" style="height: 3px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                </div>
                <div class="alert alert-warning py-2 d-none" id="rbm-map-alert"></div>

                <div class="table-responsive border rounded" style="max-height: 420px;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Select</th>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Outcome</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="rbm-map-tbody">
                            <tr><td colspan="5" class="text-muted text-center py-4">Open to load activities.</td></tr>
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="rbm-map-save-btn" disabled>
                    <i class="bx bx-link-alt me-1"></i> Save mapping
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.ApmRbmMapConfig = {
    documentType: @json($rbmMapDocumentType),
    documentId: {{ (int) $rbmMapDocumentId }},
    praActivityId: {{ $rbmMapPraActivityId > 0 ? $rbmMapPraActivityId : 'null' }},
    year: {{ $rbmMapYear }},
    quarter: @json($rbmMapQuarter),
    divisionId: {{ $rbmMapDivisionId > 0 ? $rbmMapDivisionId : 'null' }},
    activitiesUrl: @json(route('matrices.pra.activities')),
    linkUrl: @json(route('matrices.pra.link')),
    csrf: @json(csrf_token()),
};
</script>
<script src="{{ asset('js/rbm-map-modal.js') }}?v={{ (@filemtime(public_path('js/rbm-map-modal.js')) ?: time()) + 2 }}" defer></script>
@endif
