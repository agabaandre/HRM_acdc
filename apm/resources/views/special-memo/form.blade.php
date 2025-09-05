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
                <textarea name="background" id="background" class="form-control summernote" rows="3" required>{{ old('background', $specialMemo->background ?? '') }}</textarea>
            </div>
            <div class="col-md-6">
                <label for="responsible_person_id" class="form-label fw-semibold">
                    <i class="fas fa-user-tie me-1 text-success"></i> Responsible Person <span class="text-danger">*</span>
                </label>
                @php
                    // Determine the selected responsible person id robustly
                    $selectedResponsibleId = old('responsible_person_id');
                    if (is_null($selectedResponsibleId)) {
                        $selectedResponsibleId = $specialMemo->responsible_person_id ?? $specialMemo->staff_id ?? null;
                    }
                    // Find the selected staff member, even if not in $staff
                    $allStaff = $staff;
                    $selectedStaff = null;
                    if ($selectedResponsibleId && !$staff->contains('staff_id', $selectedResponsibleId)) {
                        $selectedStaff = \App\Models\Staff::where('staff_id', $selectedResponsibleId)->first();
                    }
                @endphp
                <select name="responsible_person_id" id="responsible_person_id" class="form-select select2" required style="width: 100%;">
                    <option value="">Select</option>
                    @foreach($staff as $member)
                        <option value="{{ $member->staff_id }}" 
                                {{ (string)$selectedResponsibleId === (string)$member->staff_id ? 'selected' : '' }}>
                            {{ $member->fname }} {{ $member->lname }} - {{ $member->job_name ?? 'N/A' }} ({{ $member->duty_station_name ?? 'N/A' }})
                        </option>
                    @endforeach
                    @if($selectedStaff)
                        <option value="{{ $selectedStaff->staff_id }}" selected>
                            {{ $selectedStaff->fname }} {{ $selectedStaff->lname }} - {{ $selectedStaff->job_name ?? 'N/A' }} ({{ $selectedStaff->duty_station_name ?? 'N/A' }})
                        </option>
                    @endif
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label fw-semibold">
                    <i class="fas fa-calendar-day me-1 text-success"></i> Activity Date From <span class="text-danger">*</span>
                </label>
                <input type="text" name="date_from" id="date_from" class="form-control datepicker" 
                       value="{{ old('date_from', optional($specialMemo?->date_from)->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label fw-semibold">
                    <i class="fas fa-calendar-check me-1 text-success"></i> Activity Date To <span class="text-danger">*</span>
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
                        @php
                            $locationIds = [];
                            if ($specialMemo && $specialMemo->location_id) {
                                if (is_array($specialMemo->location_id)) {
                                    $locationIds = $specialMemo->location_id;
                                } else {
                                    $decoded = json_decode($specialMemo->location_id, true);
                                    if (is_array($decoded)) {
                                        $locationIds = $decoded;
                                    } else {
                                        // Handle double-encoded case
                                        $doubleDecoded = json_decode($decoded, true);
                                        $locationIds = is_array($doubleDecoded) ? $doubleDecoded : [];
                                    }
                                }
                            }
                        @endphp
                        <option value="{{ $location->id }}" 
                                {{ in_array((string)$location->id, old('location_id', array_map('strval', $locationIds))) ? 'selected' : '' }}>
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
                <select name="internal_participants[]" id="internal_participants" class="form-select select2 border-success" multiple required>
                    @php
                        // Use processed participants if available, otherwise fall back to raw data
                        $selectedParticipantIds = [];
                        if (isset($internalParticipants) && !empty($internalParticipants)) {
                            // Extract staff_id from processed participants
                            foreach ($internalParticipants as $participant) {
                                if (isset($participant['staff']['staff_id'])) {
                                    $selectedParticipantIds[] = $participant['staff']['staff_id'];
                                } elseif (isset($participant['staff_id'])) {
                                    $selectedParticipantIds[] = $participant['staff_id'];
                                }
                            }
                        } else {
                            // Fallback to raw data
                            $rawParticipants = is_string($specialMemo->internal_participants ?? null)
                                ? json_decode($specialMemo->internal_participants, true)
                                : ($specialMemo->internal_participants ?? []);
                            $selectedParticipantIds = array_keys($rawParticipants);
                        }
                    @endphp
                    @foreach($divisionStaff as $member)
                        <option value="{{ $member->staff_id }}" 
                                {{ in_array($member->staff_id, old('internal_participants', $selectedParticipantIds)) ? 'selected' : '' }}>
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
                <label for="justification" class="form-label fw-semibold">
                    <i class="fas fa-comment-dots me-1 text-success"></i> Supporting Reasons for the Special Memo <span class="text-danger">*</span>
                </label>
                <textarea name="justification" id="justification" class="form-control summernote" rows="3" required>{{ old('justification', $specialMemo->justification ?? '') }}</textarea>
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
                            <th>Activity Start Date</th>
                            <th>Activity End Date</th>
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