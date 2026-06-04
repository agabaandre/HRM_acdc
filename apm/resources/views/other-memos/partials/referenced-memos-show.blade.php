@php
    $refs = is_array($memo->referenced_memos ?? null) ? $memo->referenced_memos : [];
@endphp
@if (count($refs) > 0)
    <tr>
        <td class="field-label">
            <i class="bx bx-link-alt me-2 text-primary"></i>Referenced memos
        </td>
        <td class="field-value" colspan="3">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 bg-white">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 28%;">Document #</th>
                            <th>Title</th>
                            <th style="width: 14%;" class="text-center">Type</th>
                            <th style="width: 12%;" class="text-center">Preview</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($refs as $ref)
                            <tr>
                                <td>
                                    @if (! empty($ref['document_number']))
                                        <code class="text-primary">{{ $ref['document_number'] }}</code>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-wrap">{{ $ref['title'] ?? '—' }}</td>
                                <td class="text-center small text-muted">{{ $ref['memo_kind'] ?? 'Memo' }}</td>
                                <td class="text-center">
                                    @if (! empty($ref['url']))
                                        <a href="{{ $ref['url'] }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer" title="Open referenced memo in a new tab">
                                            <i class="bx bx-link-external"></i> Open
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-0 mt-2">These approved memos were cited by the submitter to support this request.</p>
        </td>
    </tr>
@endif
