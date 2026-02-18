<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Signature Verification</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; color: #333; margin: 20px; line-height: 1.4; }
        h1 { font-size: 16pt; color: #911C39; border-bottom: 2px solid #911C39; padding-bottom: 6px; margin-top: 0; }
        h2 { font-size: 12pt; margin-top: 16px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f8f9fa; font-weight: bold; }
        .meta-table td:first-child { width: 28%; color: #6c757d; }
        .verified-badge { background: #911C39; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; margin-bottom: 10px; display: inline-block; }
        .failed-badge { background: #6c757d; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; margin-bottom: 10px; display: inline-block; }
        .doc-number { font-family: monospace; }
    </style>
</head>
<body>
    <h1>Signature Verification</h1>
    <p style="margin-bottom: 16px;">Africa CDC Central Business Platform — Document signature verification report</p>

    @if(!empty($hash_matched) && !empty($matched_signatory))
        <div class="verified-badge">✓ Verified</div>
    @elseif(!empty($verification_attempted) && empty($hash_matched))
        <div class="failed-badge">✗ Failed</div>
    @endif

    <h2>Document</h2>
    <table class="meta-table">
        <tr><td>Document type</td><td>{{ $doc_type ?? 'N/A' }}</td></tr>
        <tr><td>Document number</td><td class="doc-number">{{ $document_number ?? 'N/A' }}</td></tr>
        @if(!empty($metadata['activity_title']))
        <tr><td>Activity title</td><td>{{ $metadata['activity_title'] }}</td></tr>
        @endif
        <tr><td>Creator</td><td>{{ $metadata['creator'] ?? 'N/A' }}</td></tr>
        <tr><td>Division</td><td>{{ $metadata['division'] ?? 'N/A' }}</td></tr>
        <tr><td>Date created</td><td>{{ $metadata['date_created'] ?? 'N/A' }}</td></tr>
    </table>

    @if(!empty($hash_matched) && !empty($matched_signatory))
    <h2>Matched signatory (hash verified)</h2>
    <table class="meta-table">
        <tr><td>Role</td><td>{{ $matched_signatory['role'] ?? '' }}</td></tr>
        <tr><td>Name</td><td>{{ $matched_signatory['name'] ?? '' }}</td></tr>
        <tr><td>Action</td><td>{{ $matched_signatory['action'] ?? '' }}</td></tr>
        <tr><td>Date / time</td><td>{{ $matched_signatory['date'] ?? '' }}</td></tr>
        <tr><td>Verify hash</td><td class="doc-number">{{ $matched_signatory['hash'] ?? '' }}</td></tr>
    </table>
    @endif

    <h2>Signatories and verification hashes</h2>
    <table>
        <thead>
            <tr>
                <th>Role</th>
                <th>Name</th>
                <th>Action</th>
                <th>Date / time</th>
                <th>Verify hash</th>
            </tr>
        </thead>
        <tbody>
            @forelse($signatories ?? [] as $s)
            <tr>
                <td>{{ $s['role'] ?? '' }}</td>
                <td>{{ $s['name'] ?? '' }}</td>
                <td>{{ $s['action'] ?? '' }}</td>
                <td>{{ $s['date'] ?? '' }}</td>
                <td class="doc-number">{{ $s['hash'] ?? '' }}</td>
            </tr>
            @empty
            <tr><td colspan="5">No signatories.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top: 20px; font-size: 9pt; color: #6c757d;">Generated on {{ date('d F Y H:i') }} — Africa CDC Central Business Platform</p>
</body>
</html>
