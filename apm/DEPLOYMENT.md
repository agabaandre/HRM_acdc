# Deployment Guide

## Quick Setup Commands

### 1. Install Dependencies
```bash
composer install --optimize-autoloader --no-dev
npm install --production
npm run build
```

### 2. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup
```bash
php artisan migrate --force
```

### 4. Create Uploads Symlink
```bash
php artisan uploads:link
```

### 5. Clear Caches
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

## One-Command Deployment

Use the composer script for complete deployment:
```bash
composer run deploy
```

This will:
- Clear all caches
- Create/update uploads symlink
- Run database migrations

## Uploads Symlink

The `uploads:link` command creates a symbolic link from `/opt/homebrew/var/www/staff/uploads` to `public/uploads`, making uploaded files accessible via web.

### Manual Command
```bash
php artisan uploads:link
```

### Force Recreation
```bash
php artisan uploads:link --force
```

### Directory Structure
```
/opt/homebrew/var/www/staff/uploads/
├── staff/
│   └── signature/     # Staff signature images
├── summernote/        # Rich text editor uploads
└── ...                # Other uploaded documents
```

## Troubleshooting

### Symlink Issues
- Ensure the web server has permission to create symlinks
- On Windows, the command uses `mklink /J` (junction)
- On Unix-like systems, it uses `symlink()`

### Permission Issues
```bash
chmod -R 755 storage/app/uploads
chown -R www-data:www-data storage/app/uploads
```

### Verify Symlink
```bash
ls -la public/uploads
# Should show: lrwxr-xr-x ... public/uploads -> /path/to/storage/app/uploads
```

## Production Considerations

1. **File Permissions**: Ensure proper permissions on uploads directory
2. **Storage**: Monitor disk space for uploads
3. **Backup**: Include uploads directory in backup strategy
4. **Security**: Validate file uploads and restrict file types
