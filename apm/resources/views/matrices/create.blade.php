@extends('layouts.app')

@section('title', 'Create Matrix')

@section('header', 'Create New Matrix')

@section('header-actions')
<a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back text-dark"></i> Back to List
</a>
@endsection

@section('content')
@php
    $isAdmin = user_session('user_role') == 10;
    $userDivisionId = user_session('division_id');
    $defaultFocal = old('focal_person_id', user_session('focal_person'));
    $currentYear = date('Y');
    $currentMonth = date('n');
    $currentQuarter = 'Q' . ceil($currentMonth / 3);
   //dd(user_session());
 
@endphp

<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-success"><i class="bx bx-grid-alt me-2 text-success"></i> Matrix Details</h5>
       
    </div>

    <div class="card-body px-4 py-5">
        <form action="{{ route('matrices.store') }}" method="POST" id="matrixForm">
            @csrf

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label for="year" class="form-label fw-semibold">Year <span class="text-danger">*</span></label>
                    <select name="year" id="year" class="form-select @error('year') is-invalid @enderror" required>
                        <option value="">Select Year</option>
                        @foreach($years as $year)
                            <option value="{{ $year }}" {{ old('year', $currentYear) == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    @error('year')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="quarter" class="form-label fw-semibold">Quarter <span class="text-danger">*</span></label>
                    <select name="quarter" id="quarter" class="form-select @error('quarter') is-invalid @enderror" required>
                        <option value="">Select Quarter</option>
                        @foreach($quarters as $quarter)
                            <option value="{{ $quarter }}" {{ old('quarter', $currentQuarter) == $quarter ? 'selected' : '' }}>{{ $quarter }}</option>
                        @endforeach
                    </select>
                    @error('quarter')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <button type="button" id="addArea" class="btn btn-sm btn-outline-success mb-4">
            <i class="bx bx-plus-circle text-success me-1"></i> Add Key Result Area
            </button>
            <!-- Key Result Areas -->
            <div id="keyResultAreas">
                @if(old('key_result_area'))
                    @foreach(old('key_result_area') as $index => $area)
                    <div class="key-result-area mb-4">
                        <div class="card border shadow-sm">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="m-0 fw-semibold text-success">Result Area #{{ $index + 1 }}</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-area">
                                    <i class="bx bx-trash text-danger me-1"></i> Remove
                                </button>
                            </div>
                            <div class="card-body p-4">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="key_result_area[{{ $index }}][description]" class="form-control" rows="3" placeholder="Describe this key result area" required>{{ $area['description'] ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="key-result-area mb-4">
                        <div class="card border shadow-sm">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="m-0 fw-semibold text-success">Result Area #1</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-area">
                                    <i class="bx bx-trash text-danger me-1"></i> Remove
                                </button>
                            </div>
                            <div class="card-body p-4">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="key_result_area[0][description]" class="form-control" rows="3" placeholder="Describe this key result area" required></textarea>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if(isfocal_person())
            <div class="d-flex justify-content-between border-top pt-4 mt-4">
                <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary px-4">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success px-5 shadow-sm">
                    <i class="bx bx-save me-2"></i> Create Matrix
                </button>
            </div>
            @endif
        </form>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function () {
        let areaIndex = {{ old('key_result_area') ? count(old('key_result_area')) : 1 }};

        $('#addArea').click(function () {
            const newArea = `
                <div class="key-result-area mb-4" style="display: none;">
                    <div class="card border shadow-sm">
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
@endsection
