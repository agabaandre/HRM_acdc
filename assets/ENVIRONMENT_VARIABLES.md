# Environment Variables Configuration

## File storage (uploads)

Staff photos, memo attachments, helpdesk files, and other uploads should live **outside the git repo** in production.

> **Full guide:** [docs/STORAGE.md](../docs/STORAGE.md)

### Core variables (root `.env`, mirrored in APM / Helpdesk / staff-portal)

```env
# Production — host-side storage
STAFF_SITE_ID=localhost-staff
STAFF_DATA_ROOT=/var/staffdata/localhost-staff
STAFF_USE_HOST_STORAGE=true

# Per-module overrides (optional)
STAFF_PORTAL_UPLOADS_ROOT=/var/staffdata/localhost-staff/ci
STAFF_APM_FILES_ROOT=/var/staffdata/localhost-staff/apm
STAFF_HELPDESK_FILES_ROOT=/var/staffdata/localhost-staff/helpdesk
STAFF_PORTAL_MODULE_FILES_ROOT=/var/staffdata/localhost-staff/staff-portal
```

When unset, apps use in-repo paths (`uploads/`, `storage/app/public`) — normal for local development.

### Knowledge Hub integration

On the Knowledge Hub server:

```env
HUB_STAFF_STORAGE_ENABLED=true
STAFF_REPO_ROOT=/path/to/staff
STAFF_BASE_URL=http://localhost/staff
```

---

## Alternative Login Configuration

The application supports an environment variable to control whether alternative sign-in methods are available on the login page.

### Variable: `ALLOW_ALTERNATIVE_LOGIN`

- **Type**: Boolean
- **Default**: `true`
- **Description**: Controls whether the alternative sign-in form (email/password) is displayed on the login page

### Usage Examples

```bash
# Enable alternative login (default)
ALLOW_ALTERNATIVE_LOGIN=true

# Disable alternative login (only Microsoft SSO)
ALLOW_ALTERNATIVE_LOGIN=false
```

### Implementation

The variable is checked in `application/modules/auth/views/login/login.php`:

```php
<?php 
// Check environment variable for alternative login, default to true
$allowAlternativeLogin = getenv('ALLOW_ALTERNATIVE_LOGIN');
$allowAlternativeLogin = $allowAlternativeLogin !== false ? filter_var($allowAlternativeLogin, FILTER_VALIDATE_BOOLEAN) : true;
?>
<?php if ($allowAlternativeLogin): ?>
    <!-- Alternative Login Form -->
<?php endif; ?>
```

### Security Considerations

- When set to `false`, only Microsoft SSO authentication is available
- This can be useful for production environments where you want to enforce single sign-on
- The variable defaults to `true` to maintain backward compatibility
