<style>
.matrix-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.5rem;
    font-size: 0.92rem;
    line-height: 1.1;
    margin-bottom: 0.5rem;
}
.matrix-meta-item {
    display: flex;
    align-items: center;
    min-width: 120px;
    margin-bottom: 0;
}
.matrix-meta-item i {
    font-size: 1rem;
    margin-right: 0.3rem;
    color: #007bff;
}
.matrix-meta-label {
    color: #888;
    font-size: 0.85em;
    margin-right: 0.2em;
}
.matrix-meta-value {
    font-weight: 500;
}

/* Enhanced styling for budget values - eye-friendly colors */
#intramural-budget, #extramural-budget, #total-budget {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 0.4rem 0.6rem;
    border-radius: 0.4rem;
    border: 1px solid transparent;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    display: inline-block;
    min-width: 100px;
    font-size: 0.9rem;
}

#intramural-budget {
    border-color: #52c41a;
    background: linear-gradient(135deg, #f6ffed 0%, #f0f9e8 100%);
    color: #389e0d !important;
}

#extramural-budget {
    border-color: #1890ff;
    background: linear-gradient(135deg, #f0f9ff 0%, #e6f7ff 100%);
    color: #0958d9 !important;
}

#total-budget {
    border-color: #722ed1;
    background: linear-gradient(135deg, #f9f0ff 0%, #efdbff 100%);
    color: #531dab !important;
}

#intramural-budget:hover, #extramural-budget:hover, #total-budget:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

/* Modal content wrapping styles for Key Result Areas */
.modal-body .list-group-item {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    overflow-wrap: break-word;
    hyphens: auto;
}

.modal-body .list-group-item p {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    overflow-wrap: break-word;
    hyphens: auto;
    line-height: 1.5;
}

.modal-body .list-group-item h6 {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    overflow-wrap: break-word;
}

/* Ensure modal content doesn't exceed width */
.modal-body {
    max-width: 100%;
    overflow-x: hidden;
}
</style>

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
        <h6 class="mb-0" style="font-size:1rem;">
            <i class="bx bx-info-circle me-2 text-primary"></i>Matrix Information
        </h6>
        <div class="d-flex gap-2">
            @if($matrix->overall_status === 'pending')
                <a href="{{ route('matrices.view-status', $matrix) }}" class="btn btn-info btn-sm shadow-sm">
                    <i class="bx bx-info-circle me-1"></i> View Status
                </a>
            @endif
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#keyResultAreasModal">
                <i class="bx bx-target-lock"></i> Key Result Areas
            </button>
        </div>
    </div>
    <div class="card-body py-2 px-3">
        <div class="matrix-meta-row">
            <div class="matrix-meta-item">
                <i class="bx bx-calendar-alt"></i>
                <span class="matrix-meta-label">Year:</span>
                <span class="matrix-meta-value">{{ $matrix->year }}</span>
            </div>
            <div class="matrix-meta-item">
                <i class="bx bx-calendar-week"></i>
                <span class="matrix-meta-label">Quarter:</span>
                <span class="matrix-meta-value">{{ $matrix->quarter }}</span>
            </div>
            <div class="matrix-meta-item">
                <i class="bx bx-building"></i>
                <span class="matrix-meta-label">Division:</span>
                <span class="matrix-meta-value">{{ $matrix->division->division_name }}</span>
            </div>
            <div class="matrix-meta-item">
                <i class="bx bx-user-voice"></i>
                <span class="matrix-meta-label">Focal Person:</span>
                <span class="matrix-meta-value">{{ $matrix->focalPerson ? ($matrix->focalPerson->fname." ".$matrix->focalPerson->lname): 'Not assigned' }}</span>
            </div>
            <div class="matrix-meta-item">
                <i class="bx bx-money text-success"></i>
                <span class="matrix-meta-label fw-bold">Intramural Budget:</span>
                <span class="matrix-meta-value text-success fw-bold fs-6" id="intramural-budget">
                    <i class="bx bx-loader-alt bx-spin"></i> Loading...
                </span>
            </div>
            <div class="matrix-meta-item">
                <i class="bx bx-money text-info"></i>
                <span class="matrix-meta-label fw-bold">Extramural Budget:</span>
                <span class="matrix-meta-value text-info fw-bold fs-6" id="extramural-budget">
                    <i class="bx bx-loader-alt bx-spin"></i> Loading...
                </span>
            </div>
        </div>
        <div class="mt-3">
            @if($matrix->overall_status !== 'approved')
                <span class="badge {{ config('approval_states')[$matrix->overall_status] }} p-2">
                    <i class="fa fa-clock text-bold"></i>
                    {{ $matrix->workflow_definition && $matrix->approval_level>0 ? $matrix->workflow_definition->role : strtoupper($matrix->overall_status) }}
                    <small class="text-white">
                        {{ $matrix->current_actor ? "(".$matrix->current_actor->fname." ".$matrix->current_actor->lname.")" : "" }}
                    </small>
                </span>
            @endif
            @if($matrix->overall_status == 'approved')
                <span class="badge bg-success p-2">
                    <i class="bx bx-check text-bold"></i> {{ strtoupper($matrix->overall_status) }}
                </span>
            @endif
        </div>
    </div>
</div>

<!-- Key Result Areas Modal -->
<div class="modal fade" id="keyResultAreasModal" tabindex="-1" aria-labelledby="keyResultAreasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="keyResultAreasModalLabel">
                    <i class="bx bx-target-lock me-2 text-primary"></i>Key Result Areas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @php
                    $keyResultAreas = is_array($matrix->key_result_area)
                        ? $matrix->key_result_area
                        : json_decode($matrix->key_result_area ?? '[]', true);
                @endphp

                @if(empty($keyResultAreas))
                    <div class="alert alert-info mb-0">
                        <i class="bx bx-info-circle me-2"></i> No key result areas have been added yet.
                    </div>
                @else
                    <div class="list-group">
                        @foreach($keyResultAreas as $index => $area)
                            <div class="list-group-item border-0 border-bottom pb-2 mb-2">
                                <h6 class="fw-bold text-success mb-1">
                                    <i class="bx bx-bullseye me-1"></i> Area {{ $index + 1 }}
                                </h6>
                                <p class="mb-0 text-muted">
                                    {{ $area['description'] ?? 'No description provided' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>