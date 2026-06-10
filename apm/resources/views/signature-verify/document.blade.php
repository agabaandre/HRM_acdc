<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document verification – Africa CDC CBP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #119a48; --primary-dark: #0d7a3a; }
        body { background: #f4f6f8; }
        .verify-header { background: linear-gradient(135deg, var(--primary), #1bb85a); color: #fff; }
        .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        code { font-size: 0.9em; }
    </style>
</head>
<body>
@php
    $data = $result_for_display ?? [];
    $doc = $data['document'] ?? [];
    $meta = $doc['metadata'] ?? [];
    $signatories = $data['signatories'] ?? [];
    $matched = $data['matched_signatory'] ?? null;
    $hashMatched = !empty($data['hash_matched']);
@endphp
<div class="verify-header py-4 mb-4">
    <div class="container">
        <h1 class="h4 mb-1"><i class="fas fa-shield-alt me-2"></i>APM document verification</h1>
        <p class="mb-0 small opacity-90">Validate signature hashes for an approved Africa CDC document.</p>
    </div>
</div>

<div class="container pb-5">
    <div class="card mb-4">
        <div class="card-body">
            @if (!empty($data['message']))
                <div class="alert alert-warning">{{ $data['message'] }}</div>
            @endif

            @if ($hashMatched && $matched)
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle me-1"></i>
                    <strong>Hash verified.</strong> The signature hash matches an approver on this document.
                </div>
            @elseif (!empty($hash))
                <div class="alert alert-warning mb-4">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Hash <code class="user-select-all">{{ $hash }}</code> did not match any signatory below.
                </div>
            @endif

            <h5 class="mb-3">Document</h5>
            <dl class="row small mb-4">
                <dt class="col-sm-3 text-muted">Document type</dt>
                <dd class="col-sm-9">{{ $doc['doc_type'] ?? 'N/A' }}</dd>
                <dt class="col-sm-3 text-muted">Document number</dt>
                <dd class="col-sm-9"><code>{{ $doc['document_number'] ?? 'N/A' }}</code></dd>
                @if (!empty($meta['activity_title']))
                    <dt class="col-sm-3 text-muted">Title</dt>
                    <dd class="col-sm-9">{{ $meta['activity_title'] }}</dd>
                @endif
                <dt class="col-sm-3 text-muted">Creator</dt>
                <dd class="col-sm-9">{{ $meta['creator'] ?? 'N/A' }}</dd>
                <dt class="col-sm-3 text-muted">Division</dt>
                <dd class="col-sm-9">{{ $meta['division'] ?? 'N/A' }}</dd>
                <dt class="col-sm-3 text-muted">Date created</dt>
                <dd class="col-sm-9">{{ $meta['date_created'] ?? 'N/A' }}</dd>
            </dl>

            @if ($matched)
                <h5 class="mb-3">Matched signatory</h5>
                <dl class="row small mb-4">
                    <dt class="col-sm-3 text-muted">Role</dt><dd class="col-sm-9">{{ $matched['role'] ?? '' }}</dd>
                    <dt class="col-sm-3 text-muted">Name</dt><dd class="col-sm-9">{{ $matched['name'] ?? '' }}</dd>
                    <dt class="col-sm-3 text-muted">Action</dt><dd class="col-sm-9"><span class="badge bg-secondary">{{ $matched['action'] ?? '' }}</span></dd>
                    <dt class="col-sm-3 text-muted">Date / time</dt><dd class="col-sm-9">{{ $matched['date'] ?? '' }}</dd>
                    <dt class="col-sm-3 text-muted">Verify hash</dt><dd class="col-sm-9"><code class="user-select-all">{{ $matched['hash'] ?? '' }}</code></dd>
                </dl>
            @endif

            @if (count($signatories) > 0)
                <h5 class="mb-3">Signatories and verification hashes</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Role</th>
                                <th>Name</th>
                                <th>Action</th>
                                <th>Date / time</th>
                                <th>Verify hash</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($signatories as $s)
                                <tr @if($matched && ($s['hash'] ?? '') === ($matched['hash'] ?? '')) class="table-success" @endif>
                                    <td>{{ $s['role'] ?? '' }}</td>
                                    <td>{{ $s['name'] ?? '' }}</td>
                                    <td><span class="badge bg-secondary">{{ $s['action'] ?? '' }}</span></td>
                                    <td>{{ $s['date'] ?? '' }}</td>
                                    <td><code class="user-select-all">{{ $s['hash'] ?? '' }}</code></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted mb-0">No signatory records found for this document.</p>
            @endif
        </div>
    </div>

    <p class="text-center text-muted small mb-0">
        <a href="{{ route('signature-verify.index') }}" class="text-decoration-none">Staff login</a> for manual lookup, hash verify, or PDF upload validation.
    </p>
</div>
</body>
</html>
