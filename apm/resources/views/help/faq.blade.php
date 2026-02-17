<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs – CBP Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #119a48; --primary-dark: #0d7a3a; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f8f9fa; }
        .faq-header { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 2rem 0; }
        .faq-header a { color: rgba(255,255,255,.9); text-decoration: none; }
        .faq-header a:hover { color: white; }
        .search-box { max-width: 480px; margin: 0 auto 1.5rem; }
        .faq-card { border: none; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 0.75rem; overflow: hidden; }
        .faq-card .card-header { background: #fff; border-bottom: 1px solid #eee; font-weight: 600; cursor: pointer; padding: 1rem 1.25rem; }
        .faq-card .card-header:hover { background: #f8f9fa; }
        .faq-card .card-body { padding: 1rem 1.25rem; color: #495057; line-height: 1.6; }
        .faq-card .card-body ul { margin-bottom: 0; padding-left: 1.25rem; }
        .faq-card .card-body a { color: var(--primary); }
        .faq-item-hidden { display: none; }
        .no-results { display: none; padding: 2rem; text-align: center; color: #6c757d; }
        .badge-num { background: var(--primary); color: white; width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; margin-right: 0.5rem; }
        .platform-note { background: #e8f5e9; border-left: 4px solid var(--primary); padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.95rem; }

        /* Print styles (borrowed from special-memo print: clean layout, expand all, hide nav) */
        @media print {
            body { background: #fff; }
            .faq-header { background: #119a48 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .faq-header a[href]:not(.no-print-hide) { color: #fff !important; }
            .search-box, #faqPrintPdfBtn, .no-results, .btn, .d-flex.gap-2 .btn { display: none !important; }
            .faq-item-hidden { display: block !important; }
            .collapse { display: block !important; visibility: visible !important; height: auto !important; }
            .faq-card .card-header { cursor: default; border-bottom: 1px solid #dee2e6; }
            .faq-card { box-shadow: none; border: 1px solid #dee2e6; margin-bottom: 0.75rem; break-inside: avoid; }
            .container { max-width: 100%; }
            a[href]:not(.no-print-hide)::after { content: none !important; }
            .platform-note { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="faq-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <a href="{{ url('/') }}" class="d-inline-flex align-items-center mb-1"><i class="fas fa-arrow-left me-2"></i>Back to login</a>
                    <h1 class="h4 mb-0 mt-2"><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h1>
                    <p class="mb-0 opacity-90 small">Central Business Platform (CBP) – Approvals Management and Staff Portal</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="faqPrintPdfBtn" class="btn btn-light btn-sm" title="Print or Save as PDF">
                        <i class="fas fa-file-pdf me-1"></i>Print / Save as PDF
                    </button>
                    <a href="{{ url('/help') }}" class="btn btn-outline-success btn-sm"><i class="fas fa-book me-1"></i>Help &amp; guides</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <div class="platform-note">
            <strong><i class="fas fa-info-circle me-1"></i>Access:</strong> The CBP platform is available at <a href="https://cbp.africacdc.org" target="_blank" rel="noopener">https://cbp.africacdc.org</a>. You must use an <strong>active Africa CDC email account</strong> to sign in.
        </div>

        <div class="search-box">
            <label class="form-label small text-muted mb-1">Search FAQs</label>
            <input type="text" id="faqSearch" class="form-control form-control-lg border-2" placeholder="Type to search questions or answers...">
        </div>

        <div id="noResults" class="no-results card">
            <div class="card-body">No matching questions found. Try different keywords.</div>
        </div>

        <div id="faqAccordion">
            @forelse($faqs as $index => $faq)
                @php
                    $searchText = trim(implode(' ', [
                        $faq->search_keywords ?? '',
                        $faq->question,
                        strip_tags($faq->answer),
                    ]));
                    $collapseId = 'faq-' . $faq->id;
                @endphp
                <div class="faq-item" data-search="{{ Str::lower($searchText) }}">
                    <div class="card faq-card">
                        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}">
                            <span class="badge-num">{{ $index + 1 }}</span>{{ $faq->question }}
                        </div>
                        <div id="{{ $collapseId }}" class="collapse">
                            <div class="card-body faq-answer">
                                {!! $faq->answer !!}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card faq-card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <p class="mb-0">No FAQs are available at the moment. Please check back later or contact support.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="text-center mt-4 pb-3">
            <a href="{{ url('/help') }}" class="btn btn-success w-100"><i class="fas fa-book"></i>Help &amp; user guides</a>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary w-100 mt-2"><i class="fas fa-arrow-left"></i>Back to login</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('faqSearch').addEventListener('input', function() {
            var q = this.value.trim().toLowerCase();
            var items = document.querySelectorAll('.faq-item');
            var vis = 0;
            items.forEach(function(el) {
                var text = (el.getAttribute('data-search') || '') + ' ' + (el.textContent || '').toLowerCase();
                var show = !q || text.indexOf(q) !== -1;
                el.classList.toggle('faq-item-hidden', !show);
                if (show) vis++;
            });
            document.getElementById('noResults').style.display = vis ? 'none' : 'block';
        });

        // Print / Save as PDF: expand all FAQs then open print dialog (like special memo print)
        document.getElementById('faqPrintPdfBtn').addEventListener('click', function() {
            var collapses = document.querySelectorAll('#faqAccordion .collapse');
            var bsCollapse;
            collapses.forEach(function(el) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    bsCollapse = bootstrap.Collapse.getInstance(el);
                    if (!bsCollapse) bsCollapse = new bootstrap.Collapse(el, { toggle: false });
                    bsCollapse.show();
                } else {
                    el.classList.add('show');
                    el.style.height = 'auto';
                }
            });
            document.querySelectorAll('.faq-item-hidden').forEach(function(el) { el.classList.remove('faq-item-hidden'); });
            setTimeout(function() { window.print(); }, 300);
        });
    </script>
</body>
</html>
