@php
    $docSearchConfig = $docSearchConfig ?? [
        'searchUrl' => route('document-search.search'),
        'yearsUrl' => route('document-search.years'),
        'defaultYear' => (int) date('Y'),
        'placeholder' => 'Document number or title…',
    ];
    $docSearchMountId = $docSearchMountId ?? 'apm-document-search';
@endphp
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body">
        <div class="text-muted small mb-2">Look up a document by number or title</div>
        <div id="{{ $docSearchMountId }}" class="apm-document-search-mount">
            <script type="application/json" class="apm-document-search-config">@json($docSearchConfig)</script>
        </div>
    </div>
</div>
<script>
(function () {
    var mountId = @json($docSearchMountId);
    function bootDocSearch() {
        var el = document.getElementById(mountId);
        if (el && window.ApmDocumentSearch) {
            window.ApmDocumentSearch.bootFromDom(el);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootDocSearch);
    } else {
        bootDocSearch();
    }
    document.addEventListener('livewire:navigated', bootDocSearch);
})();
</script>
