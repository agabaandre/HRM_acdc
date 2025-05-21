@extends('layouts.app')

@section('title', 'Edit Matrix')

@section('header', 'Edit Matrix')

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
        <form action="{{ route('matrices.update', $matrix) }}" method="POST" id="matrixForm">
            @csrf
            @method('PUT')

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="form-group position-relative">
                        <label for="year" class="form-label fw-semibold"><i class="bx bx-calendar-alt me-1 text-primary"></i>Year <span class="text-danger">*</span></label>
                        <select name="year" id="year" class="form-select form-select-lg @error('year') is-invalid @enderror" required>
                            <option value="">Select Year</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ old('year', $matrix->year) == $year ? 'selected' : '' }}>
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
                        <label for="quarter" class="form-label fw-semibold"><i class="bx bx-calendar-week me-1 text-primary"></i>Quarter <span class="text-danger">*</span></label>
                        <select name="quarter" id="quarter" class="form-select form-select-lg @error('quarter') is-invalid @enderror" required>
                            <option value="">Select Quarter</option>
                            @foreach($quarters as $quarter)
                                <option value="{{ $quarter }}" {{ old('quarter', $matrix->quarter) == $quarter ? 'selected' : '' }}>
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
                        <label for="division_id" class="form-label fw-semibold"><i class="bx bx-building me-1 text-primary"></i>Division <span class="text-danger">*</span></label>
                        <select name="division_id" id="division_id" class="form-select form-select-lg select2 @error('division_id') is-invalid @enderror" required>
                            <option value="">Select Division</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->id }}" {{ old('division_id', $matrix->division_id) == $division->id ? 'selected' : '' }}>
                                    {{ $division->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('division_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                

                <div class="col-md-3">
                    <div class="form-group position-relative">
                        <label for="focal_person_id" class="form-label fw-semibold"><i class="bx bx-user-voice me-1 text-primary"></i>Focal Person <span class="text-danger">*</span></label>
                        <select name="focal_person_id" id="focal_person_id" class="form-select form-select-lg select2 @error('focal_person_id') is-invalid @enderror" required>
                            <option value="">Select Focal Person</option>
                            @foreach($focalPersons as $person)
                                <option value="{{ $person->id }}" {{ old('focal_person_id', $matrix->focal_person_id) == $person->id ? 'selected' : '' }} data-division-id="{{ $person->division_id }}">
                                    {{ $person->name }} {{ $person->division ? '('.$person->division->division_name.')' : '' }}
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

            <div class="row g-4 mb-4">
                
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-semibold m-0"><i class="bx bx-target-lock text-primary me-2"></i>Key Result Areas <span class="text-danger">*</span></h5>
                    <hr class="flex-grow-1 mx-3">
                </div>
                <div id="keyResultAreas">
                    @foreach(old('key_result_area', $matrix->key_result_area) as $index => $area)
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
                                               required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><i class="bx bx-detail me-1 text-primary"></i>Description</label>
                                        <textarea name="key_result_area[{{ $index }}][description]"
                                                  class="form-control"
                                                  rows="2"
                                                  required>{{ $area['description'] ?? '' }}</textarea>
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold"><i class="bx bx-bullseye me-1 text-primary"></i>Expected Results</label>
                                        <textarea name="key_result_area[{{ $index }}][targets]"
                                                  class="form-control"
                                                  rows="2"
                                                  required>{{ $area['targets'] ?? '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="text-center mt-3">
                    <button type="button" class="btn btn-outline-primary btn-lg" id="addArea">
                        <i class="bx bx-plus-circle me-1"></i> Add Key Result Area
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary px-4 btn-lg">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                    <i class="bx bx-save me-1"></i> Update Matrix
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        let areaIndex = {{ count($matrix->key_result_area) }};

        // Add new key result area
        $('#addArea').click(function() {
            const newArea = `
                <div class="key-result-area mb-4">
                    <div class="card border shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-semibold">Result Area #${$('.key-result-area').length + 1}</h6>
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
                                       required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="bx bx-detail me-1 text-primary"></i>Description</label>
                                <textarea name="key_result_area[${areaIndex}][description]"
                                          class="form-control"
                                          rows="2"
                                          required></textarea>
                            </div>
                            <div>
                                <label class="form-label fw-semibold"><i class="bx bx-bullseye me-1 text-primary"></i>Expected Results</label>
                                <textarea name="key_result_area[${areaIndex}][targets]"
                                          class="form-control"
                                          rows="2"
                                          required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#keyResultAreas').append(newArea);
            areaIndex++;
        });

        // Remove key result area
        $(document).on('click', '.remove-area', function() {
            const areasCount = $('.key-result-area').length;
            if (areasCount > 1) {
                $(this).closest('.key-result-area').remove();
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

        // Form submission
        $('#matrixForm').on('submit', function() {
            if ($('.key-result-area').length === 0) {
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
            
            // If there's a designated focal person for this division, select it (unless editing with existing selection)
            const divisionFocalPerson = divisionFocalPersons[divisionId];
            const currentFocalPerson = focalPersonSelect.val();
            
            // Only auto-select if no focal person is currently selected
            if (!currentFocalPerson && divisionFocalPerson) {
                // Check if the option exists and is visible
                const focalPersonOption = focalPersonSelect.find(`option[value="${divisionFocalPerson}"]`);
                if (focalPersonOption.length && divisionStaffIds.includes(parseInt(divisionFocalPerson))) {
                    focalPersonSelect.val(divisionFocalPerson);
                }
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
