# Finance Module Documentation

Welcome to the Finance module documentation. This directory contains comprehensive guides for setting up, configuring, and maintaining the Finance application.

## 📚 Documentation Index

### Getting Started

- **[Installation Guide](./INSTALLATION.md)** - Complete setup instructions for the Finance module
- **[Quick Start Guide](./QUICKSTART.md)** - Get up and running quickly
- **[Authentication Guide](./AUTHENTICATION.md)** - How authentication and session transfer works

### Database & ORM

- **[ORM Setup Guide](./ORM_SETUP.md)** - Complete Sequelize ORM guide with examples
- **[Migrations Guide](./MIGRATIONS.md)** - How to create and run database migrations
- **[Session Store Setup](./SESSION_STORE_SETUP.md)** - MySQL session store configuration

### Server Configuration

- **[Server Configuration](./SERVER_CONFIGURATION.md)** - Server setup and configuration details
- **[Session Implementation](./SESSION_IMPLEMENTATION.md)** - Session transfer implementation details

### Deployment & Infrastructure

- **[Apache Reverse Proxy](./APACHE_REVERSE_PROXY.md)** - Complete reverse proxy setup for production
- **[Apache Proxy Config](./APACHE_PROXY_CONFIG.md)** - Alternative proxy configuration
- **[Reverse Proxy Fix](./REVERSE_PROXY_FIX.md)** - Troubleshooting reverse proxy issues

### Frontend & Assets

- **[Frontend Fix](./FRONTEND_FIX.md)** - Frontend-specific fixes and configurations
- **[Assets Setup](./ASSETS_SETUP.md)** - Asset management and configuration
- **[Layouts Summary](./LAYOUTS_SUMMARY.md)** - Layout and component structure

## 🚀 Quick Commands

### Installation
```bash
cd finance
npm run install:all
```

### Development
```bash
# Run both server and frontend
npm run dev:all

# Run server only
npm run dev

# Run frontend only
npm run client
```

### Database Migrations
```bash
# Run migrations
npm run db:migrate

# Check migration status
npm run db:migrate:status

# Rollback last migration
npm run db:migrate:undo
```

### Production Build
```bash
# Build frontend
npm run client:build

# Start server
npm start
```

## 📖 Documentation by Topic

### For Developers

1. **Setting Up Development Environment**
   - Start with [Installation Guide](./INSTALLATION.md)
   - Follow [Quick Start Guide](./QUICKSTART.md)

2. **Working with Database**
   - Read [ORM Setup Guide](./ORM_SETUP.md)
   - Learn [Migrations Guide](./MIGRATIONS.md)

3. **Understanding Authentication**
   - Review [Authentication Guide](./AUTHENTICATION.md)
   - Check [Session Implementation](./SESSION_IMPLEMENTATION.md)

### For DevOps/System Administrators

1. **Production Deployment**
   - Start with [Server Configuration](./SERVER_CONFIGURATION.md)
   - Configure [Apache Reverse Proxy](./APACHE_REVERSE_PROXY.md)

2. **Troubleshooting**
   - Check [Reverse Proxy Fix](./REVERSE_PROXY_FIX.md)
   - Review [Server Configuration](./SERVER_CONFIGURATION.md)

## 🔧 Common Tasks

### Running Migrations

See the [Migrations Guide](./MIGRATIONS.md) for detailed instructions.

**Quick reference**:
```bash
# Run all pending migrations
npm run db:migrate

# Check what migrations have run
npm run db:migrate:status

# Rollback last migration
npm run db:migrate:undo
```

### Setting Up Reverse Proxy

See [Apache Reverse Proxy](./APACHE_REVERSE_PROXY.md) for complete setup.

**Key points**:
- API routes: `/finance/api/*` → `localhost:3003/api/*`
- Frontend: `/finance/*` → `localhost:3002/finance/*`
- Order matters: API location must come before frontend location

### Configuring Session Store

See [Session Store Setup](./SESSION_STORE_SETUP.md) for MySQL session configuration.

**Quick setup**:
1. Install dependencies: `npm install --legacy-peer-deps`
2. Restart server
3. Table `finance_sessions` will be created automatically

## 🐛 Troubleshooting

### Common Issues

1. **Session not persisting**
   - Check [Session Store Setup](./SESSION_STORE_SETUP.md)
   - Verify MySQL connection

2. **Reverse proxy not working**
   - Review [Apache Reverse Proxy](./APACHE_REVERSE_PROXY.md)
   - Check [Reverse Proxy Fix](./REVERSE_PROXY_FIX.md)

3. **Migration errors**
   - See [Migrations Guide](./MIGRATIONS.md) troubleshooting section
   - Verify database credentials

4. **Authentication issues**
   - Review [Authentication Guide](./AUTHENTICATION.md)
   - Check [Session Implementation](./SESSION_IMPLEMENTATION.md)

## 📝 File Structure

```
finance/
├── documentation/          # All documentation (this directory)
├── server/
│   ├── config/            # Configuration files
│   ├── controllers/       # MVC controllers
│   ├── models/            # Sequelize models
│   ├── migrations/        # Database migrations
│   ├── routes/            # API routes
│   └── middleware/        # Express middleware
├── frontend/              # React frontend
└── package.json           # Dependencies and scripts
```

## 🔗 Related Resources

### Finance Module
- Main README: `../README.md`
- Server README: `../server/README.md`
- Database README: `../server/database/README.md`

### Platform Documentation
- **Main Documentation Hub**: [`../../documentation/README.md`](../../documentation/README.md) - Central documentation for the entire CBP platform

### Related Modules
- **APM (Approvals Management) Documentation**: [`../../apm/documentation/`](../../apm/documentation/) - Complete documentation for the Laravel-based Approvals Management System
  - [Approval Trail Management](../../apm/documentation/APPROVAL_TRAIL_MANAGEMENT.md)
  - [Approval Trail Archiving](../../apm/documentation/APPROVAL_TRAIL_ARCHIVING.md)
  - [Document Numbering System](../../apm/documentation/DOCUMENT_NUMBERING_SYSTEM.md)
  - [Queue Setup Guide](../../apm/documentation/QUEUE_SETUP_GUIDE.md)
  - [Systemd Queue Guide](../../apm/documentation/SYSTEMD_QUEUE_GUIDE.md)
  - [Deployment Guide](../../apm/documentation/DEPLOYMENT.md)
  - [Cron Setup](../../apm/documentation/CRON_SETUP.md)
  - [Daily Notifications Setup](../../apm/documentation/DAILY_NOTIFICATIONS_SETUP.md)
  - [Session Expiry Setup](../../apm/documentation/SESSION_EXPIRY_SETUP.md)

- **Staff Portal Documentation**: 
  - [Environment Variables](../../assets/ENVIRONMENT_VARIABLES.md) - Configuration guide
  - [Auth Module Improvements](../../application/modules/auth/README_IMPROVEMENTS.md) - Authentication features
  - [Shared Header Usage](../../application/modules/templates/views/partials/SHARED_HEADER_USAGE.md) - Component documentation

## 📞 Support

For issues or questions:
1. Check the relevant documentation guide
2. Review troubleshooting sections
3. Check server logs for error messages
4. Verify configuration files

---

**Last Updated**: 2024
**Version**: 1.0.0

