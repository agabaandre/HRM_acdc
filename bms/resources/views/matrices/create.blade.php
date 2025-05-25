@extends('layouts.app')

@section('title', 'Create Matrix')

@section('header', 'Create New Matrix')


@section('header-actions')
<a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary">
    <i class="bx text-dark bx-arrow-back"></i> Back to List
</a>
@endsection

@section('content')
@php
    $isAdmin = user_session('user_role') == 10;
    $userDivisionId = user_session('division_id');
    $defaultFocal = old('focal_person_id', user_session('focal_person'));
   
@endphp
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx text-success bx-grid-alt me-2 text-primary"></i>Matrix Details</h5>[FW=1, RWF=2]
    </div>
    <div class="card-body p-4">
        <form action="{{ route('matrices.store') }}" method="POST" id="matrixForm">
            @csrf

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="form-group position-relative">
                        <label for="year" class="form-label fw-semibold"><i class="bx text-success bx-calendar me-1 text-primary"></i>Year <span class="text-danger">*</span></label>
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
                        <label for="quarter" class="form-label fw-semibold"><i class="bx text-success bx-grid-small me-1 text-primary"></i>Quarter <span class="text-danger">*</span></label>
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

                <div class="col-md-3">
                    <div class="form-group position-relative">
                        <label for="division_id" class="form-label fw-semibold"><i class="bx text-success bx-building me-1 text-primary"></i>Division <span class="text-danger">*</span></label>
                        <select name="division_id" id="division_id" class="form-select form-select-lg @error('division_id') is-invalid @enderror" required>
                            <option value="">Select Division</option>
                            @foreach($divisions as $division)
                                @if($isAdmin || $division->id == $userDivisionId)
                                    <option value="{{ $division->id }}" {{ $division->id == old('division_id', $userDivisionId) ? 'selected' : '' }}>
                                        {{ $division->name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('division_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>


<div class="col-md-3">
    <div class="form-group position-relative">
        <label for="focal_person_id" class="form-label fw-semibold">
            <i class="bx text-success bx-user-voice me-1 text-primary"></i>
            Focal Person <span class="text-danger">*</span>
        </label>

        <select name="focal_person_id" id="focal_person_id"
                class="form-select form-select-lg @error('focal_person_id') is-invalid @enderror"
                {{ $isAdmin ? '' : 'readonly' }} required>
            <option value="">Select Focal Person</option>
            @foreach($focalPersons as $person)
                @if($isAdmin || $person->division_id == $userDivisionId)
                    <option value="{{ $person->staff_id }}"
                            data-division-id="{{ $person->division_id }}"
                            {{ $defaultFocal == $person->staff_id ? 'selected' : '' }}>
                        {{ $person->name }} {{ $person->division ? '(' . $person->division->name . ')' : '' }}
                    </option>
                @endif
            @endforeach
        </select>

        <small class="text-muted mt-1 d-block">Person who will coordinate activities in this matrix</small>
        @error('focal_person_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

            </div>

            

            <div class="row g-4 mb-4">
                

                
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-semibold m-0"><i class="bx text-success bx-target-lock text-primary me-2"></i>Key Result Areas <span class="text-danger">*</span></h5>
                    
                </div>
                <div id="keyResultAreas" class="mb-4">
                    @if(old('key_result_area'))
                        @foreach(old('key_result_area') as $index => $area)
                            <div class="key-result-area mb-4">
                                <div class="card border shadow-sm">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h6 class="m-0 fw-semibold">Result Area #{{ $index + 1 }}</h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-area">
                                            <i class="bx text-danger bx-trash me-1"></i> Remove
                                        </button>
                                    </div>
                                    <div class="card-body p-4">
                                    
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold"><i class="bx text-success bx-detail me-1 text-primary"></i>Description</label>
                                            <textarea name="key_result_area[{{ $index }}][description]"
                                                      class="form-control"
                                                      rows="3"
                                                      placeholder="Describe this key result area"
                                                      required>{{ $area['description'] ?? '' }}</textarea>
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
                                        <i class="bx text-danger bx-trash me-1"></i> Remove
                                    </button>
                                </div>
                                <div class="card-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><i class="bx text-success bx-heading me-1 text-primary"></i>Title</label>
                                        <input type="text"
                                               name="key_result_area[0][title]"
                                               class="form-control form-control-lg"
                                               placeholder="Enter area title"
                                               required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><i class="bx text-success bx-detail me-1 text-primary"></i>Description</label>
                                        <textarea name="key_result_area[0][description]"
                                                  class="form-control"
                                                  rows="3"
                                                  placeholder="Describe this key result area"
                                                  required></textarea>
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold"><i class="bx text-success bx-bullseye me-1 text-primary"></i>Expected Results</label>
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
                    <button type="button" id="addArea" class="btn btn-sm btn-outline-success btn-lg">
                        <i class="bx text-success bx-plus-circle me-1"></i> Add Key Result Area
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary px-4">
                    <i class="bx text-success bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary btn-md px-5 shadow-sm">
                    <i class="bx text-white bx-save me-2"></i> Create Matrix
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
                                <i class="bx text-danger bx-trash me-1"></i> Remove
                            </button>
                        </div>
                        <div class="card-body p-4">
                          
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="bx text-success bx-detail me-1 text-primary"></i>Description</label>
                                <textarea name="key_result_area[${areaIndex}][description]"
                                          class="form-control"
                                          rows="3"
                                          placeholder="Describe this key result area"
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
                    confirmButtonText: '<i class="bx text-success bx-check me-1"></i> OK',
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
                    confirmButtonText: '<i class="bx text-success bx-check me-1"></i> OK',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
                return false;
            }

            // Show loading indicator
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="bx text-success bx-loader text-success bx-spin me-2"></i> Creating...');
            submitBtn.prop('disabled', true);

            return true;
        });
        
        // Division-based focal person filtering
        // Store staff by division and division focal persons data from PHP
        const staffByDivision = @json($staffByDivision);
        const divisionFocalPersons = @json($divisionFocalPersons);
        
        // Handle division selection change
        $('#division_id').on('change', function() {
            const divisionId = $(this).val();
            const focalPersonSelect = $('#focal_person_id');
            
            if (!divisionId) {
                // If no division selected, show all focal persons
                focalPersonSelect.find('option').show();
                return;
            }
            
            // Hide all options first except the placeholder
            focalPersonSelect.find('option:not(:first)').hide();
            
            // Get the staff IDs for the selected division
            const divisionStaffIds = staffByDivision[divisionId] || [];
            
            // Show only staff from the selected division
            focalPersonSelect.find('option').each(function() {
                const value = $(this).val();
                if (!value) return; // Skip placeholder
                
                if (divisionStaffIds.includes(parseInt(value))) {
                    $(this).show();
                }
            });
            
            // If there's a designated focal person for this division, select it
            const divisionFocalPerson = divisionFocalPersons[divisionId];
            if (divisionFocalPerson) {
                // Check if the option exists and is visible
                const focalPersonOption = focalPersonSelect.find(`option[value="${divisionFocalPerson}"]`);
                if (focalPersonOption.length && divisionStaffIds.includes(parseInt(divisionFocalPerson))) {
                    focalPersonSelect.val(divisionFocalPerson);
                } else {
                    // If the focal person is not in the division's staff, reset to placeholder
                    focalPersonSelect.val('');
                }
            } else {
                // Reset to placeholder if no designated focal person
                focalPersonSelect.val('');
            }
        });
        
        // Trigger the change event on page load if there's a selected division
        if ($('#division_id').val()) {
            $('#division_id').trigger('change');
        }
    });
</script>
@endpush

@endsection
