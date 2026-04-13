@php
    $attachments = is_array($attachments ?? null) ? $attachments : [];
@endphp
<div class="card border mb-4" id="other-memo-attachments-card">
    <div class="card-header bg-light border-bottom py-2">
        <span class="fw-semibold text-success"><i class="bx bx-paperclip me-1"></i> Attachments</span>
    </div>
    <div class="card-body">
        @if (count($attachments) > 0)
            <div class="mb-4">
                <h6 class="text-muted mb-3">Current attachments</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Document type</th>
                                <th>File name</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attachments as $index => $attachment)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                    <td>{{ $attachment['original_name'] ?? 'Unknown' }}</td>
                                    <td>{{ isset($attachment['size']) ? round($attachment['size'] / 1024, 2) . ' KB' : 'N/A' }}</td>
                                    <td>{{ isset($attachment['uploaded_at']) ? \Carbon\Carbon::parse($attachment['uploaded_at'])->format('Y-m-d H:i') : 'N/A' }}</td>
                                    <td>
                                        @if (! empty($attachment['path']))
                                            <a href="{{ url('storage/' . $attachment['path']) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bx bx-show"></i> View
                                            </a>
                                            <a href="{{ url('storage/' . $attachment['path']) }}" download="{{ $attachment['original_name'] ?? 'file' }}" class="btn btn-sm btn-outline-success">
                                                <i class="bx bx-download"></i> Download
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="d-flex gap-2 mb-3">
            <button type="button" class="btn btn-danger btn-sm" id="addAttachment">Add New</button>
            <button type="button" class="btn btn-secondary btn-sm" id="removeAttachment">Remove</button>
        </div>
        <div class="row g-3" id="attachmentContainer">
            @if (count($attachments) > 0)
                @foreach ($attachments as $index => $attachment)
                    <div class="col-md-4 attachment-block">
                        <label class="form-label">Document type</label>
                        <input type="text" name="attachments[{{ $index }}][type]" class="form-control" value="{{ $attachment['type'] ?? '' }}">
                        <input type="file" name="attachments[{{ $index }}][file]" class="form-control mt-1 attachment-input" accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*">
                        <small class="text-muted">Current: {{ $attachment['original_name'] ?? 'No file' }}</small>
                        <small class="text-muted d-block">Leave empty to keep existing file</small>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="attachments[{{ $index }}][replace]" id="replace_{{ $index }}" value="1">
                            <label class="form-check-label" for="replace_{{ $index }}">
                                <small class="text-warning">Replace existing file</small>
                            </label>
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="attachments[{{ $index }}][delete]" id="delete_{{ $index }}" value="1">
                            <label class="form-check-label" for="delete_{{ $index }}">
                                <small class="text-danger">Delete this attachment</small>
                            </label>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <i class="bx bx-info-circle me-2"></i>
                        No attachments yet. Click <strong>Add New</strong> to add files.
                    </div>
                </div>
            @endif
        </div>
        <p class="small text-muted mt-2 mb-0">Max size 10 MB per file. Allowed: PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, DOCX.</p>
    </div>
</div>
