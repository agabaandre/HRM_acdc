<div class="card border-0 shadow-sm mb-5">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <label for="activity_title" class="form-label fw-semibold">
                    <i class="fas fa-pen-nib me-1 text-success"></i> Activity Title <span class="text-danger">*</span>
                </label>
                <input type="text" name="activity_title" id="activity_title" class="form-control" 
                       value="{{ old('activity_title', $specialMemo->activity_title ?? '') }}" required>
            </div>
            <div class="col-md-6">
                <label for="request_type_id" class="form-label fw-semibold">
                    <i class="fas fa-tags me-1 text-success"></i> Request Type <span class="text-danger">*</span>
                </label>
                <select name="request_type_id" id="request_type_id" class="form-select" required>
                    <option value="">Select</option>
                    @foreach($requestTypes as $type)
                        <option value="{{ $type->id }}" 
                                {{ old('request_type_id', $specialMemo->request_type_id ?? '') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-12">
                <label for="background" class="form-label fw-semibold">
                    <i class="fas fa-align-left me-1 text-success"></i> Background/Context <span class="text-danger">*</span>
                </label>
                <textarea name="background" id="background" class="form-control" rows="3" required>{{ old('background', $specialMemo->background ?? '') }}</textarea>
            </div>
            <div class="col-md-6">
                <label for="responsible_person_id" class="form-label fw-semibold">
                    <i class="fas fa-user-tie me-1 text-success"></i> Responsible Person <span class="text-danger">*</span>
                </label>
                <select name="responsible_person_id" id="responsible_person_id" class="form-select select2" required>
                    <option value="">Select</option>
                    @foreach($staff as $member)
                        <option value="{{ $member->staff_id }}" 
                                {{ old('responsible_person_id', $specialMemo->responsible_person_id ?? '') == $member->staff_id ? 'selected' : '' }}>
                            {{ $member->fname }} {{ $member->lname }} - {{ $member->job_name ?? 'N/A' }} ({{ $member->duty_station_name ?? 'N/A' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label fw-semibold">
                    <i class="fas fa-calendar-day me-1 text-success"></i> Date From <span class="text-danger">*</span>
                </label>
                <input type="text" name="date_from" id="date_from" class="form-control datepicker" 
                       value="{{ old('date_from', optional($specialMemo?->date_from)->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label fw-semibold">
                    <i class="fas fa-calendar-check me-1 text-success"></i> Date To <span class="text-danger">*</span>
                </label>
                <input type="text" name="date_to" id="date_to" class="form-control datepicker" 
                       value="{{ old('date_to', optional($specialMemo?->date_to)->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-4">
                <label for="location_id" class="form-label fw-semibold">
                    <i class="fas fa-map-marker-alt me-1 text-success"></i> Location/Venue <span class="text-danger">*</span>
                </label>
                <select name="location_id[]" id="location_id" class="form-select border-success" multiple required>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" 
                                {{ in_array($location->id, old('location_id', json_decode($specialMemo->location_id ?? '[]', true) ?? [])) ? 'selected' : '' }}>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Hold CTRL or CMD to select multiple locations</small>
            </div>
            <div class="col-md-4">
                <label for="internal_participants" class="form-label fw-semibold">
                    <i class="fas fa-user-friends me-1 text-success"></i> Select Internal Participants <span class="text-danger">*</span>
                </label>
                <select name="internal_participants[]" id="internal_participants" class="form-select border-success" multiple required>
                    @php
                        $internalParticipants = is_string($specialMemo->internal_participants ?? null)
                            ? json_decode($specialMemo->internal_participants, true)
                            : ($specialMemo->internal_participants ?? []);
                    @endphp
                    @foreach($divisionStaff as $member)
                        <option value="{{ $member->staff_id }}" 
                                {{ in_array($member->staff_id, old('internal_participants', array_keys($internalParticipants))) ? 'selected' : '' }}>
                            {{ $member->fname }} {{ $member->lname }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">You cannot select more than the total participants</small>
            </div>
            <div class="col-md-4">
                <label for="total_external_participants" class="form-label fw-semibold">
                    <i class="fas fa-users me-1 text-success"></i> Number of External Participants
                </label>
                <input type="number" name="total_external_participants" id="total_external_participants" class="form-control border-success" 
                       value="{{ old('total_external_participants', $specialMemo->total_external_participants ?? 0) }}" min="0">
            </div>
            <div class="col-md-12">
                <label for="activity_request_remarks" class="form-label fw-semibold">
                    <i class="fas fa-comment-dots me-1 text-success"></i> Request for Approval <span class="text-danger">*</span>
                </label>
                <textarea name="activity_request_remarks" id="activity_request_remarks" class="form-control" rows="3" required>{{ old('activity_request_remarks', $specialMemo->activity_request_remarks ?? '') }}</textarea>
            </div>
            <div class="col-md-12">
                <label for="justification" class="form-label fw-semibold">
                    <i class="fas fa-comment-dots me-1 text-success"></i> Supporting Reasons for the Special Memo <span class="text-danger">*</span>
                </label>
                <textarea name="justification" id="justification" class="form-control" rows="3" required>{{ old('justification', $specialMemo->justification ?? '') }}</textarea>
            </div>
            <div class="mt-5">
                <h6 class="fw-bold text-success mb-3"><i class="fas fa-user-plus me-2"></i> Add Participants from Other Divisions</h6>
                <div id="externalParticipantsWrapper"></div>
                <button type="button" class="btn btn-outline-success btn-sm mt-2" id="addDivisionBlock">
                    <i class="fas fa-plus-circle me-1"></i> Add Division Participants
                </button>
            </div>
            <h6 class="fw-bold text-success mb-3 mt-4">
                <i class="fas fa-users-cog me-2"></i> Participants - Days
            </h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle" id="participantsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Participant Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>No. of Days</th>
                        </tr>
                    </thead>
                    <tbody id="participantsTableBody">
                        <tr><td colspan="4" class="text-muted text-center">No participants selected yet</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>