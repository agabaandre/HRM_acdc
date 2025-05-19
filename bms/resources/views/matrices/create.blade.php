@extends('layouts.app')

@section('title', 'Create Matrix')

@section('header', 'Create New Matrix')

@section('header-actions')
<a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to List
</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Matrix Details</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('matrices.store') }}" method="POST" id="matrixForm">
            @csrf

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                        <select name="year" id="year" class="form-select @error('year') is-invalid @enderror" required>
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
                    <div class="form-group">
                        <label for="quarter" class="form-label">Quarter <span class="text-danger">*</span></label>
                        <select name="quarter" id="quarter" class="form-select @error('quarter') is-invalid @enderror" required>
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
                    <div class="form-group">
                        <label for="division_id" class="form-label">Division <span class="text-danger">*</span></label>
                        <select name="division_id" id="division_id" class="form-select select2 @error('division_id') is-invalid @enderror" required>
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

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="staff_id" class="form-label">Staff <span class="text-danger">*</span></label>
                        <select name="staff_id" id="staff_id" class="form-select select2 @error('staff_id') is-invalid @enderror" required>
                            <option value="">Select Staff</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" {{ old('staff_id') == $member->id ? 'selected' : '' }}>
                                    {{ $member->fname }} {{ $member->lname }}
                                </option>
                            @endforeach
                        </select>
                        @error('staff_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="focal_person_id" class="form-label">Focal Person <span class="text-danger">*</span></label>
                        <select name="focal_person_id" id="focal_person_id" class="form-select select2 @error('focal_person_id') is-invalid @enderror" required>
                            <option value="">Select Focal Person</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" {{ old('focal_person_id') == $member->id ? 'selected' : '' }}>
                                    {{ $member->fname }} {{ $member->lname }}
                                </option>
                            @endforeach
                        </select>
                        @error('focal_person_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Key Result Areas <span class="text-danger">*</span></label>
                <div id="keyResultAreas">
                    @if(old('key_result_area'))
                        @foreach(old('key_result_area') as $index => $area)
                            <div class="key-result-area">
                                <div class="row">
                                    <div class="col-md-11">
                                        <div class="mb-3">
                                            <label class="form-label">Title</label>
                                            <input type="text"
                                                   name="key_result_area[{{ $index }}][title]"
                                                   class="form-control"
                                                   value="{{ $area['title'] ?? '' }}"
                                                   required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="key_result_area[{{ $index }}][description]"
                                                      class="form-control"
                                                      rows="2"
                                                      required>{{ $area['description'] ?? '' }}</textarea>
                                        </div>
                                        <div>
                                            <label class="form-label">Expected Results</label>
                                            <textarea name="key_result_area[{{ $index }}][targets]"
                                                      class="form-control"
                                                      rows="2"
                                                      required>{{ $area['targets'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-link text-danger remove-area">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="key-result-area">
                            <div class="row">
                                <div class="col-md-11">
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text"
                                               name="key_result_area[0][title]"
                                               class="form-control"
                                               required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="key_result_area[0][description]"
                                                  class="form-control"
                                                  rows="2"
                                                  required></textarea>
                                    </div>
                                    <div>
                                        <label class="form-label">Expected Results</label>
                                        <textarea name="key_result_area[0][targets]"
                                                  class="form-control"
                                                  rows="2"
                                                  required></textarea>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-link text-danger remove-area">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="text-center mt-3">
                    <button type="button" class="btn btn-outline-success" id="addArea">
                        <i class="bx bx-plus"></i> Add Key Result Area
                    </button>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save"></i> Create Matrix
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        let areaIndex = {{ old('key_result_area') ? count(old('key_result_area')) : 1 }};

        // Add new key result area
        $('#addArea').click(function() {
            const newArea = `
                <div class="key-result-area">
                    <div class="row">
                        <div class="col-md-11">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text"
                                       name="key_result_area[${areaIndex}][title]"
                                       class="form-control"
                                       required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="key_result_area[${areaIndex}][description]"
                                          class="form-control"
                                          rows="2"
                                          required></textarea>
                            </div>
                            <div>
                                <label class="form-label">Expected Results</label>
                                <textarea name="key_result_area[${areaIndex}][targets]"
                                          class="form-control"
                                          rows="2"
                                          required></textarea>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-link text-danger remove-area">
                                <i class="bx bx-trash"></i>
                            </button>
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
                alert('At least one key result area is required.');
            }
        });

        // Form submission
        $('#matrixForm').on('submit', function() {
            if ($('.key-result-area').length === 0) {
                alert('At least one key result area is required.');
                return false;
            }
            return true;
        });
    });
</script>
@endpush
@endsection
