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
</style>

<div class="card shadow-sm mb-2">
    <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
        <h6 class="mb-0" style="font-size:1rem;">
            <i class="bx bx-info-circle me-2 text-primary"></i>Matrix Information
        </h6>
        <!-- Button trigger modal -->
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#keyResultAreasModal">
            <i class="bx bx-target-lock"></i> Key Result Areas
        </button>
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
                <span class="matrix-meta-value">{{ $matrix->division->name }}</span>
            </div>
            <div class="matrix-meta-item">
                <i class="bx bx-user-voice"></i>
                <span class="matrix-meta-label">Focal Person:</span>
                <span class="matrix-meta-value">{{ $matrix->focalPerson ? $matrix->focalPerson->name : 'Not assigned' }}</span>
            </div>
            <div class="matrix-meta-item">
                <i class="bx bx-calendar-plus"></i>
                <span class="matrix-meta-label">Created:</span>
                <span class="matrix-meta-value">{{ $matrix->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <div class="matrix-meta-item">
                <i class="bx bx-calendar-edit"></i>
                <span class="matrix-meta-label">Updated:</span>
                <span class="matrix-meta-value">{{ $matrix->updated_at->format('Y-m-d H:i') }}</span>
            </div>
        </div>
        <div>
            @if($matrix->overall_status !=='approved')
                <span class="badge {{config('approval_states')[$matrix->overall_status]}}">
                    <i class="fa fa-clock text-bold"></i>
                    {{ ($matrix->workflow_definition)?$matrix->workflow_definition->role:strtoupper($matrix->overall_status) }}
                </span>
            @endif
            @if($matrix->overall_status =='approved')
                <span class="badge bg-success"><i class="bx bx-check text-bold"></i>  {{strtoupper($matrix->overall_status)}}</span>
            @endif
        </div>
    </div>
</div>

<!-- Key Result Areas Modal -->
<div class="modal fade" id="keyResultAreasModal" tabindex="-1" aria-labelledby="keyResultAreasModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="keyResultAreasModalLabel"><i class="bx bx-target-lock me-2 text-primary"></i>Key Result Areas</h5>
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
            @foreach($keyResultAreas as $index => $area)
                <div class="border-bottom pb-2 mb-3">
                    <h6 class="fw-bold text-success">
                        <i class="bx bx-bullseye me-1"></i> Area {{ $index + 1 }}
                    </h6>
                    <p class="mb-0 text-muted">
                        {{ $area['description'] ?? 'No description provided' }}
                    </p>
                </div>
            @endforeach
        @endif
      </div>
    </div>
  </div>
</div>