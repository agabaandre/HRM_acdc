@php
    $docSearchConfig = $docSearchConfig ?? [
        'searchUrl' => route('document-search.search'),
        'yearsUrl' => route('document-search.years'),
        'defaultYear' => (int) date('Y'),
        'placeholder' => 'Document number…',
    ];
    $docSearchMountId = $docSearchMountId ?? 'apm-document-search';
@endphp
<div id="{{ $docSearchMountId }}" class="apm-document-search-mount mb-3">
    <script type="application/json" class="apm-document-search-config">@json($docSearchConfig)</script>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById(@json($docSearchMountId));
    if (el && window.ApmDocumentSearch) {
        window.ApmDocumentSearch.bootFromDom(el);
    }
});
</script>
