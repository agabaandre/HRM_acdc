@extends('layouts.app')

@section('title', 'Create Matrix')

@section('header', 'Create New Matrix')


@section('header-actions')
<a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to List
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-grid-alt me-2 text-primary"></i>Matrix Details</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('matrices.store') }}" method="POST" id="matrixForm">
            @csrf

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="form-group position-relative">
                        <label for="year" class="form-label fw-semibold"><i class="bx bx-calendar me-1 text-primary"></i>Year <span class="text-danger">*</span></label>
                        <select name="year" id="year" class="form-select form-select-lg @error('year') is-invalid @enderror" required>
                            <option value="">Select Year</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ old('year') == $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                        @error('year')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group position-relative">
                        <label for="quarter" class="form-label fw-semibold"><i class="bx bx-grid-small me-1 text-primary"></i>Quarter <span class="text-danger">*</span></label>
                        <select name="quarter" id="quarter" class="form-select form-select-lg @error('quarter') is-invalid @enderror" required>
                            <option value="">Select Quarter</option>
                            @foreach($quarters as $quarter)
                                <option value="{{ $quarter }}" {{ old('quarter') == $quarter ? 'selected' : '' }}>
                                    {{ $quarter }}
                                </option>
                            @endforeach
                        </select>
                        @error('quarter')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="division_id" class="form-label fw-semibold"><i class="bx bx-building me-1 text-primary"></i>Division <span class="text-danger">*</span></label>
                        <select name="division_id" id="division_id" class="form-select form-select-lg @error('division_id') is-invalid @enderror" required>
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
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="staff_id" class="form-label fw-semibold"><i class="bx bx-user me-1 text-primary"></i>Staff <span class="text-danger">*</span></label>
                        <select name="staff_id" id="staff_id" class="form-select form-select-lg @error('staff_id') is-invalid @enderror" required>
                            <option value="">Select Staff</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" {{ old('staff_id') == $member->id ? 'selected' : '' }}>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted mt-1 d-block">Primary staff member responsible for this matrix</small>
                        @error('staff_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="focal_person_id" class="form-label fw-semibold"><i class="bx bx-user-voice me-1 text-primary"></i>Focal Person <span class="text-danger">*</span></label>
                        <select name="focal_person_id" id="focal_person_id" class="form-select form-select-lg @error('focal_person_id') is-invalid @enderror" required>
                            <option value="">Select Focal Person</option>
                            @foreach($staff as $person)
                                <option value="{{ $person->id }}" {{ old('focal_person_id') == $person->id ? 'selected' : '' }}>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted mt-1 d-block">Person who will coordinate activities in this matrix</small>
                        @error('focal_person_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-semibold m-0"><i class="bx bx-target-lock text-primary me-2"></i>Key Result Areas <span class="text-danger">*</span></h5>
                    <hr class="flex-grow-1 mx-3">
                </div>
                <div id="keyResultAreas" class="mb-4">
                    @if(old('key_result_area'))
                        @foreach(old('key_result_area') as $index => $area)
                            <div class="key-result-area mb-4">
                                <div class="card border shadow-sm">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h6 class="m-0 fw-semibold">Result Area #{{ $index + 1 }}</h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-area">
                                            <i class="bx bx-trash me-1"></i> Remove
                                        </button>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold"><i class="bx bx-heading me-1 text-primary"></i>Title</label>
                                            <input type="text"
                                                   name="key_result_area[{{ $index }}][title]"
                                                   class="form-control form-control-lg"
                                                   value="{{ $area['title'] ?? '' }}"
                                                   placeholder="Enter area title"
                                                   required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold"><i class="bx bx-detail me-1 text-primary"></i>Description</label>
                                            <textarea name="key_result_area[{{ $index }}][description]"
                                                      class="form-control"
                                                      rows="3"
                                                      placeholder="Describe this key result area"
                                                      required>{{ $area['description'] ?? '' }}</textarea>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold"><i class="bx bx-bullseye me-1 text-primary"></i>Expected Results</label>
                                            <textarea name="key_result_area[{{ $index }}][targets]"
                                                      class="form-control"
                                                      rows="3"
                                                      placeholder="What are the expected results/outcomes?"
                                                      required>{{ $area['targets'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="key-result-area mb-4">
                            <div class="card border shadow-sm">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 fw-semibold">Result Area #1</h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-area">
                                        <i class="bx bx-trash me-1"></i> Remove
                                    </button>
                                </div>
                                <div class="card-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><i class="bx bx-heading me-1 text-primary"></i>Title</label>
                                        <input type="text"
                                               name="key_result_area[0][title]"
                                               class="form-control form-control-lg"
                                               placeholder="Enter area title"
                                               required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><i class="bx bx-detail me-1 text-primary"></i>Description</label>
                                        <textarea name="key_result_area[0][description]"
                                                  class="form-control"
                                                  rows="3"
                                                  placeholder="Describe this key result area"
                                                  required></textarea>
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold"><i class="bx bx-bullseye me-1 text-primary"></i>Expected Results</label>
                                        <textarea name="key_result_area[0][targets]"
                                                  class="form-control"
                                                  rows="3"
                                                  placeholder="What are the expected results/outcomes?"
                                                  required></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="text-center mt-3">
                    <button type="button" id="addArea" class="btn btn-outline-primary btn-lg">
                        <i class="bx bx-plus-circle me-1"></i> Add Key Result Area
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary px-4">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                    <i class="bx bx-save me-2"></i> Create Matrix
                </button>
            </div>
        </form>
    </div>
</div>


@push('scripts')

<!-- Import Select2, SweetAlert -->
<link href="{{ asset('assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
<script src="{{ asset('assets/libs/select2/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script type="text/javascript">
    $(document).ready(function() {
        let areaIndex = {{ old('key_result_area') ? count(old('key_result_area')) : 1 }};

// Initialize Select2 for better dropdown UX
$('.form-select').select2({
    dropdownParent: $('#matrixForm'),
});

// Add new key result area with animation
$('#addArea').click(function() {
            const newArea = `
                <div class="key-result-area mb-4" style="display: none;">
                    <div class="card border shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-semibold">Result Area #${areaIndex + 1}</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-area">
                                <i class="bx bx-trash me-1"></i> Remove
                            </button>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="bx bx-heading me-1 text-primary"></i>Title</label>
                                <input type="text"
                                       name="key_result_area[${areaIndex}][title]"
                                       class="form-control form-control-lg"
                                       placeholder="Enter area title"
                                       required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="bx bx-detail me-1 text-primary"></i>Description</label>
                                <textarea name="key_result_area[${areaIndex}][description]"
                                          class="form-control"
                                          rows="3"
                                          placeholder="Describe this key result area"
                                          required></textarea>
                            </div>
                            <div>
                                <label class="form-label fw-semibold"><i class="bx bx-bullseye me-1 text-primary"></i>Expected Results</label>
                                <textarea name="key_result_area[${areaIndex}][targets]"
                                          class="form-control"
                                          rows="3"
                                          placeholder="What are the expected results/outcomes?"
                                          required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            const $newArea = $(newArea);
            $('#keyResultAreas').append($newArea);
            $newArea.slideDown(300);
            areaIndex++;

            // Scroll to the new area
            $('html, body').animate({
                scrollTop: $newArea.offset().top - 100
            }, 300);
        });

        // Remove key result area with animation
        $(document).on('click', '.remove-area', function() {
            const areasCount = $('.key-result-area').length;
            if (areasCount > 1) {
                const $area = $(this).closest('.key-result-area');
                $area.slideUp(300, function() {
                    $area.remove();
                    // Update the numbering of remaining areas
                    $('.key-result-area').each(function(idx) {
                        $(this).find('h6').text(`Result Area #${idx + 1}`);
                    });
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cannot Remove',
                    text: 'At least one key result area is required.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: '<i class="bx bx-check me-1"></i> OK',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }
        });

        // Form validation and submission with sweet alert
        $('#matrixForm').on('submit', function(e) {
            if ($('.key-result-area').length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'At least one key result area is required.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: '<i class="bx bx-check me-1"></i> OK',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
                return false;
            }

            // Show loading indicator
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="bx bx-loader bx-spin me-2"></i> Creating...');
            submitBtn.prop('disabled', true);

            return true;
        });
    });
</script>
@endpush

@endsection
