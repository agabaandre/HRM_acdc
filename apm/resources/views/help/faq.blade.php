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
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f4f2; }
        .faq-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2.5rem 0;
            box-shadow: 0 4px 12px rgba(17, 154, 72, .2);
        }
        .faq-header a { color: rgba(255,255,255,.92); text-decoration: none; }
        .faq-header a:hover { color: white; }
        .faq-header .btn-outline-light:hover,
        .faq-header .btn-outline-light:focus { background-color: transparent; border-color: rgba(255,255,255,.8); color: #fff; }
        .faq-hero { text-align: center; padding: 0.5rem 0 1rem; }
        .faq-hero h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.35rem; }
        .faq-hero .subtitle { opacity: .9; font-size: 0.95rem; }
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

        /* Section heading: Windows-folder icon color + bold black text; clickable to collapse section */
        .faq-section-header { cursor: pointer; padding: 0.75rem 0; border-bottom: 2px solid #dee2e6; margin-bottom: 0; display: flex; align-items: center; gap: 0.5rem; user-select: none; }
        .faq-section-header:hover { background: #f8f9fa; margin-left: -0.5rem; margin-right: -0.5rem; padding-left: 0.5rem; padding-right: 0.5rem; }
        .faq-section-header .fa-folder { color: #FFC000; }
        .faq-section-header .section-title { font-weight: 700; color: #000; margin: 0; font-size: 1.1rem; }
        .faq-section-header .section-chevron { margin-left: auto; color: #6c757d; transition: transform 0.2s; }
        .faq-section-header[aria-expanded="false"] .section-chevron { transform: rotate(-90deg); }
        .faq-category-section .collapse { margin-top: 0.5rem; }

        /* Print styles: expand all, no clipping, so first print shows full content */
        @media print {
            body { background: #fff; }
            .faq-header { background: #119a48 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .faq-header a[href]:not(.no-print-hide) { color: #fff !important; }
            .search-box, #faqPrintPdfBtn, .no-results, .btn, .d-flex.gap-2 .btn { display: none !important; }
            .faq-item-hidden { display: block !important; }
            .collapse { display: block !important; visibility: visible !important; height: auto !important; overflow: visible !important; }
            .faq-card, .faq-card .card-body { overflow: visible !important; }
            .faq-card .card-header { cursor: default; border-bottom: 1px solid #dee2e6; }
            .faq-card { box-shadow: none; border: 1px solid #dee2e6; margin-bottom: 0.75rem; break-inside: avoid; }
            .container { max-width: 100%; }
            a[href]:not(.no-print-hide)::after { content: none !important; }
            .platform-note { break-inside: avoid; }
            .faq-section-header .section-chevron { display: none; }
            .faq-category-section .collapse { display: block !important; visibility: visible !important; height: auto !important; overflow: visible !important; }
        }
    </style>
</head>
<body>
    <div class="faq-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <a href="{{ url('/') }}" class="d-inline-flex align-items-center mb-2 opacity-90"><i class="fas fa-arrow-left me-2"></i>Back to login</a>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('help.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-book me-1"></i>Help &amp; Documentation</a>
                    <button type="button" id="faqPrintPdfBtn" class="btn btn-light btn-sm" title="Print or Save as PDF">
                        <i class="fas fa-file-pdf me-1"></i>Print / Save as PDF
                    </button>
                </div>
            </div>
            <div class="faq-hero">
                <h1><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h1>
                <p class="subtitle mb-0">Central Business Platform (CBP)</p>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <div class="platform-note">
            <strong><i class="fas fa-info-circle me-1"></i>Access:</strong> The CBP platform is available at <a href="{{ rtrim(url('/'), '/') }}" target="_blank" rel="noopener">{{ rtrim(url('/'), '/') }}</a>. You must have a valid account to sign in.
        </div>

        <div class="search-box">
            <label class="form-label small text-muted mb-1">Search FAQs</label>
            <input type="text" id="faqSearch" class="form-control form-control-lg border-2" placeholder="Type to search questions or answers...">
        </div>

        <div id="noResults" class="no-results card">
            <div class="card-body">No matching questions found. Try different keywords.</div>
        </div>

        <div id="faqAccordion">
            @forelse($categories as $catIndex => $category)
                @if($category->faqs->isNotEmpty())
                    @php
                        $sectionCollapseId = 'section-' . $category->id;
                    @endphp
                    <div class="faq-category-section mb-4">
                        <div class="faq-section-header" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $sectionCollapseId }}" aria-expanded="false">
                            <i class="fas fa-folder"></i>
                            <span class="section-title">{{ $category->name }}</span>
                            <i class="fas fa-chevron-down section-chevron"></i>
                        </div>
                        <div id="{{ $sectionCollapseId }}" class="collapse">
                            @foreach($category->faqs as $idx => $faq)
                                @php
                                    $searchText = trim(implode(' ', [
                                        $faq->search_keywords ?? '',
                                        $faq->question,
                                        strip_tags($faq->answer),
                                    ]));
                                    $collapseId = 'faq-' . $faq->id;
                                    $num = $idx + 1;
                                @endphp
                                <div class="faq-item" data-search="{{ Str::lower($searchText) }}">
                                    <div class="card faq-card">
                                        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}">
                                            <span class="badge-num">{{ $num }}</span>{{ $faq->question }}
                                        </div>
                                        <div id="{{ $collapseId }}" class="collapse">
                                            <div class="card-body faq-answer">
                                                {!! $faq->resolved_answer !!}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
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
            <a href="{{ route('help.index') }}" class="btn btn-success w-100"><i class="fas fa-book me-2"></i>Help &amp; Documentation</a>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary w-100 mt-2"><i class="fas fa-arrow-left me-2"></i>Back to login</a>
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

        // Print / Save as PDF: expand all sections and answers, force layout, then print (fixes first-print content cut off)
        document.getElementById('faqPrintPdfBtn').addEventListener('click', function() {
            var collapses = document.querySelectorAll('#faqAccordion .collapse');
            collapses.forEach(function(el) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    var bsCollapse = bootstrap.Collapse.getInstance(el);
                    if (!bsCollapse) bsCollapse = new bootstrap.Collapse(el, { toggle: false });
                    bsCollapse.show();
                }
                el.classList.add('show');
                el.style.height = 'auto';
                el.style.overflow = 'visible';
            });
            document.querySelectorAll('.faq-item-hidden').forEach(function(el) { el.classList.remove('faq-item-hidden'); });
            // Force browser to apply expanded layout before opening print dialog
            void document.body.offsetHeight;
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    setTimeout(function() { window.print(); }, 150);
                });
            });
        });
    </script>
</body>
</html>
