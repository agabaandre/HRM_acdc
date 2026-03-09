# APM (Approvals Management) Documentation

Welcome to the APM (Approvals Management) documentation. This directory contains comprehensive guides for the Laravel-based Approvals Management System.

## 📚 Documentation Index

### User Guides

- **[User Guide](./USER_GUIDE.md)** - Complete guide for creating and managing documents (Matrices, Memos, Requests)
- **[Approvers Guide](./APPROVERS_GUIDE.md)** - Guide for approvers on how to approve, return, or reject documents

### API

- **[APM API Documentation](./API_DOCUMENTATION.md)** - REST API guide: auth, endpoints, pending approvals with approval trails, actions, memo list, and examples.
- **[APM API (OpenAPI/Swagger)](./APM_API_OPENAPI.yaml)** - OpenAPI 3.0 specification (full request/response schemas). **Interactive docs:** open `/docs` in the browser (e.g. `http://localhost/staff/apm/docs`).

### Core Features

- **[Approval Trail Management](./APPROVAL_TRAIL_MANAGEMENT.md)** - Complete guide to approval trail tracking and management
- **[Approval Trail Archiving](./APPROVAL_TRAIL_ARCHIVING.md)** - How to archive old approval trails and manage storage
- **[Document Numbering System](./DOCUMENT_NUMBERING_SYSTEM.md)** - Document number generation and management
- **[Document Number Management](./DOCUMENT_NUMBER_MANAGEMENT.md)** - Advanced document numbering features
- **[Signature Verification](./SIGNATURE_VERIFICATION.md)** - Validate APM document signature hashes (lookup, verify, upload PDF)
- **[Change Tracking Feasibility](./CHANGE_TRACKING_FEASIBILITY.md)** - Change tracking implementation guide

### Infrastructure & Deployment

- **[Deployment Guide](./DEPLOYMENT.md)** - Production deployment instructions
- **[Queue Setup Guide](./QUEUE_SETUP_GUIDE.md)** - Laravel queue configuration and setup
- **[Queue Troubleshooting](./QUEUE_TROUBLESHOOTING.md)** - Common queue issues and solutions
- **[Cron Setup](./CRON_SETUP.md)** - Cron job configuration for scheduled tasks
- **[Supervisor Setup Demo](./SUPERVISOR_SETUP_DEMO.md)** - Supervisor configuration for queue workers
- **[Database Backup System](../README_BACKUP.md)** - Automatic database backups with retention policies and OneDrive integration

### Notifications & Automation

- **[Daily Notifications Setup](./DAILY_NOTIFICATIONS_SETUP.md)** - Daily notification system configuration
- **[Session Expiry Setup](./SESSION_EXPIRY_SETUP.md)** - Session expiration and management

### System Improvements

- **[Sync Improvements](./SYNC_IMPROVEMENTS.md)** - Data synchronization enhancements
- **[System Updates](./SYSTEM_UPDATES.md)** - Changelog of new features and updates (signature verification, etc.)

## 🚀 Quick Commands

### Queue Management

```bash
# Start queue worker
php artisan queue:work

# Process specific queue
php artisan queue:work --queue=high,default

# Restart queue workers
php artisan queue:restart
```

### Cron Jobs

See [Cron Setup Guide](./CRON_SETUP.md) for complete cron configuration.

**Key cron entries**:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Artisan Commands

```bash
# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## 📖 Documentation by Topic

### For Developers

1. **APM API (approver apps / integrations)**
   - **[API Documentation](./API_DOCUMENTATION.md)** - Auth, endpoints, approval trails, actions, and curl examples
   - OpenAPI spec: [APM_API_OPENAPI.yaml](./APM_API_OPENAPI.yaml)
   - Interactive Swagger UI: visit `/docs` when the app is running (e.g. `http://localhost/staff/apm/docs`)
   - See [System Updates](./SYSTEM_UPDATES.md) for API summary and changelog

2. **Understanding Approval System**
   - Start with [Approval Trail Management](./APPROVAL_TRAIL_MANAGEMENT.md)
   - Review [Document Numbering System](./DOCUMENT_NUMBERING_SYSTEM.md)
   - Check [Change Tracking Feasibility](./CHANGE_TRACKING_FEASIBILITY.md)

3. **Working with Queues**
   - Read [Queue Setup Guide](./QUEUE_SETUP_GUIDE.md)
   - Troubleshoot with [Queue Troubleshooting](./QUEUE_TROUBLESHOOTING.md)
   - Configure [Supervisor Setup](./SUPERVISOR_SETUP_DEMO.md)

4. **System Maintenance**
   - Review [Approval Trail Archiving](./APPROVAL_TRAIL_ARCHIVING.md)
   - Check [Sync Improvements](./SYNC_IMPROVEMENTS.md)
   - See [System Updates](./SYSTEM_UPDATES.md) for recent features (including APM API and users sync)

### For DevOps/System Administrators

1. **Production Deployment**
   - Start with [Deployment Guide](./DEPLOYMENT.md)
   - Configure [Cron Setup](./CRON_SETUP.md)
   - Set up [Queue Workers](./QUEUE_SETUP_GUIDE.md)

2. **Monitoring & Maintenance**
   - Review [Approval Trail Archiving](./APPROVAL_TRAIL_ARCHIVING.md)
   - Check [Queue Troubleshooting](./QUEUE_TROUBLESHOOTING.md)
   - Set up [Database Backup System](../README_BACKUP.md) - Configure automatic backups and retention policies

3. **Automation**
   - Configure [Daily Notifications](./DAILY_NOTIFICATIONS_SETUP.md)
   - Set up [Session Expiry](./SESSION_EXPIRY_SETUP.md)
   - Configure [Database Backups](../README_BACKUP.md) - Automatic backup scheduling and cleanup

## 🔧 Common Tasks

### Setting Up Queue Workers

See the [Queue Setup Guide](./QUEUE_SETUP_GUIDE.md) for detailed instructions.

**Quick setup**:
1. Install Supervisor: `sudo apt-get install supervisor`
2. Configure queue worker in Supervisor
3. Start Supervisor service
4. Monitor queue processing

### Configuring Cron Jobs

See [Cron Setup Guide](./CRON_SETUP.md) for complete configuration.

**Key points**:
- Add Laravel scheduler to crontab
- Ensure proper permissions
- Monitor cron logs

### Archiving Approval Trails

See [Approval Trail Archiving](./APPROVAL_TRAIL_ARCHIVING.md) for complete guide.

**Quick reference**:
- Archive old approval trails to reduce database size
- Maintain data integrity during archiving
- Restore archived data if needed

## 🐛 Troubleshooting

### Common Issues

1. **Queue not processing**
   - Check [Queue Troubleshooting](./QUEUE_TROUBLESHOOTING.md)
   - Verify Supervisor is running
   - Check queue connection configuration

2. **Cron jobs not running**
   - Verify crontab entry
   - Check file permissions
   - Review cron logs

3. **Document numbers not generating**
   - Review [Document Numbering System](./DOCUMENT_NUMBERING_SYSTEM.md)
   - Check database configuration
   - Verify sequence tables

4. **Notifications not sending**
   - Check [Daily Notifications Setup](./DAILY_NOTIFICATIONS_SETUP.md)
   - Verify queue is processing
   - Review notification logs

## 📝 File Structure

```
apm/
├── documentation/          # All documentation (this directory)
├── app/
│   ├── Http/
│   │   └── Controllers/   # Laravel controllers
│   ├── Models/            # Eloquent models
│   ├── Jobs/              # Queue jobs
│   └── Console/
│       └── Commands/      # Artisan commands
├── database/
│   ├── migrations/        # Database migrations
│   └── seeders/          # Database seeders
├── resources/
│   └── views/            # Blade templates
└── routes/               # Route definitions
```

## 🔗 Related Resources

### APM Module
- Main README: `../README.md`
- Laravel Documentation: [https://laravel.com/docs](https://laravel.com/docs)

### Platform Documentation
- **Main Documentation Hub**: [`../../documentation/README.md`](../../documentation/README.md) - Central documentation for the entire CBP platform

### Related Modules
- **Finance Module Documentation**: [`../../finance/documentation/`](../../finance/documentation/) - Node.js/React Finance Management System documentation
  - [Frontend Architecture](../../finance/documentation/FRONTEND_ARCHITECTURE.md)
  - [Server Architecture](../../finance/documentation/SERVER_ARCHITECTURE.md)
  - [Migrations Guide](../../finance/documentation/MIGRATIONS.md)
  - [Session Implementation](../../finance/documentation/SESSION_IMPLEMENTATION.md)
  - [Installation Guide](../../finance/documentation/INSTALLATION.md)

- **Staff Portal Documentation**: 
  - [Environment Variables](../../assets/ENVIRONMENT_VARIABLES.md) - Configuration guide
  - [Auth Module Improvements](../../application/modules/auth/README_IMPROVEMENTS.md) - Authentication features
  - [Shared Header Usage](../../application/modules/templates/views/partials/SHARED_HEADER_USAGE.md) - Component documentation

## 📞 Support

For issues or questions:
1. Check the relevant documentation guide
2. Review troubleshooting sections
3. Check Laravel logs: `storage/logs/laravel.log`
4. Verify configuration files
5. Review queue logs if using queues

---

**Last Updated**: 2025
**Version**: 1.1.0

