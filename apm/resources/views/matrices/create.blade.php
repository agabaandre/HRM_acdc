@extends('layouts.app')

@section('title', isset($editing) && $editing ? 'Edit Matrix' : 'Create Matrix')

@section('header', isset($editing) && $editing ? 'Edit Matrix' : 'Create New Matrix')

@section('content')
@php
    $isAdmin = user_session('user_role') == 10;
    $userDivisionId = user_session('division_id') ?? 0;
    $defaultFocal = old('focal_person_id', user_session('focal_person'));
    // Use the values passed from controller instead of calculating here
    $currentYear = $currentYear ?? date('Y');
    $currentMonth = date('n');
    $currentQuarter = $currentQuarter ?? 'Q' . ceil($currentMonth / 3);
    $nextQuarter = $nextQuarter ?? null;
    $nextYear = $nextYear ?? $currentYear;
    
    // Control for quarter/year selection
    // Set ALLOW_QUARTER_CONTROL in .env to true to allow all quarters/years
    $allowQuarterControl = env('ALLOW_QUARTER_CONTROL', false);
@endphp

<div class="card shadow-lg border-0 mb-5 bg-light">
    <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
        <h4 class="mb-0 text-success fw-bold"><i class="bx bx-grid-alt me-2 text-success"></i> New Matrix</h4>
        <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back text-dark"></i> Back to List
        </a>
    </div>
    <div class="card-body px-5 py-5">
        @php
            $userDivisionId = user_session('division_id') ?? 0;
            $existingMatricesForUser = $existingMatrices[$userDivisionId] ?? collect();
            $nextAvailableQuarter = $nextAvailableQuarters[$userDivisionId] ?? null;
        @endphp

        <form action="{{ isset($editing) && $editing ? route('matrices.update', $matrix) : route('matrices.store') }}" method="POST" id="matrixForm">
            @csrf
            @if(isset($editing) && $editing)
                @method('PUT')
            @endif
           
            <!-- Information Panel for One Quarter Ahead -->
            <div class="alert alert-info border-0 shadow-sm mb-4">
                <div class="d-flex align-items-start">
                    <i class="bx bx-info-circle me-3 text-info" style="font-size: 1.5rem; margin-top: 0.2rem;"></i>
                    <div>
                        <h6 class="mb-2 fw-bold text-info">Matrix Creation Guidelines</h6>
                        <p class="mb-2">You can create a matrix for:</p>
                        <ul class="mb-0 ps-3">
                            <li><strong>Current Quarter:</strong> {{ $currentQuarter ?? 'Q1' }} {{ $currentYear ?? date('Y') }}</li>
                            @if(isset($nextQuarter) && $nextQuarter)
                                <li><strong>Next Quarter:</strong> {{ $nextQuarter }} {{ $nextYear > ($currentYear ?? date('Y')) ? $nextYear : ($currentYear ?? date('Y')) }}</li>
                            @endif
                        </ul>
                        @if(isset($nextQuarter) && $nextQuarter)
                            <small class="text-muted mt-2 d-block">
                                <i class="bx bx-lightbulb me-1"></i>
                                Planning ahead? Create your matrix for {{ $nextQuarter }} to get an early start on next quarter's activities.
                            </small>
                        @endif
                    </div>
                </div>
            </div>
         
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label for="year" class="form-label fw-semibold">
                        Year <span class="text-danger">*</span>
                        @if(!$allowQuarterControl)
                            <span class="badge bg-info ms-2">Current or Next Year</span>
                        @endif
                    </label>
                    <select name="year" id="year" class="form-select @error('year') is-invalid @enderror shadow-sm" required @if(!$allowQuarterControl) readonly @endif>
                        @if($allowQuarterControl)
                            <option value="">Select Year</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ old('year', isset($editing) && $editing ? $matrix->year : ($currentYear ?? date('Y'))) == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        @else
                            <option value="{{ isset($editing) && $editing ? $matrix->year : ($currentYear ?? date('Y')) }}" selected>{{ isset($editing) && $editing ? $matrix->year : ($currentYear ?? date('Y')) }} ({{ isset($editing) && $editing ? 'Selected' : 'Current' }} Year)</option>
                            @if(isset($nextYear) && $nextYear > ($currentYear ?? date('Y')))
                                <option value="{{ $nextYear }}">{{ $nextYear }} (Next Year)</option>
                            @endif
                        @endif
                    </select>
                    @if(!$allowQuarterControl && isset($nextQuarter) && $nextQuarter && $nextYear > ($currentYear ?? date('Y')))
                        <small class="form-text text-muted">
                            <i class="bx bx-lightbulb me-1"></i>
                            Next quarter ({{ $nextQuarter }}) will be in {{ $nextYear }}
                        </small>
                    @endif
                    @error('year')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="quarter" class="form-label fw-semibold">
                        Quarter <span class="text-danger">*</span>
                        @if(!$allowQuarterControl)
                            <span class="badge bg-info ms-2">Current or Next Quarter</span>
                        @endif
                    </label>
                    <select name="quarter" id="quarter" class="form-select @error('quarter') is-invalid @enderror shadow-sm" required @if(!$allowQuarterControl) readonly @endif>
                        @if($allowQuarterControl)
                            <option value="">Select Quarter</option>
                            <option value="Q1" {{ old('quarter', isset($editing) && $editing ? $matrix->quarter : ($currentQuarter ?? 'Q1')) == 'Q1' ? 'selected' : '' }}>Q1</option>
                            <option value="Q2" {{ old('quarter', isset($editing) && $editing ? $matrix->quarter : ($currentQuarter ?? 'Q1')) == 'Q2' ? 'selected' : '' }}>Q2</option>
                            <option value="Q3" {{ old('quarter', isset($editing) && $editing ? $matrix->quarter : ($currentQuarter ?? 'Q1')) == 'Q3' ? 'selected' : '' }}>Q3</option>
                            <option value="Q4" {{ old('quarter', isset($editing) && $editing ? $matrix->quarter : ($currentQuarter ?? 'Q1')) == 'Q4' ? 'selected' : '' }}>Q4</option>
                        @else
                            <option value="{{ isset($editing) && $editing ? $matrix->quarter : ($currentQuarter ?? 'Q1') }}" selected>{{ isset($editing) && $editing ? $matrix->quarter : ($currentQuarter ?? 'Q1') }} ({{ isset($editing) && $editing ? 'Selected' : 'Current' }} Quarter)</option>
                            @if(isset($nextQuarter) && $nextQuarter)
                                <option value="{{ $nextQuarter }}">{{ $nextQuarter }} (Next Quarter)</option>
                            @endif
                        @endif
                    </select>
                    @if(!$allowQuarterControl && isset($nextQuarter) && $nextQuarter)
                        <small class="form-text text-muted">
                            <i class="bx bx-lightbulb me-1"></i>
                            You can create a matrix for the current quarter ({{ $currentQuarter ?? 'Q1' }}) or the next quarter ({{ $nextQuarter }}{{ $nextYear > ($currentYear ?? date('Y')) ? ' ' . $nextYear : '' }})
                        </small>
                    @endif
                    @error('quarter')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        
            <!-- Quick Actions -->
            @if(isset($nextAvailableQuarter) && $nextAvailableQuarter && !$allowQuarterControl)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex align-items-center">
                        <button type="button" id="useNextQuarter" class="btn btn-outline-info btn-sm me-3">
                            <i class="bx bx-fast-forward me-1"></i> Use Next Available Quarter ({{ $nextAvailableQuarter }})
                        </button>
                        <small class="text-muted">
                            <i class="bx bx-info-circle me-1"></i>
                            Click to automatically select the next available quarter for your division
                        </small>
                    </div>
                </div>
            </div>
            @endif
         
            <div class="mb-4">
                <button type="button" id="addArea" class="btn btn-md btn-outline-success btn-lg rounded-pill shadow-sm mb-3">
                    <i class="bx bx-plus-circle text-success me-1"></i> Add Key Result Area
                </button>
            </div>
            <div class="alert alert-info border-0 shadow-sm mb-4">
                <i class="bx bx-info-circle me-2"></i>
                Please add at least one <strong>Key Result Area</strong> for this matrix. Each area should describe a major expected outcome for the quarter.
            </div>
            <!-- Key Result Areas -->
            <div id="keyResultAreas">
                @if(old('key_result_area'))
                    @foreach(old('key_result_area') as $index => $area)
                    <div class="key-result-area mb-4">
                        <div class="card matrix-card border-0 shadow-sm bg-white">
                            <div class="card-header bg-opacity-10 d-flex justify-content-between align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-bullseye me-2"></i>Result Area #{{ $index + 1 }}</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill remove-area">
                                    <i class="bx bx-trash text-danger me-1"></i> Remove
                                </button>
                            </div>
                            <div class="card-body p-4">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="key_result_area[{{ $index }}][description]" class="form-control shadow-sm" rows="3" placeholder="Describe this key result area" required>{{ $area['description'] ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @elseif(isset($editing) && $editing && isset($matrix->key_result_area) && is_array($matrix->key_result_area))
                    @foreach($matrix->key_result_area as $index => $area)
                    <div class="key-result-area mb-4">
                        <div class="card matrix-card border-0 shadow-sm bg-white">
                            <div class="card-header bg-opacity-10 d-flex justify-content-between align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-bullseye me-2"></i>Result Area #{{ $index + 1 }}</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill remove-area">
                                    <i class="bx bx-trash text-danger me-1"></i> Remove
                                </button>
                            </div>
                            <div class="card-body p-4">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="key_result_area[{{ $index }}][description]" class="form-control shadow-sm" rows="3" placeholder="Describe this key result area" required>{{ $area['description'] ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="key-result-area mb-4">
                        <div class="card matrix-card border-0 shadow-sm bg-white">
                            <div class="card-header bg-opacity-10 d-flex justify-content-between align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-bullseye me-2"></i>Result Area #1</h6>
                            </div>
                            <div class="card-body p-4">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="key_result_area[0][description]" class="form-control shadow-sm" rows="3" placeholder="Describe this key result area" required></textarea>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            @if(isfocal_person())
            <div class="d-flex justify-content-between border-top pt-4 mt-4">
                <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary px-4 rounded-pill shadow-sm">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success px-5 rounded-pill shadow-sm">
                    <i class="bx bx-save me-2"></i> {{ isset($editing) && $editing ? 'Update Matrix' : 'Create Matrix' }}
                </button>
            </div>
            @endif
        </form>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function () {
        let areaIndex = {{ old('key_result_area') ? count(old('key_result_area')) : (isset($editing) && $editing && isset($matrix->key_result_area) && is_array($matrix->key_result_area) ? count($matrix->key_result_area) : 1) }};
        
        // Get existing matrices data for validation
        const existingMatrices = @json($existingMatricesForUser ? $existingMatricesForUser->pluck('quarter', 'year')->toArray() : []);
        const userDivisionId = {{ $userDivisionId ?? 0 }};
        const isEditing = {{ isset($editing) && $editing ? 'true' : 'false' }};
        const currentMatrixId = {{ isset($editing) && $editing ? $matrix->id : 'null' }};
        const allowQuarterControl = {{ $allowQuarterControl ? 'true' : 'false' }};
        
        // Function to check if matrix already exists (excluding current matrix when editing)
        function checkMatrixExists(year, quarter) {
            if (existingMatrices[year] && existingMatrices[year].includes(quarter)) {
                // When editing, don't show warning for the current matrix
                if (isEditing && currentMatrixId) {
                    return false;
                }
                return true;
            }
            return false;
        }
        
        // Function to show warning for duplicate matrix
        function showDuplicateWarning(year, quarter) {
            const warningHtml = `
                <div class="alert alert-warning border-0 shadow-sm mb-4" id="duplicateWarning">
                    <div class="d-flex align-items-center">
                        <i class="bx bx-error-circle me-3 text-warning" style="font-size: 1.5rem;"></i>
                        <div>
                            <h6 class="mb-2 fw-bold text-warning">Matrix Already Exists!</h6>
                            <p class="mb-0">A matrix for <strong>${year} ${quarter}</strong> already exists for your division. 
                            You cannot create duplicate matrices for the same division, year, and quarter combination.</p>
                        </div>
                    </div>
                </div>`;
            
            // Remove existing warning if any
            $('#duplicateWarning').remove();
            
            // Insert warning before the form
            $('#matrixForm').before(warningHtml);
            
            // Disable submit button
            $('#matrixForm button[type="submit"]').prop('disabled', true);
        }
        
        // Function to hide warning and enable submit
        function hideDuplicateWarning() {
            $('#duplicateWarning').remove();
            $('#matrixForm button[type="submit"]').prop('disabled', false);
        }
        
        // Check for duplicates when year or quarter changes
        $('#year, #quarter').change(function() {
            const year = $('#year').val();
            const quarter = $('#quarter').val();
            
            // Only auto-update year if quarter is changed and quarter control is not allowed
            if (!allowQuarterControl && quarter && !year) {
                if (quarter === 'Q1' && '{{ $currentQuarter }}' === 'Q4') {
                    // If selecting Q1 and current quarter is Q4, set year to next year
                    $('#year').val('{{ $nextYear }}').trigger('change');
                } else {
                    // Otherwise set to current year
                    $('#year').val('{{ $currentYear }}').trigger('change');
                }
            }
            
            if (year && quarter) {
                if (checkMatrixExists(year, quarter)) {
                    showDuplicateWarning(year, quarter);
                } else {
                    hideDuplicateWarning();
                }
            }
        });
        
        // Check on page load if values are pre-selected
        if ($('#year').val() && $('#quarter').val()) {
            const year = $('#year').val();
            const quarter = $('#quarter').val();
            if (checkMatrixExists(year, quarter)) {
                showDuplicateWarning(year, quarter);
            }
        }
        
        // Form submission handler
        $('#matrixForm').submit(function(e) {
            const year = $('#year').val();
            const quarter = $('#quarter').val();
            
            if (year && quarter && checkMatrixExists(year, quarter)) {
                e.preventDefault();
                showDuplicateWarning(year, quarter);
                
                // Scroll to warning
                $('html, body').animate({
                    scrollTop: $('#duplicateWarning').offset().top - 100
                }, 500);
                
                return false;
            }
        });
        
        // Handle "Use Next Quarter" button click
        $('#useNextQuarter').click(function() {
            const nextQuarter = '{{ $nextAvailableQuarter ?? "" }}';
            if (nextQuarter && nextQuarter !== '') {
                $('#quarter').val(nextQuarter).trigger('change');
                
                // Auto-set the year if needed
                if (nextQuarter === 'Q1' && '{{ $currentQuarter }}' === 'Q4') {
                    $('#year').val('{{ $nextYear }}').trigger('change');
                } else {
                    $('#year').val('{{ $currentYear }}').trigger('change');
                }
                
                // Show success message
                const successHtml = `
                    <div class="alert alert-success border-0 shadow-sm mb-4" id="quarterSelected">
                        <div class="d-flex align-items-center">
                            <i class="bx bx-check-circle me-3 text-success" style="font-size: 1.5rem;"></i>
                            <div>
                                <h6 class="mb-0 fw-bold text-success">Quarter Selected!</h6>
                                <p class="mb-0">Successfully selected <strong>${nextQuarter} ${nextQuarter === 'Q1' && '{{ $currentQuarter }}' === 'Q4' ? '{{ $nextYear }}' : '{{ $currentYear }}'}</strong> for your new matrix.</p>
                            </div>
                        </div>
                    </div>`;
                
                // Remove existing success message if any
                $('#quarterSelected').remove();
                
                // Insert success message before the form
                $('#matrixForm').before(successHtml);
                
                // Auto-hide after 3 seconds
                setTimeout(function() {
                    $('#quarterSelected').fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        });

        $('#addArea').click(function () {
            const newArea = `
                <div class="key-result-area mb-4" style="display: none;">
                    <div class="card matrix-card border shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-semibold text-success">Result Area #${areaIndex + 1}</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-area">
                                <i class="bx bx-trash text-danger me-1"></i> Remove
                            </button>
                        </div>
                        <div class="card-body p-4">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="key_result_area[${areaIndex}][description]"
                                      class="form-control"
                                      rows="3"
                                      placeholder="Describe this key result area"
                                      required></textarea>
                        </div>
                    </div>
                </div>`;
            const $newArea = $(newArea);
            $('#keyResultAreas').append($newArea);
            $newArea.slideDown(300);
            areaIndex++;
        });

        $(document).on('click', '.remove-area', function () {
            const count = $('.key-result-area').length;
            if (count > 1) {
                const $area = $(this).closest('.key-result-area');
                $area.slideUp(300, function () {
                    $area.remove();
                    $('.key-result-area').each(function (idx) {
                        $(this).find('h6').text(`Result Area #${idx + 1}`);
                    });
                    areaIndex--;
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cannot Remove',
                    text: 'At least one key result area is required.',
                    confirmButtonColor: '#119A48'
                });
            }
        });

        $('#matrixForm').on('submit', function (e) {
            if ($('.key-result-area').length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'At least one key result area is required.',
                    confirmButtonColor: '#119A48'
                });
                return false;
            }

            const btn = $(this).find('button[type="submit"]');
            btn.html('<i class="bx bx-loader bx-spin me-2"></i> Creating...').prop('disabled', true);
            return true;
        });
    });
</script>
@endpush

@push('styles')
<style>
    body, .bg-light {
        background: linear-gradient(135deg, #f8fafc 0%, #e9f7ef 100%) !important;
    }
    .matrix-stepper {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 2rem;
    }
    .matrix-stepper .step {
        background: #fff;
        border: 2px solid #119A48;
        color: #119A48;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
        box-shadow: 0 2px 8px rgba(17,154,72,0.08);
        margin: 0 0.5rem;
    }
    .matrix-stepper .step.active {
        background: #119A48;
        color: #fff;
    }
    .matrix-section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #119A48;
        margin-bottom: 1.5rem;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
    }
    .matrix-section-title i {
        font-size: 2rem;
        margin-right: 0.75rem;
    }
    .matrix-info-panel {
        background: #e9f7ef;
        border-left: 5px solid #119A48;
        border-radius: 0.5rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 2rem;
        color: #119A48;
        font-size: 1.1rem;
        box-shadow: 0 2px 8px rgba(17,154,72,0.04);
    }
    .matrix-card {
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 4px 24px rgba(17,154,72,0.08);
        border: none;
    }
    .matrix-card .card-header {
        border-radius: 1.25rem 1.25rem 0 0;
        background: linear-gradient(90deg, #e9f7ef 0%, #fff 100%);
        border-bottom: 1px solid #e9f7ef;
    }
    .matrix-card .card-body {
        border-radius: 0 0 1.25rem 1.25rem;
    }
    .key-result-area {
        animation: fadeIn 0.5s;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .form-control, .form-select {
        border-radius: 0.75rem !important;
        box-shadow: 0 1px 4px rgba(17,154,72,0.04);
        border: 1px solid #d1e7dd;
        font-size: 1.08rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: #119A48;
        box-shadow: 0 0 0 0.2rem rgba(17,154,72,0.10);
    }
    .btn-success, .btn-outline-success {
        transition: background 0.2s, color 0.2s, box-shadow 0.2s;
    }
    .btn-success:hover, .btn-success:focus {
        background: #0e7c39 !important;
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(17,154,72,0.12);
    }
    .btn-outline-success:hover, .btn-outline-success:focus {
        background: #119A48 !important;
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(17,154,72,0.10);
    }
    .btn-outline-danger:hover, .btn-outline-danger:focus {
        background: #e74c3c !important;
        color: #fff !important;
    }
    .rounded-pill {
        border-radius: 2rem !important;
    }
</style>
@endpush
@endsection
