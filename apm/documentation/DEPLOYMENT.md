# Deployment Guide

## Server requirements

### PDF attachment embedding (memo / activity print)

Production servers that generate APM PDFs (activities, single memos, special memos, non-travel memos, change requests, etc.) should have tools to embed **scanned or image-only PDF** attachments in the printout appendix. mPDF alone cannot import many scanned PDFs.

| Package | Binary | Role |
|---------|--------|------|
| Ghostscript | `gs` | Primary: republish PDFs; rasterize pages to PNG for embedding |
| Poppler | `pdftoppm` | Fallback rasterization |
| LibreOffice | `libreoffice`, `soffice` | Convert Word (`.doc`, `.docx`) attachments to PDF for the annex |
| PHP Imagick (optional) | — | Optional rasterization via ImageMagick |

**Debian / Ubuntu:**

```bash
sudo apt update
sudo apt install ghostscript poppler-utils libreoffice-writer
```

**RHEL / AlmaLinux / Rocky:**

```bash
sudo dnf install ghostscript poppler-utils libreoffice-writer
```

Verify after install (as the same user PHP runs as, e.g. `www-data`):

```bash
which gs pdftoppm libreoffice
gs --version
libreoffice --version
```

If these commands are missing from PHP’s `PATH`, scanned PDFs may fail to embed, and Word attachments will not render in the annex (they still appear in the attachment index).

Implementation: `App\Helpers\PrintHelper::appendAttachmentsAppendixToMpdf()`.

---

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
