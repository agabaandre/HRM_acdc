<div class="card border-0 shadow-sm mb-5">

    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <label for="activity_title" class="form-label fw-semibold">
                    <i class="fas fa-pen-nib me-1 text-success"></i> {{ $title ?? 'Activity' }} Title <span class="text-danger">*</span>
                </label>
                <input type="text" name="activity_title" id="activity_title" class="form-control " value="{{ old('activity_title', $activity->activity_title ?? '') }}" required>
            </div>
            <div class="col-md-3">
                <label for="request_type_id" class="form-label fw-semibold">
                    <i class="fas fa-tags me-1 text-success"></i> Request Type <span class="text-danger">*</span>
                </label>
                <select name="request_type_id" id="request_type_id" class="form-select " required>
                    <option value="">Select</option>
                    @foreach($requestTypes as $type)
                    <option value="{{ $type->id }}" {{ old('request_type_id', $activity->request_type_id ?? '') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="key_result_link" class="form-label fw-semibold">
                    <i class="fas fa-link me-1 text-success"></i> Link to Key Result <span class="text-danger">*</span>
                </label>
                <select name="key_result_link" id="key_result_link" class="form-select border-success" required>
                    <option value="">Select Key Result</option>
                    @php
                        $keyResults = is_array($matrix->key_result_area) 
                                    ? $matrix->key_result_area 
                                    : json_decode($matrix->key_result_area ?? '[]', true);
                    @endphp
                    @foreach($keyResults as $index => $kr)
                        <option value="{{ $index }}">
                            {{ $kr['description'] ?? 'No Description' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-12">
                <label for="background" class="form-label fw-semibold">
                    <i class="fas fa-align-left me-1 text-success"></i> Background /Justification/ Context <span class="text-danger">*</span>
                </label>
                <textarea name="background" id="background" class="form-control " rows="3" required>{{ old('background', $activity->background ?? '') }}</textarea>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <label for="responsible_person" class="form-label fw-semibold">
                    <i class="fas fa-user-tie me-1 text-success"></i> Responsible Person <span class="text-danger">*</span>
                </label>
                <select name="responsible_person_id" id="responsible_person_id" class="form-select select2 " required>
                    <option value="">Select</option>
                    @foreach($staff as $member)
                    <option value="{{ $member->staff_id }}" {{ old('responsible_person_id', $activity->responsible_person_id ?? '') == $member->staff_id ? 'selected' : '' }}>
                        {{ $member->fname }} {{ $member->lname }} - {{ $member->job_name ?? 'N/A' }} ({{ $member->duty_station_name ?? 'N/A' }})
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="date_from" class="form-label fw-semibold">
                    <i class="fas fa-calendar-day me-1 text-success"></i> Activity Date From <span class="text-danger">*</span>
                </label>
                <input type="text" name="date_from" id="date_from" class="form-control datepicker " value="{{ old('date_from', $activity->date_from ?? '') }}" required>
            </div>

            <div class="col-md-3">
                <label for="date_to" class="form-label fw-semibold">
                    <i class="fas fa-calendar-check me-1 text-success"></i> Activity Date To <span class="text-danger">*</span>
                </label>
                <input type="text" name="date_to" id="date_to" class="form-control datepicker " value="{{ old('date_to', $activity->date_to ?? '') }}" required>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <label for="location_id" class="form-label fw-semibold">
                    <i class="fas fa-map-marker-alt me-1 text-success"></i> Location/Venue <span class="text-danger">*</span>
                </label>
                <select name="location_id[]" id="location_id" class="form-select border-success" multiple required>
                    @foreach($locations as $location)
                        @php
                           $isSelected = false;
                          if($activity && isset($activity->location_id)):
                            $locationIds = is_string($activity->location_id ?? '') 
                                ? json_decode($activity->location_id, true) 
                                : ($activity->location_id ?? []);
                            $isSelected = in_array($location->id, old('location_id', $locationIds ?? []));
                        endif;
                        @endphp
                        @if($location->id == 1)
                        <option value="{{ $location->id }}" {{ $isSelected ? 'selected' : '' }}>
                            {{ $location->name }}
                        </option>
                        @endif
                        <option value="{{ $location->id }}" {{ $isSelected ? 'selected' : '' }}>
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
                    @foreach($divisionStaff as $member)
                        @php
                            $participantIds = [];
                            if (isset($activity->internal_participants)) {
                                $rawParticipants = is_string($activity->internal_participants) 
                                    ? json_decode($activity->internal_participants, true) 
                                    : $activity->internal_participants;
                                $participantIds = array_keys($rawParticipants ?? []);
                            }
                            $isSelected = in_array($member->staff_id, old('internal_participants', $participantIds));
                        @endphp
                        <option value="{{ $member->staff_id }}" {{ $isSelected ? 'selected' : '' }}>
                            {{ $member->fname }} {{ $member->lname }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label for="total_participants" class="form-label fw-semibold">
                    <i class="fas fa-users me-1 text-success"></i> Number of External Participants <span class="text-danger"></span>
                </label>
                <input type="number" name="total_external_participants" id="total_external_participants" class="form-control border-success" value="{{ old('total_external_participants', $activity->total_external_participants ?? 0) }}" min="0">
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12">
                <div class="mt-5">
                    <h6 class="fw-bold text-success mb-3"><i class="fas fa-user-plus me-2"></i> Add Participants from other Division</h6>
                    <div id="externalParticipantsWrapper"></div>
                    <button type="button" class="btn btn-outline-success btn-sm mt-2" id="addDivisionBlock">
                        <i class="fas fa-plus-circle me-1"></i> Add Division Participants
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12">
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
                                <th>International Travel</th>
                            </tr>
                        </thead>
                        <tbody id="participantsTableBody">
                            <tr><td colspan="5" class="text-muted text-center">No participants selected yet</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        </div>
    </div>
</div>