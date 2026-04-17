@extends('layouts.app')

@section('title', 'New other memo')

@section('header', 'New other memo')

@section('header-actions')
    <a href="{{ route('other-memos.index') }}" class="btn btn-outline-secondary" wire:navigate>
        <i class="bx bx-arrow-back me-1 text-success"></i> Back to list
    </a>
@endsection

@push('styles')
<style>
    .other-memo-form-page .select2-container--bootstrap4 .select2-results__option--highlighted,
    .other-memo-form-page .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #198754 !important;
        color: #fff !important;
    }
    .other-memo-form-page .select2-container--bootstrap4 .select2-results__option[aria-selected="true"] {
        background-color: rgba(25, 135, 84, 0.12) !important;
        color: #0f5132 !important;
    }
</style>
@endpush

@section('content')
{{-- Page markers + scripts below live inside #apm-content-area so wire:navigate executes them (Livewire docs). --}}
<div class="other-memo-form-page" data-apm-livewire-page="other-memos-create"
    data-memo-types-api="{{ route('memo-type-definitions.api.index') }}?active_only=1">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 text-dark">
                <i class="bx bx-file-blank me-2 text-success"></i> Create memo
            </h5>
        </div>
        <div class="card-body p-4">
            <form method="post" action="{{ route('other-memos.store') }}" id="other-memo-create-form" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <h6 class="fw-bold text-success mb-3 border-bottom pb-2">
                        <i class="bx bx-category me-2"></i> Memo type
                    </h6>
                    <div class="row g-3">
                        <div class="col-12 col-lg-8">
                            <label class="form-label fw-semibold" for="memo_type_slug">
                                <i class="bx bx-list-ul me-1 text-success"></i> Type <span class="text-danger">*</span>
                            </label>
                <select name="memo_type_slug" id="memo_type_slug" class="form-select other-memo-type-select w-100 border-success" required
                    data-placeholder="Select memo type" style="width: 100%;">
                                <option value="">— Load types —</option>
                            </select>
                            <p class="small text-muted mt-2 mb-0">Choose a catalogue entry from Settings → Other memo types. Fields and approvers appear after selection.</p>
                        </div>
                    </div>
                </div>

                <div class="card border mb-4 d-none" id="memo-fields-card">
                    <div class="card-header bg-light border-bottom py-2">
                        <span class="fw-semibold text-success"><i class="bx bx-edit-alt me-1"></i> Memo content</span>
                    </div>
                    <div class="card-body" id="memo-dynamic-fields"></div>
                </div>

                <div class="card border mb-4 d-none" id="memo-attachments-card">
                    <div class="card-header bg-light border-bottom py-2">
                        <span class="fw-semibold text-success"><i class="bx bx-paperclip me-1"></i> Attachments</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-danger btn-sm" id="other-memo-add-attachment">Add New</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="other-memo-remove-attachment">Remove</button>
                        </div>
                        <div class="row g-3" id="other-memo-attachment-container"></div>
                        <p class="small text-muted mt-2 mb-0">Max size 10 MB per file. Allowed: PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, DOCX.</p>
                    </div>
                </div>

                <div class="card border mb-4 d-none" id="memo-approvers-card">
                    <div class="card-header bg-light border-bottom py-2">
                        <span class="fw-semibold text-success"><i class="bx bx-git-merge me-1"></i> Approval sequence</span>
                    </div>
                    <div class="card-body">
                        @include('other-memos.partials.approver-rows', [
                            'approvers' => [],
                            'staffOptions' => $staffOptions,
                            'roleExamples' => $roleExamples,
                        ])
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                    <button type="submit" class="btn btn-success" id="other-memo-create-submit">
                        <i class="bx bx-save"></i> Save draft
                    </button>
                    <a href="{{ route('other-memos.index') }}" class="btn btn-outline-secondary" wire:navigate>Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- Memo type loader + dynamic fields: public/js/apm-other-memo-create.js (DOMContentLoaded + livewire:navigated). --}}
@endsection
