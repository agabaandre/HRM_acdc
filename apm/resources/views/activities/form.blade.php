
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <label for="workplan_activity_code" class="form-label">Activity Code *</label>
        <input type="text" name="workplan_activity_code" id="workplan_activity_code" class="form-control" value="{{ old('workplan_activity_code') }}" required>
    </div>
    <div class="col-md-6">
        <label for="request_type_id" class="form-label">Request Type *</label>
        <select name="request_type_id" id="request_type_id" class="form-select" required>
            <option value="">Select</option>
            @foreach($requestTypes as $type)
                <option value="{{ $type->id }}" {{ old('request_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <label for="activity_title" class="form-label">Activity Title *</label>
        <input type="text" name="activity_title" id="activity_title" class="form-control" value="{{ old('activity_title') }}" required>
    </div>
    <div class="col-md-6">
        <label for="background" class="form-label">Background/Context *</label>
        <textarea name="background" id="background" rows="3" class="form-control" required>{{ old('background') }}</textarea>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <label for="staff_id" class="form-label">Responsible Person *</label>
        <select name="staff_id" id="staff_id" class="form-select" required>
            <option value="">Select</option>
            @foreach($staff as $member)
                <option value="{{ $member->id }}" {{ old('staff_id') == $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="date_from" class="form-label">Date From *</label>
        <input type="date" name="date_from" id="date_from" class="form-control" value="{{ old('date_from') }}" required>
    </div>
    <div class="col-md-3">
        <label for="date_to" class="form-label">Date To *</label>
        <input type="date" name="date_to" id="date_to" class="form-control" value="{{ old('date_to') }}" required>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <label for="location_id" class="form-label">Location/Venue *</label>
        <select name="location_id[]" id="location_id" class="form-select multiple-select" required>
            @foreach($locations as $location)
                <option value="{{ $location->id }}" {{ in_array($location->id, old('location_id', [])) ? 'selected' : '' }}>{{ $location->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label for="total_participants" class="form-label">Total Participants *</label>
        <input type="number" name="total_participants" id="total_participants" class="form-control" value="{{ old('total_participants') }}" min="1" required>
    </div>
    <div class="col-md-4">
        <label for="external_participants" class="form-label">External Participants</label>
        <input type="number" name="external_participants" id="external_participants" class="form-control" value="{{ old('external_participants', 0) }}" min="0">
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-12">
        <label for="internal_participants" class="form-label">Internal Participants *</label>
        <select name="internal_participants[]" id="internal_participants" class="form-select multiple-select" multiple required>
            @foreach($staff as $member)
                <option value="{{ $member->id }}" {{ in_array($member->id, old('internal_participants', [])) ? 'selected' : '' }}>{{ $member->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <label for="activity_request_remarks" class="form-label">Request for Approval *</label>
        <textarea name="activity_request_remarks" id="activity_request_remarks" class="form-control" rows="3" required>{{ old('activity_request_remarks') }}</textarea>
    </div>

</div>
