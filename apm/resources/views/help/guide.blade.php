<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} – CBP Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #119a48; --primary-dark: #0d7a3a; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f8f9fa; }
        .doc-header { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 2rem 0; }
        .doc-header a { color: rgba(255,255,255,.9); text-decoration: none; }
        .doc-header a:hover { color: white; }
        /* btn-light is white by default; use dark text so it's readable (header links are white) */
        .doc-header .btn-light,
        .doc-header .btn-light i { color: #212529 !important; }
        /* All header buttons: on hover background goes white, use dark text for readability */
        .doc-header .btn-outline-light:hover,
        .doc-header .btn-outline-light:focus,
        .doc-header .btn-light:hover,
        .doc-header .btn-light:focus { color: #212529 !important; background-color: #fff; border-color: #fff; }
        .doc-header .btn-outline-light:hover i,
        .doc-header .btn-outline-light:focus i,
        .doc-header .btn-light:hover i,
        .doc-header .btn-light:focus i { color: inherit; }
        .search-box { max-width: 480px; margin: 0 auto 1.5rem; }
        .no-results-doc { display: none; padding: 2rem; text-align: center; color: #6c757d; }
        .platform-note { background: #e8f5e9; border-left: 4px solid var(--primary); padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.95rem; }

        .guide-content { line-height: 1.8; color: #333; }
        .guide-content h1 { color: #119a48; border-bottom: 3px solid #119a48; padding-bottom: 10px; margin-bottom: 30px; margin-top: 20px; }
        .guide-content h2 { color: #28a745; margin-top: 40px; margin-bottom: 20px; padding-bottom: 8px; border-bottom: 2px solid #e9ecef; }
        .guide-content h3 { color: #17a2b8; margin-top: 30px; margin-bottom: 15px; }
        .guide-content h4 { color: #6c757d; margin-top: 25px; margin-bottom: 12px; }
        .guide-content p { margin-bottom: 15px; text-align: justify; }
        .guide-content ul, .guide-content ol { margin-bottom: 20px; padding-left: 30px; }
        .guide-content li { margin-bottom: 8px; }
        .guide-content code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; color: #e83e8c; }
        .guide-content pre { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; overflow-x: auto; margin: 20px 0; }
        .guide-content pre code { background: none; padding: 0; color: #333; }
        .guide-content table { width: 100%; margin: 20px 0; border-collapse: collapse; }
        .guide-content table th, .guide-content table td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        .guide-content table th { background: #f8f9fa; font-weight: 600; color: #495057; }
        .guide-content blockquote { border-left: 4px solid #119a48; padding-left: 20px; margin: 20px 0; color: #6c757d; font-style: italic; }
        .guide-content a { color: #119a48; text-decoration: none; }
        .guide-content a:hover { text-decoration: underline; }
        .guide-content hr { margin: 40px 0; border: none; border-top: 2px solid #e9ecef; }
        .screenshot-placeholder { background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px; padding: 60px 20px; margin: 30px 0; text-align: center; min-height: 200px; display: flex; align-items: center; justify-content: center; }
        .screenshot-placeholder .placeholder-content { color: #6c757d; }
        .screenshot-placeholder i { font-size: 4rem; margin-bottom: 15px; color: #adb5bd; display: block; }
        .screenshot-placeholder p { font-weight: 600; margin: 10px 0 5px 0; color: #495057; font-size: 1.1rem; }
        .screenshot-placeholder small { color: #868e96; font-style: italic; display: block; margin-top: 5px; }

        @media print {
            .doc-header a[href]:not(.no-print-hide) { color: #fff !important; }
            .search-box, #docSearch, .no-results-doc, .btn { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="doc-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    @if($guideType === 'documentation')
                    <a href="{{ route('help.index') }}" class="d-inline-flex align-items-center mb-1"><i class="fas fa-arrow-left me-2"></i>Back to Help</a>
                    <span class="text-white opacity-75 mx-2">|</span>
                    @endif
                    <a href="{{ url('/') }}" class="d-inline-flex align-items-center mb-1"><i class="fas fa-sign-in-alt me-2"></i>Back to login</a>
                    <h1 class="h4 mb-0 mt-2">
                        <i class="fas fa-{{ $guideType === 'user' ? 'user' : ($guideType === 'approver' ? 'user-check' : 'book') }} me-2"></i>{{ $title }}
                    </h1>
                    <p class="mb-0 opacity-90 small">Central Business Platform (CBP)</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if($guideType === 'documentation')
                    <a href="{{ route('help.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Help</a>
                    @else
                    <a href="{{ route('help.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-book me-1"></i>Help</a>
                    @endif
                    <a href="{{ route('faq.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-question-circle me-1"></i>FAQs</a>
                    @if($guideType === 'user')
                    <a href="{{ route('help.approvers-guide') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-user-check me-1"></i>Approvers Guide</a>
                    @elseif($guideType === 'approver')
                    <a href="{{ route('help.user-guide') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-user me-1"></i>User Guide</a>
                    @else
                    <a href="{{ route('help.user-guide') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-user me-1"></i>User Guide</a>
                    <a href="{{ route('help.approvers-guide') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-user-check me-1"></i>Approvers Guide</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <div class="platform-note">
            <strong><i class="fas fa-info-circle me-1"></i>Access:</strong> The CBP platform is available at <a href="{{ rtrim(url('/'), '/') }}" target="_blank" rel="noopener" class="text-dark">{{ rtrim(url('/'), '/') }}</a>. You must have a valid account to sign in.
        </div>

        <div class="search-box">
            <label class="form-label small text-muted mb-1">Search this document</label>
            <input type="text" id="docSearch" class="form-control form-control-lg border-2" placeholder="Type to search in this page...">
        </div>

        <div id="noResultsDoc" class="no-results-doc card">
            <div class="card-body">No matching content found. Try different keywords.</div>
        </div>

        <div id="docContentWrapper" class="card shadow-sm">
            <div class="card-body">
                <div class="guide-content">
                    {!! $content !!}
                </div>
            </div>
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Last Updated: {{ now()->format('F Y') }}</small>
                    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-home me-1"></i>Back to Help Center</a>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 pb-3">
            <a href="{{ route('help.index') }}" class="btn btn-success w-100"><i class="fas fa-book"></i> Help Center</a>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary w-100 mt-2"><i class="fas fa-arrow-left"></i> Back to login</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            var input = document.getElementById('docSearch');
            var wrapper = document.getElementById('docContentWrapper');
            var noResults = document.getElementById('noResultsDoc');
            if (!input || !wrapper) return;

            function normalize(s) {
                return (s || '').toLowerCase().replace(/\s+/g, ' ').trim();
            }

            input.addEventListener('input', function() {
                var q = normalize(this.value);
                var searchable = normalize(wrapper.textContent || '');
                var show = !q || searchable.indexOf(q) !== -1;
                wrapper.style.display = show ? '' : 'none';
                noResults.style.display = show ? 'none' : 'block';
            });
        })();
    </script>
</body>
</html>
