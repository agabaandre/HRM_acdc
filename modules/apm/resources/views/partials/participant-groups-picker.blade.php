<div class="mt-4 participant-groups-picker apm-v-picker-panel">
    <h6 class="fw-bold text-success mb-2">
        <i class="fas fa-layer-group me-2"></i> Add Participants from Groups
    </h6>
    <p class="text-muted small mb-3 apm-v-hint">
        Reuse saved participant sets from your division. <strong>Division Members</strong> adds everyone in your division automatically.
        <a href="{{ route('participant-groups.index') }}">View / manage groups</a>
    </p>
    <div class="row g-3 align-items-stretch">
        <div class="col-md-8">
            <label for="participant_group_select" class="form-label">Participant group</label>
            <select id="participant_group_select" class="form-select">
                <option value="" selected>None</option>
            </select>
        </div>
        <div class="col-md-4 d-flex flex-column">
            <label class="form-label d-none d-md-block">&nbsp;</label>
            <button type="button" class="btn btn-outline-success flex-grow-1" id="applyParticipantGroup">
                <i class="fas fa-user-check me-1"></i> Add group to participants
            </button>
        </div>
    </div>
</div>
