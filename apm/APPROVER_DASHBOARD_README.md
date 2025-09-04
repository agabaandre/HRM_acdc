# Approver Dashboard

A comprehensive dashboard for monitoring pending approvals across all document types in the APM system.

## Features

### 📊 **Real-time Monitoring**
- Live view of pending documents for each approver
- Breakdown by document type (Matrix, Non-Travel Memos, Special Memos, ARF Requests, Service Requests)
- Current approval level tracking

### 🔍 **Advanced Filtering**
- Search by approver name or email
- Filter by division
- Filter by document type
- Filter by date range
- Filter by workflow definition

### 📈 **Statistics Dashboard**
- Total approvers count
- Total pending documents
- Active workflow information
- Last updated timestamp

### 🚀 **Performance Optimized**
- Server-side pagination (25, 50, 100 records per page)
- Efficient database queries with proper joins
- Real-time data refresh every 5 minutes
- Responsive Bootstrap table interface

## API Endpoints

### GET `/approver-dashboard`
Main dashboard page with full interface.

### GET `/api/approver-dashboard`
JSON API for dashboard data with pagination and filtering.

**Query Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Records per page (default: 25, max: 100)
- `q` - Search query for approver name/email
- `division_id` - Filter by division
- `doc_type` - Filter by document type
- `date_from` - Start date filter
- `date_to` - End date filter
- `workflow_definition_id` - Filter by workflow

**Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "approver_id": 1,
      "approver_name": "John Doe",
      "approver_email": "john@example.com",
      "division_name": "IT Division",
      "level_no": 1,
      "pending_counts": {
        "matrix": 5,
        "non_travel": 3,
        "single_memos": 0,
        "special": 2,
        "memos": 0,
        "arf": 1,
        "requests_for_service": 0
      },
      "total_pending": 11
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 25,
    "total": 50,
    "last_page": 2,
    "from": 1,
    "to": 25
  }
}
```

### GET `/api/approver-dashboard/filter-options`
Get available filter options (divisions, workflows, document types).

### GET `/api/approver-dashboard/summary-stats`
Get summary statistics for the dashboard.

## Document Types Monitored

1. **Matrix** - Quarterly matrices
2. **Non-Travel Memos** - Non-travel related memos
3. **Single Memos** - Single activity memos
4. **Special Memos** - Special activity memos
5. **Memos** - General memos
6. **ARF Requests** - Activity Request Forms
7. **Requests for Service** - Service requests

## Database Schema

The dashboard works with the following tables:
- `workflows` - Workflow definitions
- `workflow_definition` - Workflow approval levels and roles
- `staff` - Staff members (approvers)
- `divisions` - Organizational divisions
- `matrices` - Matrix documents
- `non_travel_memos` - Non-travel memo documents
- `special_memos` - Special memo documents
- `request_arfs` - ARF request documents
- `service_requests` - Service request documents

## Security

- All inputs are sanitized and validated
- Results are limited by user permissions
- SQL injection protection through parameterized queries
- CSRF protection on all forms

## Usage

1. **Access the Dashboard**: Navigate to "Approver Dashboard" in the main navigation
2. **Apply Filters**: Use the filter panel to narrow down results
3. **View Details**: Click "View" button for detailed approver information
4. **Export Data**: Use the export button to download data as CSV
5. **Refresh**: Click refresh button or wait for auto-refresh (5 minutes)

## Technical Notes

- Built with Laravel 11 and Bootstrap 5
- Uses jQuery for AJAX interactions
- Responsive design for mobile and desktop
- Optimized for large datasets with pagination
- Real-time updates without page refresh

## Future Enhancements

- Email notifications for high pending counts
- Approval deadline tracking
- Historical approval trends
- Bulk approval actions
- Custom dashboard widgets
- Mobile app integration
