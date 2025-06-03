<div class="card border-0 shadow-sm mb-5">

    <div class="card-body">
        <div class="row g-4">
        <div class="col-md-6">
                <label for="activity_title" class="form-label fw-semibold">
                    <i class="fas fa-pen-nib me-1 text-success"></i> Activity Title <span class="text-danger">*</span>
                </label>
                <input type="text" name="activity_title" id="activity_title" class="form-control " value="{{ old('activity_title') }}" required>
            </div>
            <div class="col-md-6">
                <label for="request_type_id" class="form-label fw-semibold">
                    <i class="fas fa-tags me-1 text-success"></i> Request Type <span class="text-danger">*</span>
                </label>
                <select name="request_type_id" id="request_type_id" class="form-select " required>
                    <option value="">Select</option>
                    @foreach($requestTypes as $type)
                    <option value="{{ $type->id }}" {{ old('request_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>

          

            <div class="col-md-12">
                <label for="background" class="form-label fw-semibold">
                    <i class="fas fa-align-left me-1 text-success"></i> Background/Context <span class="text-danger">*</span>
                </label>
                <textarea name="background" id="background" class="form-control " rows="3" required>{{ old('background') }}</textarea>
            </div>

            <div class="col-md-6">
                <label for="staff_id" class="form-label fw-semibold">
                    <i class="fas fa-user-tie me-1 text-success"></i> Responsible Person <span class="text-danger">*</span>
                </label>
                <select name="staff_id" id="staff_id" class="form-select select2 " required>
                    <option value="">Select</option>
                    @foreach($staff as $member)
                    <option value="{{ $member->id }}" {{ old('staff_id') == $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="date_from" class="form-label fw-semibold">
                    <i class="fas fa-calendar-day me-1 text-success"></i> Date From <span class="text-danger">*</span>
                </label>
                <input type="text" name="date_from" id="date_from" class="form-control datepicker " value="{{ old('date_from') }}" required>
            </div>

            <div class="col-md-3">
                <label for="date_to" class="form-label fw-semibold">
                    <i class="fas fa-calendar-check me-1 text-success"></i> Date To <span class="text-danger">*</span>
                </label>
                <input type="text" name="date_to" id="date_to" class="form-control datepicker " value="{{ old('date_to') }}" required>
            </div>

            <div class="col-md-4">
                <label for="location_id" class="form-label fw-semibold">
                    <i class="fas fa-map-marker-alt me-1 text-success"></i> Location/Venue <span class="text-danger">*</span>
                </label>
                <select name="location_id[]" id="location_id" class="form-select border-success" multiple required>
                    @foreach($locations as $location)
                    <option value="{{ $location->name }}" {{ in_array($location->name, old('location_name', [])) ? 'selected' : '' }}>
                        {{ $location->name }}
                    </option>
                    @endforeach
                </select>
                <small class="text-muted">Hold CTRL or CMD to select multiple locations</small>
            </div>


            <div class="col-md-4">
                <label for="total_participants" class="form-label fw-semibold">
                    <i class="fas fa-users me-1 text-success"></i> Total Participants <span class="text-danger">*</span>
                </label>
                <input type="number" name="total_participants" id="total_participants" class="form-control border-success" value="{{ old('total_participants') }}" min="1" required>
            </div>

            <div class="col-md-4">
                <label for="internal_participants" class="form-label fw-semibold">
                    <i class="fas fa-user-friends me-1 text-success"></i> Select Internal Participants <span class="text-danger">*</span>
                </label>
                <select name="internal_participants[]" id="internal_participants" class="form-select border-success" multiple required>
                    @foreach($staff as $member)
                    <option value="{{ $member->name }}" {{ in_array($member->name, old('internal_participants', [])) ? 'selected' : '' }}>
                        {{ $member->name }}
                    </option>
                    @endforeach
                </select>
                <small class="text-muted">You cannot select more than the total participants</small>
            </div>

            <div class="col-md-12">
                <label for="activity_request_remarks" class="form-label fw-semibold">
                    <i class="fas fa-comment-dots me-1 text-success"></i>Justification / Request for Approval <span class="text-danger">*</span>
                </label>
                <textarea name="activity_request_remarks" id="activity_request_remarks" class="form-control" rows="3" required>{{ old('activity_request_remarks') }}</textarea>
            </div>
        </div>
    </div>
</div>