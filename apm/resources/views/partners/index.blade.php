@extends('layouts.app')

@section('title', 'Partners')

@section('header', 'Partners')

@section('header-actions')
<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createPartnerModal">
    <i class="bx bx-plus"></i> Add Partner
</button>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>All Partners</h5>
        <div>
            <form action="{{ route('partners.index') }}" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search partners..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bx bx-search"></i>
                </button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Fund Codes</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($partners as $partner)
                        <tr>
                            <td>{{ $partner->id }}</td>
                            <td>{{ $partner->name }}</td>
                            <td>
                                <span class="badge bg-info">{{ $partner->fund_codes_count }} Codes</span>
                            </td>
                            <td>{{ $partner->created_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('partners.show', $partner) }}" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-partner" data-bs-toggle="tooltip" title="Edit"
                                        data-id="{{ $partner->id }}" data-name="{{ e($partner->name) }}" data-url="{{ route('partners.update', $partner) }}">
                                        <i class="bx bx-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="mb-3">
                                        <i class="bx bx-folder-open text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-muted mb-3">No partners found</h5>
                                    <p class="text-muted mb-4">Get started by adding your first partner</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPartnerModal">
                                        <i class="bx bx-plus"></i> Add Partner
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($partners->hasPages())
        <div class="card-footer">
            {{ $partners->links() }}
        </div>
    @endif
</div>

<!-- Create Partner Modal -->
<div class="modal fade" id="createPartnerModal" tabindex="-1" aria-labelledby="createPartnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createPartnerModalLabel"><i class="bx bx-plus-circle me-2"></i>New Partner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createPartnerForm" action="{{ route('partners.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="create_partner_name" class="form-label fw-semibold">Partner Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" id="create_partner_name"
                            name="name" value="{{ old('name') }}" placeholder="Enter partner name" required>
                        <div class="invalid-feedback" id="createPartnerNameError"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="createPartnerSubmitBtn">
                        <i class="bx bx-save me-1"></i> Save Partner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Partner Modal -->
<div class="modal fade" id="editPartnerModal" tabindex="-1" aria-labelledby="editPartnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editPartnerModalLabel"><i class="bx bx-edit me-2"></i>Edit Partner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPartnerForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_partner_name" class="form-label fw-semibold">Partner Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg" id="edit_partner_name"
                            name="name" placeholder="Enter partner name" required>
                        <div class="invalid-feedback" id="editPartnerNameError"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editPartnerSubmitBtn">
                        <i class="bx bx-save me-1"></i> Update Partner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (el) { new bootstrap.Tooltip(el); });

        // Create partner form: AJAX submit
        $('#createPartnerForm').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $name = $('#create_partner_name');
            var $err = $('#createPartnerNameError');
            var $btn = $('#createPartnerSubmitBtn');
            $name.removeClass('is-invalid');
            $err.text('');
            $btn.prop('disabled', true);
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).done(function() {
                $('#createPartnerModal').modal('hide');
                $form[0].reset();
                window.location.reload();
            }).fail(function(xhr) {
                $btn.prop('disabled', false);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var err = xhr.responseJSON.errors.name;
                    if (err && err.length) {
                        $name.addClass('is-invalid');
                        $err.text(err[0]);
                    }
                } else {
                    alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'An error occurred. Please try again.');
                }
            });
        });
        $('#createPartnerModal').on('hidden.bs.modal', function() {
            $('#create_partner_name').removeClass('is-invalid');
            $('#createPartnerNameError').text('');
            $('#createPartnerSubmitBtn').prop('disabled', false);
        });

        // Edit partner: open modal with data
        $(document).on('click', '.btn-edit-partner', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var url = $(this).data('url');
            $('#editPartnerForm').attr('action', url);
            $('#edit_partner_name').val(name).removeClass('is-invalid');
            $('#editPartnerNameError').text('');
            $('#editPartnerModal').modal('show');
        });

        // Edit partner form: AJAX submit
        $('#editPartnerForm').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $name = $('#edit_partner_name');
            var $err = $('#editPartnerNameError');
            var $btn = $('#editPartnerSubmitBtn');
            $name.removeClass('is-invalid');
            $err.text('');
            $btn.prop('disabled', true);
            var data = $form.serialize() + '&_method=PUT';
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).done(function() {
                $('#editPartnerModal').modal('hide');
                window.location.reload();
            }).fail(function(xhr) {
                $btn.prop('disabled', false);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var err = xhr.responseJSON.errors.name;
                    if (err && err.length) {
                        $name.addClass('is-invalid');
                        $err.text(err[0]);
                    }
                } else {
                    alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'An error occurred. Please try again.');
                }
            });
        });
        $('#editPartnerModal').on('hidden.bs.modal', function() {
            $('#edit_partner_name').removeClass('is-invalid');
            $('#editPartnerNameError').text('');
            $('#editPartnerSubmitBtn').prop('disabled', false);
        });
    });
</script>
@endpush
