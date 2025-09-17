@extends('layouts.app')

@section('title', 'Staff Management')

@section('header', 'Staff Management')

@section('header-actions')
@php $isFocal = isfocal_person(); @endphp
@endsection

@section('styles')
<style>
    .staff-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 14px;
    }
    
    .filter-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 1px solid #dee2e6;
        border-radius: 0.75rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        color: #6c757d;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: none !important;
        color: white !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: none !important;
        color: white !important;
    }

    /* Table styling to match matrices */
    .table thead th {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border-bottom: 2px solid #f39c12;
        font-weight: 600;
        color: #856404;
        text-transform: uppercase;
        font-size: 0.875rem;
        letter-spacing: 0.5px;
        padding: 0.75rem 0.5rem;
    }

    .table td {
        vertical-align: middle;
        padding: 0.75rem 0.5rem;
        border-bottom: 1px solid #dee2e6;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    /* Ensure table fits without horizontal scroll */
    .table-responsive {
        overflow-x: auto;
        max-width: 100%;
    }

    /* Matrices-style card styling */
    .card {
        border-radius: 0.75rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .card-header {
        border-radius: 0.75rem 0.75rem 0 0;
    }

    /* Input group styling to match matrices */
    .input-group-text {
        border-right: none;
        background-color: #f8f9fa;
        border-color: #ced4da;
    }

    .form-control:focus + .input-group-text,
    .form-select:focus + .input-group-text {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* Success color scheme to match matrices */
    .text-success {
        color: #198754 !important;
    }

    .btn-success {
        background-color: #198754;
        border-color: #198754;
    }

    .btn-outline-success {
        color: #198754;
        border-color: #198754;
    }

    .btn-outline-success:hover {
        background-color: #198754;
        border-color: #198754;
    }
    
    .table th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
    }
    
    .status-badge {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.375rem 0.75rem;
        border-radius: 50px;
    }
    
    .status-active {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .status-inactive {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .action-btn {
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
        margin: 0 0.125rem;
        transition: all 0.2s ease;
    }
    
    .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .search-highlight {
        background-color: #fff3cd;
        padding: 0.125rem 0.25rem;
        border-radius: 0.25rem;
    }
 .dataTables_filter {
    margin-bottom: 6px !important;
    float: right !important;
}
</style>
@endsection

@section('content')


<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom">
            <div>
                <h5 class="mb-0 text-dark fw-bold">
                    <i class="bx bx-user me-2 text-success"></i>Staff Directory
                </h5>
                <small class="text-muted">View and manage staff information</small>
            </div>
    </div>
        <div class="table-responsive">
            <table id="staffTable" class="table table-hover mb-0" style="width:100%">
                <thead class="table-warning">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th style="width: 200px;">Division</th>
                        <th>Duty Station</th>
                        <th>Job Title</th>
                        <th>Contact</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be loaded via AJAX -->
                    <tr id="loadingRow" style="display: none;">
                        <td colspan="7" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                                    </div>
                            <div class="mt-2">Loading staff data...</div>
                            </td>
                        </tr>
                    <tr id="errorRow" style="display: none;">
                        <td colspan="7" class="text-center py-4 text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading staff data. Please check your connection and try again.
                            </td>
                        </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Staff Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this staff record?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-warning me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All related records will also be affected.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
    console.log('Initializing DataTable...');
    console.log('jQuery version:', $.fn.jquery);
    console.log('DataTables available:', typeof $.fn.DataTable !== 'undefined');
    
    // Check if DataTables is loaded
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables is not loaded!');
        alert('DataTables library is not loaded. Please check your scripts.');
        return;
    }
    
    // Initialize the DataTable using fallback method directly
    console.log('Initializing DataTable with fallback method...');
    loadStaffDataFallback();
    
    function initializeRealDataTable() {
        console.log('Initializing real DataTable...');
        
        // Show loading state
        $('#loadingRow').show();
        
        // Initialize DataTable with server-side processing
        var table = $('#staffTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('staff.datatable') }}",
            type: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: function(d) {
                // No filters - load all data
            },
            beforeSend: function(xhr) {
                console.log('Sending DataTable request to:', "{{ route('staff.datatable') }}");
            },
            success: function(data, textStatus, xhr) {
                console.log('DataTable success:', data);
                $('#loadingRow').hide();
                $('#errorRow').hide();
            },
            error: function(xhr, error, thrown) {
                console.error('DataTable AJAX error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error,
                    thrown: thrown
                });
                $('#loadingRow').hide();
                $('#errorRow').show();
                
                if (xhr.status === 302 || xhr.status === 401) {
                    console.log('Authentication error - trying fallback method...');
                    // Try to load data without server-side processing
                    loadStaffDataFallback();
                } else if (xhr.status === 404) {
                    alert('DataTable endpoint not found. Please check the route configuration.');
                } else {
                    alert('Error loading data: ' + (xhr.responseJSON?.message || 'Unknown error'));
                }
            }
        },
        columns: [
            {
                data: null,
                name: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { 
                data: 'name',
                name: 'name',
                render: function(data, type, row) {
                    var fullName = [row.fname, row.lname, row.oname].filter(Boolean).join(' ');
                    var displayName = row.title ? `${row.title} ${fullName}` : fullName;
                    return `
                        <div>
                            <strong>${displayName}</strong>
                            ${row.work_email ? `<br><small class="text-muted">${row.work_email}</small>` : ''}
                        </div>
                    `;
                }
            },
            { 
                data: 'division.division_name',
                name: 'division.division_name',
                defaultContent: 'N/A'
            },
                   {
                       data: 'duty_station_name',
                       name: 'duty_station_name',
                       defaultContent: 'N/A'
                   },
                   {
                       data: 'job_name',
                       name: 'job_name',
                       defaultContent: 'N/A'
                   },
            { 
                data: 'tel_1',
                name: 'tel_1',
                defaultContent: 'N/A'
            },
            { 
                data: 'active',
                name: 'active',
                render: function(data, type, row) {
                    var statusClass = data == 1 ? 'status-active' : 'status-inactive';
                    var statusText = data == 1 ? 'Active' : 'Inactive';
                    return `<span class="status-badge ${statusClass}">${statusText}</span>`;
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            processing: '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><br>Loading staff data...</div>',
            emptyTable: '<div class="text-center py-4"><i class="fas fa-users fa-3x text-muted mb-3"></i><br>No staff records found</div>',
            zeroRecords: '<div class="text-center py-4"><i class="fas fa-search fa-3x text-muted mb-3"></i><br>No matching records found</div>'
        },
        timeout: 30000, // 30 seconds timeout
        dom: '<"row"<"col-sm-12 col-md-6"l>>rtip',
        drawCallback: function(settings) {
            // Update total records count
            $('#totalRecords').text(settings.fnRecordsTotal());
            
        // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
        }
    });
    


        // Refresh table
        $('#refreshTable').on('click', function() {
            table.ajax.reload();
        });
    
    } // End of initializeRealDataTable function



    // Fallback function to load staff data without server-side processing
    function loadStaffDataFallback() {
        console.log('Loading staff data with fallback method...');
        $('#loadingRow').show();
        $('#errorRow').hide();
        
        // Try to load data directly from the controller
        $.ajax({
            url: "{{ route('staff.datatable') }}",
            type: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                console.log('Fallback success:', response);
                $('#loadingRow').hide();
                
                // Destroy existing table and create new one with data
                if ($.fn.DataTable.isDataTable('#staffTable')) {
                    $('#staffTable').DataTable().destroy();
                }
                
                // Create simple DataTable with the data
                $('#staffTable').DataTable({
                    data: response.data,
                    columns: [
                        { 
                            data: null,
                            render: function(data, type, row, meta) {
                                return meta.row + 1;
                            }
                        },
                        { 
                            data: 'name',
                            render: function(data, type, row) {
                                var fullName = [row.fname, row.lname, row.oname].filter(Boolean).join(' ');
                                var displayName = row.title ? `${row.title} ${fullName}` : fullName;
                                return `
                                    <div>
                                        <strong>${displayName}</strong>
                                        ${row.work_email ? `<br><small class="text-muted">${row.work_email}</small>` : ''}
                                    </div>
                                `;
                            }
                        },
                        { data: 'division.division_name', defaultContent: 'N/A' },
                        { data: 'duty_station_name', defaultContent: 'N/A' },
                        { data: 'job_name', defaultContent: 'N/A' },
                        { 
                            data: 'tel_1',
                            render: function(data, type, row) {
                                return `
                                    <div>
                                        <div><i class="fas fa-phone me-1"></i>${data || 'N/A'}</div>
                                        <div><i class="fas fa-envelope me-1"></i>${row.work_email || 'N/A'}</div>
                                    </div>
                                `;
                            }
                        },
                        { 
                            data: 'active',
                            render: function(data, type, row) {
                                var statusClass = data == 1 ? 'status-active' : 'status-inactive';
                                var statusText = data == 1 ? 'Active' : 'Inactive';
                                return `<span class="status-badge ${statusClass}">${statusText}</span>`;
                            }
                        }
                    ],
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    language: {
                        emptyTable: '<div class="text-center py-4"><i class="fas fa-users fa-3x text-muted mb-3"></i><br>No staff records found</div>',
                        zeroRecords: '<div class="text-center py-4"><i class="fas fa-search fa-3x text-muted mb-3"></i><br>No matching records found</div>'
                    }
                });
            },
            error: function(xhr, status, error) {
                console.error('Fallback also failed:', xhr.responseText);
                $('#loadingRow').hide();
                $('#errorRow').show();
            }
        });
    }
});

// Delete confirmation function
function confirmDelete(staffId) {
    $('#deleteForm').attr('action', `/staff/${staffId}`);
    $('#deleteModal').modal('show');
}
</script>
@endpush
