@extends('layouts.app')

@section('title', 'Returned Memos')

@section('header', 'My Returned/Draft Memos')

@section('header-actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-success" id="refreshData" style="border-color: #119A48; color: #119A48;">
        <i class="fas fa-sync-alt me-1"></i> Refresh
    </button>
    <button type="button" class="btn btn-outline-warning" id="exportData" style="border-color: #ffc107; color: #ffc107;">
        <i class="fas fa-download me-1"></i> Export
    </button>
</div>
@endsection

@section('styles')
<style>
    .status-badge {
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        text-transform: capitalize;
    }
    
    .status-returned { @apply bg-blue-100 text-blue-800 border border-blue-200; }
    .status-draft { @apply bg-gray-100 text-gray-800 border border-gray-200; }
    
    .gradient-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }
    
    .table-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(17, 154, 72, 0.08);
        overflow: hidden;
        border: 1px solid #e9f7ef;
    }
    
    .search-container {
        background: #e9f7ef;
        border-bottom: 1px solid #d1e7dd;
        padding: 1.5rem;
    }
    
    .table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-top: none;
        color: #495057;
    }
    
    .table td {
        vertical-align: middle;
        font-size: 0.9rem;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.25rem;
        flex-wrap: wrap;
    }
    
    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.8rem;
        border-radius: 4px;
    }
    
    .type-badge {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .type-matrix { background: #fff3cd; color: #856404; }
    .type-special { background: #d1ecf1; color: #0c5460; }
    .type-non-travel { background: #d4edda; color: #155724; }
    .type-single { background: #f8d7da; color: #721c24; }
    .type-service { background: #cce5ff; color: #004085; }
    .type-arf { background: #e2e3e5; color: #383d41; }
    .type-change { background: #fce4ec; color: #880e4f; }
    
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .stat-item {
        text-align: center;
        padding: 2rem 1.5rem;
        border-radius: 15px;
        background: white;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        min-height: 200px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .stat-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--stat-color), var(--stat-color-light));
    }

    .stat-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .stat-item.total {
        --stat-color: #119A48;
        --stat-color-light: #1bb85a;
    }

    .stat-item.total .stat-number-circle {
        background: #119A48 !important;
    }

    .stat-item.matrices {
        --stat-color: #ffc107;
        --stat-color-light: #ffed4e;
    }

    .stat-item.matrices .stat-number-circle {
        background: #ffc107 !important;
    }

    .stat-item.memos {
        --stat-color: #2c3e50;
        --stat-color-light: #34495e;
    }

    .stat-item.memos .stat-number-circle {
        background: #2c3e50 !important;
    }

    .stat-item.requests {
        --stat-color: #9f2240;
        --stat-color-light: #c44569;
    }

    .stat-item.requests .stat-number-circle {
        background: #9f2240 !important;
    }

    .stat-number-circle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: var(--stat-color);
        color: white;
        font-size: 3.5rem;
        font-weight: 900;
        margin-bottom: 0.5rem;
        text-shadow: 0 3px 6px rgba(0, 0, 0, 0.4);
        animation: countUp 1s ease-out;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        border: 4px solid rgba(255, 255, 255, 0.3);
        position: relative;
        z-index: 1;
    }

    .stat-number-circle::before {
        content: '';
        position: absolute;
        top: -4px;
        left: -4px;
        right: -4px;
        bottom: -4px;
        border-radius: 50%;
        background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.3));
        z-index: -1;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #495057;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .stat-icon {
        font-size: 2.5rem;
        color: var(--stat-color);
        margin-bottom: 1rem;
        display: block;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    @keyframes countUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Green theme button hover effects */
    .btn-outline-success:hover {
        background-color: #119A48 !important;
        border-color: #119A48 !important;
        color: white !important;
        box-shadow: 0 2px 8px rgba(17, 154, 72, 0.12);
    }

    .btn-outline-warning:hover {
        background-color: #ffc107 !important;
        border-color: #ffc107 !important;
        color: #000 !important;
        box-shadow: 0 2px 8px rgba(255, 193, 7, 0.12);
    }

    .btn-success:hover {
        background-color: #0e7c39 !important;
        border-color: #0e7c39 !important;
        box-shadow: 0 2px 8px rgba(17, 154, 72, 0.12);
    }

    .btn-warning:hover {
        background-color: #e0a800 !important;
        border-color: #e0a800 !important;
        color: #000 !important;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="stat-item total">
                <i class="fas fa-undo stat-icon"></i>
                <span class="stat-number-circle">{{ $summaryStats['total_returned'] ?? 0 }}</span>
                <span class="stat-label">Total Returned</span>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="stat-item matrices">
                <i class="fas fa-th-large stat-icon"></i>
                <span class="stat-number-circle">{{ $summaryStats['by_category']['Matrix'] ?? 0 }}</span>
                <span class="stat-label">Matrices</span>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="stat-item memos">
                <i class="fas fa-file-alt stat-icon"></i>
                <span class="stat-number-circle">{{ 
                    ($summaryStats['by_category']['Special Memo'] ?? 0) + 
                    ($summaryStats['by_category']['Non-Travel Memo'] ?? 0) + 
                    ($summaryStats['by_category']['Single Memo'] ?? 0) 
                }}</span>
                <span class="stat-label">Memos</span>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="stat-item requests">
                <i class="fas fa-clipboard-list stat-icon"></i>
                <span class="stat-number-circle">{{ 
                    ($summaryStats['by_category']['Service Request'] ?? 0) + 
                    ($summaryStats['by_category']['ARF'] ?? 0) 
                }}</span>
                <span class="stat-label">Requests</span>
            </div>
        </div>
    </div>

    <!-- Main Table Container -->
    <div class="table-container">
        <!-- Search and Filter Section -->
        <div class="search-container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="searchInput" class="form-label fw-semibold">
                        <i class="fas fa-search me-1"></i>Search
                    </label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by title, document number, or division...">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="categoryFilter" class="form-label fw-semibold">
                        <i class="fas fa-filter me-1"></i>Category
                    </label>
                    <select id="categoryFilter" class="form-select">
                        @foreach($groupedCategories as $category)
                            <option value="{{ $category['value'] }}" {{ $category == $category ? 'selected' : '' }}>
                                {{ $category['label'] }} ({{ $category['count'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="divisionFilter" class="form-label fw-semibold">
                        <i class="fas fa-building me-1"></i>Division
                    </label>
                    <select id="divisionFilter" class="form-select select2" style="width: 100%;">
                        <option value="all">All Divisions</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->id }}">{{ $division->division_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100" id="applyFilters" style="background-color: #119A48; border-color: #119A48;">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="table-responsive">
            @if($summaryStats['total_returned'] > 0)
                <table class="table table-hover mb-0" id="returnedMemosTable">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">Title</th>
                            <th width="10%">Type</th>
                            <th width="12%">Document #</th>
                            <th width="15%">Division</th>
                            <th width="12%">Submitted By</th>
                            <th width="10%">Date Returned</th>
                            <th width="8%">Status</th>
                            <th width="13%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rowIndex = 1; @endphp
                        @foreach($returnedMemos as $category => $items)
                            @foreach($items as $item)
                                <tr data-category="{{ $category }}" data-type="{{ $item['type'] }}">
                                    <td class="text-center">{{ $rowIndex++ }}</td>
                                    <td>
                                        <div class="fw-semibold text-truncate" style="max-width: 200px;" title="{{ $item['title'] }}">
                                            {{ $item['title'] }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="type-badge type-{{ strtolower(str_replace([' ', '-'], ['', ''], $item['type'])) }}">
                                            {{ $item['type'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $item['document_number'] }}</span>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $item['division'] }}</span>
                                    </td>
                                    <td>
                                        <span class="text-muted">{!! $item['submitted_by'] !!}</span>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $item['date_received']->format('M d, Y') }}</span>
                                        <br><small class="text-muted">{{ $item['date_received']->format('H:i') }}</small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-{{ $item['status'] }}">
                                            {{ ucfirst($item['status']) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="{{ $item['view_url'] }}" class="btn btn-success btn-sm" title="View" style="background-color: #119A48; border-color: #119A48;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($item['can_edit'] && $item['edit_url'])
                                                <a href="{{ $item['edit_url'] }}" class="btn btn-warning btn-sm" title="Edit" style="background-color: #ffc107; border-color: #ffc107; color: #000;">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @else
                                                <button class="btn btn-secondary btn-sm" disabled title="Edit not available">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            @endif
                                            
                                            @if($item['can_delete'] && $item['delete_url'])
                                                <button class="btn btn-danger btn-sm" onclick="confirmDelete('{{ $item['delete_url'] }}', '{{ $item['title'] }}')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-secondary btn-sm" disabled title="Delete not available">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No Returned Memos</h4>
                    <p>You don't have any returned memos at the moment.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this item?</p>
                <p><strong id="deleteItemTitle"></strong></p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let deleteUrl = '';
    
    // Refresh data
    $('#refreshData').click(function() {
        location.reload();
    });
    
    // Export data
    $('#exportData').click(function() {
        // TODO: Implement export functionality
        alert('Export functionality will be implemented soon.');
    });
    
    // Search functionality
    $('#searchInput').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterTable(searchTerm);
    });
    
    // Apply filters
    $('#applyFilters').click(function() {
        const category = $('#categoryFilter').val();
        const division = $('#divisionFilter').val();
        
        // Reload page with filters
        const url = new URL(window.location);
        url.searchParams.set('category', category);
        url.searchParams.set('division', division);
        window.location.href = url.toString();
    });
    
    // Filter table function
    function filterTable(searchTerm) {
        $('#returnedMemosTable tbody tr').each(function() {
            const row = $(this);
            const title = row.find('td:eq(1)').text().toLowerCase();
            const documentNumber = row.find('td:eq(3)').text().toLowerCase();
            const division = row.find('td:eq(4)').text().toLowerCase();
            const submittedBy = row.find('td:eq(5)').text().toLowerCase();
            const type = row.find('td:eq(2)').text().toLowerCase();
            
            const matchesSearch = title.includes(searchTerm) || 
                                documentNumber.includes(searchTerm) || 
                                division.includes(searchTerm) || 
                                submittedBy.includes(searchTerm) ||
                                type.includes(searchTerm);
            
            if (matchesSearch) {
                row.show();
            } else {
                row.hide();
            }
        });
        
        // Update row numbers
        updateRowNumbers();
    }
    
    // Update row numbers after filtering
    function updateRowNumbers() {
        let visibleIndex = 1;
        $('#returnedMemosTable tbody tr:visible').each(function() {
            $(this).find('td:first').text(visibleIndex++);
        });
    }
    
    // Confirm delete
    window.confirmDelete = function(url, title) {
        deleteUrl = url;
        $('#deleteItemTitle').text(title);
        $('#deleteModal').modal('show');
    };
    
    $('#confirmDeleteBtn').click(function() {
        if (deleteUrl) {
            // Create a form to submit DELETE request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = deleteUrl;
            
            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);
            
            // Add method override
            const methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
    
    // Initialize table
    updateRowNumbers();
});
</script>
@endpush
