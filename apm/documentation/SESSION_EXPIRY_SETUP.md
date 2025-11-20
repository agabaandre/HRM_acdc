# Session Expiry Management Setup

This document explains how to set up session expiry management in the Laravel APM application.

## Features

- **Session Monitoring**: Automatically monitors user session activity
- **Warning Dialog**: Shows a 5-minute warning before session expires
- **Keep Me Logged In**: Option to extend session without re-login
- **CI Integration**: Validates session with CodeIgniter application
- **Automatic Logout**: Logs out user when session expires

## Setup Instructions

### 1. Environment Configuration

Add the following to your `.env` file:

```env
# CI Application Configuration
CI_BASE_URL=http://localhost/staff
```

### 2. Session Configuration

Ensure your session configuration in `config/session.php` is properly set:

```php
'lifetime' => env('SESSION_LIFETIME', 120), // 2 hours in minutes
```

### 3. Middleware Registration

The session expiry middleware is already registered in `bootstrap/app.php`:

```php
$middleware->web(append: [
    \App\Http\Middleware\CheckSessionExpiry::class,
]);
```

### 4. CI App API Endpoints

The following endpoints have been added to the CI application:

- `GET /api/validate-session` - Validates session token
- `POST /api/refresh-token` - Refreshes session token

### 5. Laravel API Endpoints

The following endpoints are available in the Laravel app:

- `GET /api/validate-session` - Validates session with CI app
- `POST /api/extend-session` - Extends current session
- `GET /api/session-status` - Gets current session status

## How It Works

1. **Activity Tracking**: The system tracks user activity (mouse, keyboard, scroll, etc.)
2. **Session Monitoring**: Every 30 seconds, checks if session is about to expire
3. **Warning Display**: Shows a 5-minute countdown warning before expiry
4. **User Choice**: User can choose to extend session or log out
5. **CI Validation**: Validates session with CI app before extending
6. **Automatic Logout**: Logs out user if session expires or CI validation fails

## Customization

### Warning Time
To change the warning time (default: 5 minutes), modify the `warningTime` property in `public/js/session-monitor.js`:

```javascript
this.warningTime = 5 * 60; // 5 minutes in seconds
```

### Check Interval
To change the check interval (default: 30 seconds), modify the `checkInterval` property:

```javascript
this.checkInterval = 30; // Check every 30 seconds
```

### Session Lifetime
To change the session lifetime, update the `SESSION_LIFETIME` environment variable:

```env
SESSION_LIFETIME=180 # 3 hours in minutes
```

## Troubleshooting

### Session Not Extending
- Check if CI app is accessible from Laravel app
- Verify CI_BASE_URL is correctly configured
- Check Laravel logs for API communication errors

### Warning Not Showing
- Ensure user is logged in (check meta tag `user-logged-in`)
- Verify JavaScript is loading without errors
- Check browser console for JavaScript errors

### CI Validation Failing
- Ensure CI app has the new API endpoints
- Check CI app logs for authentication errors
- Verify token format and encoding

## Security Notes

- Sessions are validated against the CI app for security
- Failed validations result in immediate logout
- Tokens are refreshed automatically when needed
- All API communications are logged for debugging

## Files Modified/Created

### Laravel App
- `app/Http/Middleware/CheckSessionExpiry.php` - Session expiry middleware
- `app/Http/Controllers/Api/SessionController.php` - Session management API
- `resources/views/components/session-expiry-modal.blade.php` - Warning modals
- `public/js/session-monitor.js` - Client-side session monitoring
- `resources/views/layouts/app.blade.php` - Updated to include components
- `routes/web.php` - Added API routes
- `bootstrap/app.php` - Registered middleware
- `config/app.php` - Added CI base URL configuration

### CI App
- `application/modules/share/controllers/Share.php` - Added session validation endpoints

## Testing

1. Log into the application
2. Wait for the warning dialog to appear (or manually trigger by modifying the warning time)
3. Test "Keep Me Logged In" functionality
4. Test "Log Out Now" functionality
5. Test automatic logout when session expires
