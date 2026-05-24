@php
    $attachments = $attachments ?? [];
    $cardTitle = $cardTitle ?? 'Attachments';
    $emptyMessage = $emptyMessage ?? 'No files uploaded.';
@endphp
<div class="card sidebar-card border-0 mb-4">
    <div class="card-header bg-transparent border-0 py-3">
        <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
            <i class="bx bx-paperclip"></i>
            {{ $cardTitle }}
            @if (count($attachments) > 0)
                <span class="badge bg-info ms-1">{{ count($attachments) }} file(s)</span>
            @endif
        </h6>
    </div>
    <div class="card-body">
        @if (count($attachments) > 0)
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>File Name</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attachments as $index => $attachment)
                            @php
                                $originalName =
                                    $attachment['original_name'] ??
                                    ($attachment['filename'] ?? ($attachment['name'] ?? 'Unknown'));
                                $filePath = $attachment['path'] ?? ($attachment['file_path'] ?? '');
                                $ext = $filePath
                                    ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION))
                                    : '';
                                $fileUrl = $filePath ? url('storage/' . $filePath) : '#';
                                $isOffice = in_array($ext, ['ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'], true);
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                <td>{{ $originalName }}</td>
                                <td>{{ isset($attachment['size']) ? round($attachment['size'] / 1024, 2) . ' KB' : 'N/A' }}</td>
                                <td>{{ isset($attachment['uploaded_at']) ? \Carbon\Carbon::parse($attachment['uploaded_at'])->format('Y-m-d H:i') : 'N/A' }}</td>
                                <td>
                                    @if ($filePath)
                                        <button type="button"
                                            class="btn btn-sm btn-success preview-attachment"
                                            data-file-url="{{ $fileUrl }}"
                                            data-file-ext="{{ $ext }}"
                                            data-file-office="{{ $isOffice ? '1' : '0' }}">
                                            <i class="bx bx-show"></i> Preview
                                        </button>
                                        <a href="{{ $fileUrl }}" target="_blank" class="btn btn-sm btn-success">
                                            <i class="bx bx-download"></i> Download
                                        </a>
                                    @else
                                        <span class="text-muted">File not found</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">{{ $emptyMessage }}</p>
        @endif
    </div>
</div>
