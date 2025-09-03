# Audit Logs Module

## Overview
The Audit Logs module provides comprehensive monitoring and tracking of user activities across the APM system. It automatically captures user actions, system changes, and provides detailed audit trails for compliance and security purposes.

## Features

### 🔍 **Comprehensive Monitoring**
- **User Activities**: Login, logout, and all user interactions
- **CRUD Operations**: Create, Read, Update, Delete operations on all resources
- **Approval Workflows**: Approve, reject, and workflow state changes
- **System Actions**: Job executions, configuration changes, and maintenance tasks

### 📊 **Advanced Search & Filtering**
- **Text Search**: Search across user names, emails, actions, descriptions, and URLs
- **Filter by Action**: Filter by specific actions (CREATE, UPDATE, DELETE, APPROVE, etc.)
- **Filter by Resource**: Filter by resource types (Matrix, NonTravelMemo, SpecialMemo, etc.)
- **Filter by User**: Filter by specific users
- **Date Range**: Filter by date ranges
- **Route Filtering**: Filter by specific routes

### 📈 **Statistics & Analytics**
- **Total Logs**: Complete count of all audit logs
- **Recent Activity**: Activity in the last 24 hours
- **Top Actions**: Most frequently performed actions
- **Top Resources**: Most frequently accessed resources
- **Top Users**: Most active users

### 📤 **Export & Reporting**
- **CSV Export**: Export filtered audit logs to CSV format
- **Detailed Views**: Comprehensive view of individual audit log entries
- **Data Changes**: Track old vs new values for updates
- **Metadata**: Additional context and system information

### 🧹 **Automatic Cleanup**
- **Retention Period**: Configurable retention period (default: 60 days)
- **Automatic Cleanup**: Scheduled cleanup of old audit logs
- **Manual Cleanup**: On-demand cleanup via UI or command line

## Monitored Routes

The audit system monitors the following critical routes:

### Matrix Management
- `matrices.*` - All matrix-related operations
- `matrix.*` - Individual matrix operations

### Memo Management
- `non-travel.*` - Non-travel memo operations
- `special-memo.*` - Special memo operations

### Request Management
- `arf.*` - ARF request operations
- `request.*` - General request operations

### Activity Management
- `activities.*` - Activity operations
- `activity.*` - Individual activity operations

### System Management
- `jobs.*` - Job execution and system maintenance
- `workflows.*` - Workflow management
- `users.*` - User management

### Authentication
- `login` - User login
- `logout` - User logout

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
# Audit Log Configuration
AUDIT_LOG_ENABLED=true
AUDIT_LOG_RETENTION_DAYS=60
AUDIT_LOG_MIDDLEWARE_GROUP=web
```

### Configuration File

The audit configuration is located in `config/audit.php`:

```php
return [
    'retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 60),
    'enabled' => env('AUDIT_LOG_ENABLED', true),
    'middleware_group' => env('AUDIT_LOG_MIDDLEWARE_GROUP', 'web'),
    'excluded_routes' => [
        'audit-logs.*',
        'telescope.*',
        'horizon.*',
        'log-viewer.*',
    ],
    'excluded_methods' => [
        'GET',
        'HEAD',
        'OPTIONS',
    ],
    'sensitive_fields' => [
        'password',
        'password_confirmation',
        'token',
        '_token',
        'api_token',
        'secret',
        'key',
        'private_key',
        'credit_card',
        'ssn',
        'social_security_number',
    ],
];
```

## Usage

### Accessing Audit Logs

1. Navigate to **Settings** → **Audit Logs** in the main navigation
2. Use the filters to narrow down your search
3. Click on individual logs to view detailed information
4. Export logs to CSV for external analysis

### Command Line Operations

#### Cleanup Old Logs
```bash
# Clean up logs older than default retention period (60 days)
php artisan audit:cleanup

# Clean up logs older than specified days
php artisan audit:cleanup --days=30
```

#### View Help
```bash
php artisan audit:cleanup --help
```

### Programmatic Usage

#### Creating Audit Logs
```php
use App\Models\AuditLog;

AuditLog::create([
    'user_id' => auth()->id(),
    'user_name' => auth()->user()->name,
    'user_email' => auth()->user()->email,
    'action' => 'CREATE',
    'resource_type' => 'Matrix',
    'resource_id' => 123,
    'route_name' => 'matrices.store',
    'url' => request()->fullUrl(),
    'method' => 'POST',
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'description' => 'Created new matrix',
    'metadata' => ['additional' => 'context']
]);
```

#### Querying Audit Logs
```php
use App\Models\AuditLog;

// Get logs by user
$userLogs = AuditLog::byUser($userId)->get();

// Get logs by action
$createLogs = AuditLog::byAction('CREATE')->get();

// Get logs by resource type
$matrixLogs = AuditLog::byResourceType('Matrix')->get();

// Get logs by date range
$recentLogs = AuditLog::byDateRange(
    Carbon::now()->subDays(7),
    Carbon::now()
)->get();
```

## Database Schema

The audit logs are stored in the `audit_logs` table with the following structure:

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    user_name VARCHAR(255) NULL,
    user_email VARCHAR(255) NULL,
    action VARCHAR(255) NOT NULL,
    resource_type VARCHAR(255) NOT NULL,
    resource_id BIGINT UNSIGNED NULL,
    route_name VARCHAR(255) NULL,
    url VARCHAR(255) NOT NULL,
    method VARCHAR(255) NOT NULL,
    ip_address VARCHAR(255) NULL,
    user_agent TEXT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    description TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_action_created (action, created_at),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_route_created (route_name, created_at),
    INDEX idx_created (created_at)
);
```

## Security Considerations

### Sensitive Data Protection
- Passwords and sensitive fields are automatically excluded
- IP addresses and user agents are logged for security
- All data is stored securely with proper indexing

### Performance Optimization
- Database indexes on frequently queried columns
- Pagination for large result sets
- Automatic cleanup to prevent database bloat
- Efficient querying with Eloquent scopes

### Compliance
- Comprehensive audit trail for regulatory compliance
- Detailed logging of all user actions
- Export capabilities for external auditing
- Configurable retention periods

## Troubleshooting

### Common Issues

1. **Audit logs not being created**
   - Check if `AUDIT_LOG_ENABLED=true` in `.env`
   - Verify middleware is registered in `bootstrap/app.php`
   - Check Laravel logs for any errors

2. **Performance issues**
   - Ensure database indexes are created
   - Run cleanup command regularly
   - Consider adjusting retention period

3. **Missing data**
   - Check if routes are included in auditable routes
   - Verify middleware is applied to correct route groups
   - Check for any excluded routes or methods

### Log Files
- Check `storage/logs/laravel.log` for audit-related errors
- Monitor database performance with audit log queries
- Use Laravel Telescope for debugging (if installed)

## Support

For issues or questions regarding the Audit Logs module:
1. Check the Laravel logs for error messages
2. Verify configuration settings
3. Test with a simple action to ensure logging works
4. Contact system administrator for assistance
