{{--
  Expects: $emailPdfFormAction (string URL), $emailPdfDocumentLabel (string), $emailPdfRecipientChoices (list<string>)
--}}
@php
    $choices = $emailPdfRecipientChoices ?? [];
    $defaultRecipient = old('recipient_email', $choices[0] ?? '');
@endphp
@if(!empty($emailPdfFormAction) && count($choices) > 0)
<div class="modal fade" id="emailPdfModal" tabindex="-1" aria-labelledby="emailPdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="{{ $emailPdfFormAction }}" id="emailPdfForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="emailPdfModalLabel">Email PDF — {{ $emailPdfDocumentLabel }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($errors->has('recipient_email') || $errors->has('message_html'))
                        <div class="alert alert-danger small mb-3">
                            @foreach (['recipient_email', 'message_html'] as $errKey)
                                @error($errKey)
                                    <div>{{ $message }}</div>
                                @enderror
                            @endforeach
                        </div>
                    @endif
                    <p class="text-muted small mb-3">The PDF is sent only to your own email address on file.</p>
                    <div class="mb-3">
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
                    <div class="mb-2">
                        <label for="email_pdf_message_html" class="form-label">Message <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control summernote-email-pdf" name="message_html" id="email_pdf_message_html" rows="6" placeholder="Add a note to include in the email…">{{ old('message_html') }}</textarea>
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
        if (!modal || typeof jQuery === 'undefined') return;
        var $modal = jQuery(modal);
        $modal.on('shown.bs.modal', function () {
            var $ta = jQuery('#email_pdf_message_html');
            if (!$ta.length || $ta.next('.note-editor').length) return;
            if (typeof window.apmSummernoteOptions === 'function' && $ta.summernote) {
                $ta.summernote(window.apmSummernoteOptions({ height: 220, minHeight: 160, placeholder: 'Add a note…' }));
            }
        });
        $modal.on('hidden.bs.modal', function () {
            var $ta = jQuery('#email_pdf_message_html');
            if ($ta.length && $ta.summernote && $ta.next('.note-editor').length) {
                try { $ta.summernote('destroy'); } catch (e) {}
            }
        });
        jQuery('#emailPdfForm').on('submit', function () {
            var $ta = jQuery('#email_pdf_message_html');
            if ($ta.length && $ta.summernote && $ta.next('.note-editor').length) {
                try { $ta.val($ta.summernote('code')); } catch (e) {}
            }
        });
        @if($errors->has('recipient_email') || $errors->has('message_html'))
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
