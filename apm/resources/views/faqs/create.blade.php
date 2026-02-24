@extends('layouts.app')

@section('title', 'Add FAQ')
@section('header', 'Add FAQ')

@section('header-actions')
<a href="{{ route('faqs.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to list
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0"><i class="bx bx-plus-circle me-2"></i>New FAQ</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('faqs.store') }}" method="POST" id="faqForm">
            @csrf

            <div class="mb-4">
                <label for="faq_category_id" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                <select name="faq_category_id" id="faq_category_id" class="form-select form-select-lg @error('faq_category_id') is-invalid @enderror" required>
                    <option value="">Select category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('faq_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('faq_category_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="question" class="form-label fw-semibold">Question <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-lg @error('question') is-invalid @enderror" id="question"
                    name="question" value="{{ old('question') }}" placeholder="e.g. When should I use the special memos?" required maxlength="500">
                @error('question')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="answer" class="form-label fw-semibold">Answer <span class="text-danger">*</span></label>
                <textarea name="answer" id="answer" class="form-control summernote @error('answer') is-invalid @enderror" rows="6" required>{{ old('answer') }}</textarea>
                @error('answer')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Use the toolbar for bold, lists, links, and formatting. For Staff Portal links use the placeholder <code>{!! '{{staff_portal_url}}' !!}</code> so the URL adapts to production or demo.</small>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label for="sort_order" class="form-label fw-semibold">Sort order</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', (App\Models\Faq::max('sort_order') ?? 0) + 1) }}" min="0">
                    <small class="text-muted">Lower numbers appear first on the public page.</small>
                </div>
                <div class="col-md-6 mb-4">
                    <label for="search_keywords" class="form-label fw-semibold">Search keywords</label>
                    <input type="text" class="form-control" id="search_keywords" name="search_keywords" value="{{ old('search_keywords') }}" placeholder="Optional comma-separated keywords" maxlength="500">
                    <small class="text-muted">Helps the public page search find this FAQ.</small>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active (show on public FAQ page)</label>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                <a href="{{ route('faqs.index') }}" class="btn btn-outline-secondary px-4"><i class="bx bx-arrow-back me-1"></i> Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm"><i class="bx bx-save me-2"></i> Save FAQ</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#faqForm').on('submit', function() {
            $('textarea.summernote').each(function() {
                var $ta = $(this);
                if (typeof $ta.summernote === 'function' && $ta.summernote('code') !== undefined) {
                    $ta.val($ta.summernote('code'));
                }
            });
        });
    });
</script>
@endpush
