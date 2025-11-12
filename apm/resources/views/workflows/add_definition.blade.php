@extends('layouts.app')

@section('title', 'Add Workflow Definition')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Add Workflow Definition for {{ $workflow->workflow_name }}</h4>
                        <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-secondary">Back to
                            Workflow</a>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('workflows.store-definition', $workflow->id) }}" method="POST">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                            <div class="mb-3">
                                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('role') is-invalid @enderror" id="role"
                                    name="role" value="{{ old('role') }}" required>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                        <div class="form-text">Enter the role responsible for this step (e.g., Division Head, Finance Officer)</div>
                                    </div>
                            </div>
                                <div class="col-md-6">
                            <div class="mb-3">
                                        <label for="approval_order" class="form-label">Approval Order <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('approval_order') is-invalid @enderror"
                                    id="approval_order" name="approval_order" value="{{ old('approval_order') }}" min="1"
                                    required>
                                @error('approval_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                        <div class="form-text">Enter the order number for this step in the workflow (e.g., 1 for first step)</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="fund_type" class="form-label">Fund Type</label>
                                        <select class="form-select @error('fund_type') is-invalid @enderror" id="fund_type" name="fund_type">
                                            <option value="">Select Fund Type</option>
                                            @foreach($fundTypes as $fundType)
                                                <option value="{{ $fundType->id }}" {{ old('fund_type') == $fundType->id ? 'selected' : '' }}>{{ $fundType->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('fund_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <input type="text" class="form-control @error('category') is-invalid @enderror" id="category"
                                            name="category" value="{{ old('category') }}" maxlength="20">
                                        @error('category')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Category for routing (e.g., program, support)</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="memo_print_section" class="form-label">Memo Print Section</label>
                                        <select class="form-select @error('memo_print_section') is-invalid @enderror" id="memo_print_section" name="memo_print_section">
                                            <option value="through" {{ old('memo_print_section', 'through') == 'through' ? 'selected' : '' }}>Through</option>
                                            <option value="to" {{ old('memo_print_section') == 'to' ? 'selected' : '' }}>To</option>
                                            <option value="from" {{ old('memo_print_section') == 'from' ? 'selected' : '' }}>From</option>
                                            <option value="others" {{ old('memo_print_section') == 'others' ? 'selected' : '' }}>Others</option>
                                        </select>
                                        @error('memo_print_section')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="print_order" class="form-label">Print Order</label>
                                        <input type="number" class="form-control @error('print_order') is-invalid @enderror" 
                                            id="print_order" name="print_order" value="{{ old('print_order') }}" min="1">
                                        @error('print_order')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Order in which this step appears in memo printing</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="divisions" class="form-label">Divisions</label>
                                        <select class="form-select select2 @error('divisions') is-invalid @enderror" id="divisions" name="divisions[]" multiple>
                                            @foreach($divisions as $division)
                                                <option value="{{ $division->id }}" {{ in_array($division->id, old('divisions', [])) ? 'selected' : '' }}>{{ $division->division_name }}</option>
                                            @endforeach
                                        </select>
                                        @error('divisions')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Select one or more divisions for this workflow definition</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="allowed_funders" class="form-label">Allowed Funders</label>
                                        <select class="form-select select2 @error('allowed_funders') is-invalid @enderror" id="allowed_funders" name="allowed_funders[]" multiple>
                                            @foreach($funders as $funder)
                                                <option value="{{ $funder->id }}" {{ in_array($funder->id, old('allowed_funders', [])) ? 'selected' : '' }}>{{ $funder->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('allowed_funders')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Select funders allowed for this workflow definition</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="division_reference_column" class="form-label">Division Reference Column</label>
                                        <select class="form-select @error('division_reference_column') is-invalid @enderror" id="division_reference_column" name="division_reference_column">
                                            <option value="">Select Reference Column</option>
                                            <option value="division_head" {{ old('division_reference_column') == 'division_head' ? 'selected' : '' }}>Division Head</option>
                                            <option value="finance_officer" {{ old('division_reference_column') == 'finance_officer' ? 'selected' : '' }}>Finance Officer</option>
                                            <option value="director_id" {{ old('division_reference_column') == 'director_id' ? 'selected' : '' }}>Director</option>
                                            <option value="focal_person" {{ old('division_reference_column') == 'focal_person' ? 'selected' : '' }}>Focal Person</option>
                                            <option value="admin_assistant" {{ old('division_reference_column') == 'admin_assistant' ? 'selected' : '' }}>Admin Assistant</option>
                                        </select>
                                        @error('division_reference_column')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Column in divisions table to reference for division-specific approvers</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" value="1"
                                    {{ old('is_enabled', '1') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_enabled">Enabled</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_division_specific" name="is_division_specific" value="1"
                                                {{ old('is_division_specific') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="is_division_specific">Division Specific</label>
                                        </div>
                                        <div class="form-text">Check if this definition is specific to a division</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="triggers_category_check" name="triggers_category_check" value="1"
                                                {{ old('triggers_category_check') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="triggers_category_check">Triggers Category Check</label>
                                        </div>
                                        <div class="form-text">Check if this definition triggers category-based routing</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Definition</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container {
        width: 100% !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#divisions, #allowed_funders').select2({
            placeholder: 'Select options',
            allowClear: true,
            width: '100%'
        });
    });
</script>
@endpush
