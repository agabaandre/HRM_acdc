# Email Configuration Guide

This document explains the environment variables needed for the APM email system, which supports both Exchange (Office 365) and PHPMailer (SMTP) email services.

## Overview

The APM system uses a dual email approach:
- **Primary**: Exchange Email Service (Office 365) via Microsoft Graph API
- **Fallback**: PHPMailer (SMTP) for traditional email sending

The system automatically chooses between them based on the `USE_EXCHANGE_EMAIL` environment variable.

**All emails automatically include `system@africacdc.org` as BCC for audit and monitoring purposes.**

## Environment Variables

### Exchange Email Service (Office 365)

```env
# Exchange Email Configuration
USE_EXCHANGE_EMAIL=true
EXCHANGE_TENANT_ID=your_tenant_id
EXCHANGE_CLIENT_ID=your_client_id
EXCHANGE_CLIENT_SECRET=your_client_secret
EXCHANGE_REDIRECT_URI=http://localhost:8000/oauth/callback
EXCHANGE_SCOPE=https://graph.microsoft.com/.default

# Exchange Email Settings
MAIL_FROM_ADDRESS=notifications@africacdc.org
MAIL_FROM_NAME=Africa CDC APM
```

### PHPMailer SMTP (Fallback)

```env
# PHPMailer SMTP Configuration (separate from Exchange)
PHPMailer_HOST=smtp.gmail.com
PHPMailer_USERNAME=your_smtp_username@gmail.com
PHPMailer_PASSWORD=your_smtp_app_password
PHPMailer_PORT=587
PHPMailer_FROM_ADDRESS=notifications@africacdc.org
PHPMailer_FROM_NAME=Africa CDC APM

# Fallback to general MAIL_* variables if PHPMailer_* not set
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your_smtp_username@gmail.com
MAIL_PASSWORD=your_smtp_app_password
MAIL_PORT=587
MAIL_FROM_ADDRESS=notifications@africacdc.org
MAIL_FROM_NAME=Africa CDC APM
```

## Configuration Priority

### Exchange Email
1. Uses `EXCHANGE_*` variables for authentication
2. Uses `MAIL_FROM_*` variables for sender information
3. Controlled by `USE_EXCHANGE_EMAIL=true`

### PHPMailer SMTP
1. **Primary**: Uses `PHPMailer_*` variables if available
2. **Fallback**: Uses `MAIL_*` variables if `PHPMailer_*` not set
3. **Default**: Uses `mail()` function if no SMTP configuration available

## Setup Instructions

### 1. Exchange Email Setup

1. **Create Azure App Registration**:
   - Go to Azure Portal > App registrations
   - Create new registration
   - Note down `Application (client) ID` and `Directory (tenant) ID`

2. **Generate Client Secret**:
   - Go to Certificates & secrets
   - Create new client secret
   - Note down the secret value

3. **Configure API Permissions**:
   - Add Microsoft Graph > Application permissions
   - Grant `Mail.Send` permission
   - Admin consent required

4. **Set Environment Variables**:
   ```env
   USE_EXCHANGE_EMAIL=true
   EXCHANGE_TENANT_ID=your_tenant_id
   EXCHANGE_CLIENT_ID=your_client_id
   EXCHANGE_CLIENT_SECRET=your_client_secret
   MAIL_FROM_ADDRESS=notifications@africacdc.org
   MAIL_FROM_NAME=Africa CDC APM
   ```

### 2. PHPMailer SMTP Setup

1. **Choose SMTP Provider** (Gmail example):
   - Enable 2-factor authentication
   - Generate App Password
   - Use App Password as `PHPMailer_PASSWORD`

2. **Set Environment Variables**:
   ```env
   PHPMailer_HOST=smtp.gmail.com
   PHPMailer_USERNAME=your_email@gmail.com
   PHPMailer_PASSWORD=your_app_password
   PHPMailer_PORT=587
   PHPMailer_FROM_ADDRESS=notifications@africacdc.org
   PHPMailer_FROM_NAME=Africa CDC APM
   ```

## Testing

### Test Exchange Email
```bash
php artisan tinker
>>> sendMatrixNotification($matrix, $staff, 'matrix_approval', 'Test message');
```

### Test PHPMailer
```bash
# Temporarily set USE_EXCHANGE_EMAIL=false
# Then test the same function
```

## Troubleshooting

### Exchange Issues
- Verify Azure app permissions
- Check tenant ID and client ID
- Ensure client secret is valid
- Verify `MAIL_FROM_ADDRESS` is authorized

### PHPMailer Issues
- Check SMTP credentials
- Verify port and encryption settings
- Test with `mail()` function if SMTP fails
- Check firewall settings

## BCC Functionality

**Automatic BCC**: All emails sent through the APM system automatically include `system@africacdc.org` as a BCC recipient for:
- **Audit Trail**: Complete record of all system emails
- **Monitoring**: Track email delivery and system activity
- **Compliance**: Meet organizational email retention requirements
- **Debugging**: Troubleshoot email delivery issues

**No Duplication**: If `system@africacdc.org` is already in the BCC list, it won't be added again.

## Security Notes

- Store all credentials in `.env` file
- Never commit `.env` to version control
- Use App Passwords for Gmail
- Rotate client secrets regularly
- Use least privilege principle for permissions
- All emails are automatically BCC'd to `system@africacdc.org` for audit purposes

## Support

For issues with email configuration, check:
1. Laravel logs: `storage/logs/laravel.log`
2. Exchange API logs in Azure Portal
3. SMTP server logs
4. Network connectivity
