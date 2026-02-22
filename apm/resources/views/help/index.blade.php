<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help &amp; Documentation – CBP Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #119a48;
            --primary-dark: #0d7a3a;
            --card-shadow: 0 2px 8px rgba(0,0,0,.06);
            --card-shadow-hover: 0 6px 20px rgba(0,0,0,.1);
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f4f2; }
        .help-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2.5rem 0;
            box-shadow: 0 4px 12px rgba(17, 154, 72, .2);
        }
        .help-header a { color: rgba(255,255,255,.92); text-decoration: none; }
        .help-header a:hover { color: white; }
        .help-header .btn-outline-light:hover,
        .help-header .btn-outline-light:focus { background-color: transparent; border-color: rgba(255,255,255,.8); color: #fff; }
        .help-hero {
            text-align: center;
            padding: 0.5rem 0 1rem;
        }
        .help-hero h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.35rem; }
        .help-hero .subtitle { opacity: .9; font-size: 0.95rem; }

        .platform-note {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
            border-left: 4px solid var(--primary);
            padding: 0.85rem 1.1rem;
            margin-bottom: 1.75rem;
            font-size: 0.95rem;
            border-radius: 0 6px 6px 0;
            box-shadow: var(--card-shadow);
        }
        .platform-note a { color: var(--primary); font-weight: 500; }

        .section-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6c757d;
            margin-bottom: 0.75rem;
        }
        .help-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            overflow: hidden;
        }
        .help-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-hover);
        }
        .help-card .card-body { padding: 1.35rem; }
        .help-card .icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .help-card.faq-card .icon-wrap { background: rgba(17, 154, 72, .12); color: var(--primary); }
        .help-card.user-guide .icon-wrap { background: rgba(13, 110, 253, .12); color: #0d6efd; }
        .help-card.approvers-guide .icon-wrap { background: rgba(13, 202, 240, .15); color: #0dcaf0; }
        .help-card .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.25rem; }
        .help-card .card-text { font-size: 0.9rem; color: #495057; margin-bottom: 1rem; }
        .help-card .btn { font-weight: 500; border-radius: 8px; padding: 0.5rem 1rem; }

        .doc-list {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        .doc-list .list-group-item {
            border: none;
            border-bottom: 1px solid #eee;
            padding: 0.85rem 1.1rem;
            font-size: 0.95rem;
            transition: background .15s;
        }
        .doc-list .list-group-item:last-child { border-bottom: none; }
        .doc-list .list-group-item:hover { background: #f8f9fa; }
        .doc-list .list-group-item i { width: 1.5rem; color: var(--primary); }
        #docFilter { max-width: 100%; border-radius: 8px; }

        .footer-actions { margin-top: 2rem; padding-bottom: 2rem; }
        .footer-actions .btn { border-radius: 8px; font-weight: 500; }
    </style>
</head>
<body>
    <div class="help-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <a href="{{ url('/') }}" class="d-inline-flex align-items-center mb-2 opacity-90"><i class="fas fa-arrow-left me-2"></i>Back to login</a>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('faq.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-question-circle me-1"></i>FAQs</a>
                </div>
            </div>
            <div class="help-hero">
                <h1><i class="fas fa-book-open me-2"></i>Help &amp; Documentation</h1>
                <p class="subtitle mb-0">Central Business Platform (CBP)</p>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <div class="platform-note">
            <strong><i class="fas fa-info-circle me-1"></i>Access:</strong> The CBP platform is available at <a href="{{ rtrim(url('/'), '/') }}" target="_blank" rel="noopener">{{ rtrim(url('/'), '/') }}</a>. You must have a valid account to sign in.
        </div>

        <p class="section-label">Get started</p>
        <div class="row g-3 mb-4">
            <!-- FAQs -->
            <div class="col-12">
                <div class="card help-card faq-card">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="icon-wrap me-3 flex-shrink-0">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title">Frequently Asked Questions</h5>
                                <p class="card-text text-muted small mb-2">Quick, searchable answers on special memos, travel matrices, change requests, and more.</p>
                                <a href="{{ route('faq.index') }}" class="btn btn-success btn-sm">
                                    <i class="fas fa-list-ul me-2"></i>View FAQs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- User Guide -->
            <div class="col-md-6">
                <div class="card help-card user-guide">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="icon-wrap me-3 flex-shrink-0">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title">User Guide</h5>
                                <p class="card-text small">For document creators: matrices, special memos, single memos, non-travel memos, change requests, service requests, ARF.</p>
                                <a href="{{ route('help.user-guide') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-book-open me-2"></i>Open User Guide
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Approvers Guide -->
            <div class="col-md-6">
                <div class="card help-card approvers-guide">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="icon-wrap me-3 flex-shrink-0">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title">Approvers Guide</h5>
                                <p class="card-text small">For approvers: workflow, approving, returning, rejecting, and approval trail tracking.</p>
                                <a href="{{ route('help.approvers-guide') }}" class="btn btn-info btn-sm">
                                    <i class="fas fa-clipboard-check me-2"></i>Open Approvers Guide
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="section-label">Technical documentation</p>
        <div class="mb-3">
            <label class="form-label small text-muted mb-1">Filter docs</label>
            <input type="text" id="docFilter" class="form-control" placeholder="Type to filter documentation list...">
        </div>
        <div class="doc-list list-group" id="docList">
            <a href="{{ route('help.documentation.file', 'API_DOCUMENTATION.md') }}" class="list-group-item list-group-item-action" data-search="api documentation rest endpoints">
                <i class="fas fa-code me-2"></i>API Documentation
            </a>
            <a href="{{ url('/docs') }}" class="list-group-item list-group-item-action" data-search="swagger openapi interactive api reference" target="_blank" rel="noopener">
                <i class="fas fa-file-code me-2"></i>API Reference (Swagger)
            </a>
            <a href="{{ route('help.documentation.file', 'README.md') }}" class="list-group-item list-group-item-action" data-search="complete apm documentation readme">
                <i class="fas fa-book me-2"></i>Complete APM Documentation
            </a>
            <a href="{{ route('help.documentation.file', 'DEPLOYMENT.md') }}" class="list-group-item list-group-item-action" data-search="deployment guide server">
                <i class="fas fa-server me-2"></i>Deployment Guide
            </a>
            <a href="{{ route('help.documentation.file', 'QUEUE_SETUP_GUIDE.md') }}" class="list-group-item list-group-item-action" data-search="queue setup guide tasks">
                <i class="fas fa-tasks me-2"></i>Queue Setup Guide
            </a>
            <a href="{{ route('help.documentation.file', 'QUEUE_TROUBLESHOOTING.md') }}" class="list-group-item list-group-item-action" data-search="queue troubleshooting wrench">
                <i class="fas fa-wrench me-2"></i>Queue Troubleshooting
            </a>
            <a href="{{ route('help.documentation.file', 'CRON_SETUP.md') }}" class="list-group-item list-group-item-action" data-search="cron setup guide clock">
                <i class="fas fa-clock me-2"></i>Cron Setup Guide
            </a>
            <a href="{{ route('help.documentation.file', 'APPROVAL_TRAIL_MANAGEMENT.md') }}" class="list-group-item list-group-item-action" data-search="approval trail management list">
                <i class="fas fa-list-check me-2"></i>Approval Trail Management
            </a>
            <a href="{{ route('help.documentation.file', 'DOCUMENT_NUMBERING_SYSTEM.md') }}" class="list-group-item list-group-item-action" data-search="document numbering system hash">
                <i class="fas fa-hashtag me-2"></i>Document Numbering System
            </a>
            <a href="{{ route('help.documentation.file', 'SIGNATURE_VERIFICATION.md') }}" class="list-group-item list-group-item-action" data-search="signature verification">
                <i class="fas fa-signature me-2"></i>Signature Verification
            </a>
            <a href="{{ route('help.documentation.file', 'SYSTEM_UPDATES.md') }}" class="list-group-item list-group-item-action" data-search="system updates sync">
                <i class="fas fa-sync-alt me-2"></i>System Updates
            </a>
        </div>
        <p id="docNoResults" class="small text-muted mt-2 mb-0" style="display: none;">No matching documents.</p>

        <div class="footer-actions text-center">
            <a href="{{ route('faq.index') }}" class="btn btn-success"><i class="fas fa-question-circle me-2"></i>FAQs</a>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary ms-2"><i class="fas fa-arrow-left me-2"></i>Back to login</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('docFilter').addEventListener('input', function() {
            var q = this.value.trim().toLowerCase();
            var items = document.querySelectorAll('#docList .list-group-item');
            var noResults = document.getElementById('docNoResults');
            var visible = 0;
            items.forEach(function(el) {
                var text = (el.getAttribute('data-search') || '') + ' ' + (el.textContent || '').toLowerCase();
                var show = !q || text.indexOf(q) !== -1;
                el.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            noResults.style.display = visible ? 'none' : 'block';
        });
    </script>
</body>
</html>
