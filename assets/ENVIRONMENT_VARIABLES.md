# Environment Variables Configuration

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
