{{--
  Expects: $emailPdfFormAction (string URL), $emailPdfDocumentLabel (string), $emailPdfRecipientChoices (list<string>)
--}}
@php
    $choices = $emailPdfRecipientChoices ?? [];
    $defaultRecipient = old('recipient_email', $choices[0] ?? '');
@endphp
@if(!empty($emailPdfFormAction) && count($choices) > 0)
<div class="modal fade" id="emailPdfModal" tabindex="-1" aria-labelledby="emailPdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ $emailPdfFormAction }}" id="emailPdfForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="emailPdfModalLabel">Email PDF — {{ $emailPdfDocumentLabel }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @error('recipient_email')
                        <div class="alert alert-danger small mb-3">{{ $message }}</div>
                    @enderror
                    <p class="text-muted small mb-3">The PDF is sent only to your own email address on file.</p>
                    <div class="mb-0">
                        <label for="email_pdf_recipient_email" class="form-label">Send to</label>
                        @if(count($choices) === 1)
                            <input type="email" class="form-control" id="email_pdf_recipient_email" name="recipient_email" value="{{ $choices[0] }}" readonly>
                        @else
                            <select class="form-select" id="email_pdf_recipient_email" name="recipient_email" required>
                                @foreach($choices as $em)
                                    <option value="{{ $em }}" @selected(strtolower((string) $em) === strtolower((string) $defaultRecipient))>{{ $em }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="emailPdfSubmitBtn">
                        <i class="bx bx-send me-1"></i>Send email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@once
@push('scripts')
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('emailPdfModal');
        if (!modal) return;
        @if($errors->has('recipient_email'))
        if (window.bootstrap && bootstrap.Modal) {
            try { new bootstrap.Modal(modal).show(); } catch (e) {}
        }
        @endif
    });
})();
</script>
@endpush
@endonce
@endif
