@extends('layouts.app')

@section('title', 'Add New Staff')

@section('header', 'Add New Staff')

@section('header-actions')
    <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
@endsection

@section('content')
    <form action="{{ route('staff.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row">
            <div class="col-md-8">
                <!-- Personal Information Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bx bx-user me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="staff_id" class="form-label fw-semibold">
                                        Staff ID <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control @error('staff_id') is-invalid @enderror"
                                        id="staff_id" name="staff_id" value="{{ old('staff_id') }}"
                                        placeholder="Enter staff ID" required>
                                    <small class="text-muted">Unique identifier for the staff member</small>
                                    @error('staff_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="firstname" class="form-label fw-semibold">
                                        First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control @error('firstname') is-invalid @enderror"
                                        id="firstname" name="firstname" value="{{ old('firstname') }}"
                                        placeholder="Enter first name" required>
                                    @error('firstname')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="middlename" class="form-label fw-semibold">
                                        Middle Name
                                    </label>
                                    <input type="text" class="form-control @error('middlename') is-invalid @enderror"
                                        id="middlename" name="middlename" value="{{ old('middlename') }}"
                                        placeholder="Enter middle name">
                                    @error('middlename')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="lastname" class="form-label fw-semibold">
                                        Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control @error('lastname') is-invalid @enderror"
                                        id="lastname" name="lastname" value="{{ old('lastname') }}"
                                        placeholder="Enter last name" required>
                                    @error('lastname')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label fw-semibold">
                                        Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                        name="email" value="{{ old('email') }}" placeholder="Enter email address" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="telno" class="form-label fw-semibold">
                                        Telephone Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control @error('telno') is-invalid @enderror" id="telno"
                                        name="telno" value="{{ old('telno') }}" placeholder="Enter telephone number"
                                        required>
                                    @error('telno')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="gender" class="form-label fw-semibold">
                                        Gender <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('gender') is-invalid @enderror" id="gender"
                                        name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                        <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female
                                        </option>
                                    </select>
                                    @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="dob" class="form-label fw-semibold">
                                        Date of Birth
                                    </label>
                                    <input type="date" class="form-control @error('dob') is-invalid @enderror" id="dob"
                                        name="dob" value="{{ old('dob') }}">
                                    @error('dob')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employment Information Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bx bx-briefcase me-2"></i>Employment Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="division_id" class="form-label fw-semibold">
                                        Division <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('division_id') is-invalid @enderror" id="division_id"
                                        name="division_id" required>
                                        <option value="">Select Division</option>
                                        @foreach($divisions as $division)
                                            <option value="{{ $division->id }}" {{ old('division_id') == $division->id ? 'selected' : '' }}>
                                                {{ $division->division_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('division_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="directorate_id" class="form-label fw-semibold">
                                        Directorate <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('directorate_id') is-invalid @enderror"
                                        id="directorate_id" name="directorate_id" required>
                                        <option value="">Select Directorate</option>
                                        @foreach($directorates as $directorate)
                                            <option value="{{ $directorate->id }}" {{ old('directorate_id') == $directorate->id ? 'selected' : '' }}>
                                                {{ $directorate->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('directorate_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="duty_station_id" class="form-label fw-semibold">
                                        Duty Station <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('duty_station_id') is-invalid @enderror"
                                        id="duty_station_id" name="duty_station_id" required>
                                        <option value="">Select Duty Station</option>
                                        @foreach($dutyStations as $dutyStation)
                                            <option value="{{ $dutyStation->id }}" {{ old('duty_station_id') == $dutyStation->id ? 'selected' : '' }}>
                                                {{ $dutyStation->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('duty_station_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="title" class="form-label fw-semibold">
                                        Job Title <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" id="title"
                                        name="title" value="{{ old('title') }}" placeholder="Enter job title" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="designation" class="form-label fw-semibold">
                                        Designation
                                    </label>
                                    <input type="text" class="form-control @error('designation') is-invalid @enderror"
                                        id="designation" name="designation" value="{{ old('designation') }}"
                                        placeholder="Enter designation">
                                    @error('designation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="employment_status" class="form-label fw-semibold">
                                        Employment Status <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('employment_status') is-invalid @enderror"
                                        id="employment_status" name="employment_status" required>
                                        <option value="">Select Status</option>
                                        <option value="Full-time" {{ old('employment_status') == 'Full-time' ? 'selected' : '' }}>Full-time</option>
                                        <option value="Part-time" {{ old('employment_status') == 'Part-time' ? 'selected' : '' }}>Part-time</option>
                                        <option value="Contract" {{ old('employment_status') == 'Contract' ? 'selected' : '' }}>Contract</option>
                                        <option value="Consultant" {{ old('employment_status') == 'Consultant' ? 'selected' : '' }}>Consultant</option>
                                    </select>
                                    @error('employment_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="hire_date" class="form-label fw-semibold">
                                        Hire Date
                                    </label>
                                    <input type="date" class="form-control @error('hire_date') is-invalid @enderror"
                                        id="hire_date" name="hire_date" value="{{ old('hire_date') }}">
                                    @error('hire_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supervisor_id" class="form-label fw-semibold">
                                        Supervisor
                                    </label>
                                    <select class="form-select @error('supervisor_id') is-invalid @enderror"
                                        id="supervisor_id" name="supervisor_id">
                                        <option value="">Select Supervisor</option>
                                        @foreach($supervisors as $supervisor)
                                            <option value="{{ $supervisor->id }}" {{ old('supervisor_id') == $supervisor->id ? 'selected' : '' }}>
                                                {{ $supervisor->firstname }} {{ $supervisor->lastname }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('supervisor_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-group">
                                <label for="remarks" class="form-label fw-semibold">
                                    Remarks
                                </label>
                                <textarea class="form-control @error('remarks') is-invalid @enderror" id="remarks"
                                    name="remarks" rows="3"
                                    placeholder="Enter additional remarks">{{ old('remarks') }}</textarea>
                                @error('remarks')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Status & Photo Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bx bx-cog me-2"></i>Status & Photo</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-4">
                            <label class="form-label fw-semibold">Active Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="active">Active</label>
                            </div>
                            <small class="text-muted">Inactive staff will not appear in active staff lists</small>
                        </div>

                        <div class="form-group mb-4">
                            <label for="profile_photo" class="form-label fw-semibold">Profile Photo</label>
                            <input type="file" class="form-control @error('profile_photo') is-invalid @enderror"
                                id="profile_photo" name="profile_photo" accept="image/*">
                            <small class="text-muted">Supported formats: JPG, PNG, GIF (Max: 2MB)</small>
                            @error('profile_photo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- System Access Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bx bx-lock me-2"></i>System Access</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-4">
                            <label for="access_level" class="form-label fw-semibold">
                                Access Level
                            </label>
                            <select class="form-select @error('access_level') is-invalid @enderror" id="access_level"
                                name="access_level">
                                <option value="">No System Access</option>
                                <option value="1" {{ old('access_level') == '1' ? 'selected' : '' }}>Basic User</option>
                                <option value="2" {{ old('access_level') == '2' ? 'selected' : '' }}>Advanced User</option>
                                <option value="3" {{ old('access_level') == '3' ? 'selected' : '' }}>Administrator</option>
                            </select>
                            <small class="text-muted">Grant system access level for this staff member</small>
                            @error('access_level')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Submission Card -->
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bx bx-save me-2"></i> Save Staff Record
                            </button>
                            <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">
                                <i class="bx bx-arrow-back me-2"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            // Add custom validation or dynamic form behavior here
        });
    </script>
@endpush