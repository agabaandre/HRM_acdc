# Central Business Platform (CBP) Documentation

Welcome to the Africa CDC Central Business Platform (CBP) documentation. This is the main documentation hub for the entire staff management system, including the CodeIgniter-based Staff Portal, Laravel-based APM (Approvals Management), and Node.js-based Finance module.

## 📚 Platform Overview

The Africa CDC Central Business Platform is a comprehensive staff management system consisting of three main modules:

1. **Staff Portal** (CodeIgniter) - Core staff management, authentication, and profile management
2. **APM** (Laravel) - Approvals Management System for workflows, matrices, and document processing
3. **Finance** (Node.js/React) - Finance Management System for advances, missions, and budgets

## 🏗️ System Architecture

```
staff/
├── application/          # CodeIgniter Staff Portal (Core)
├── apm/                 # Laravel APM (Approvals Management)
├── finance/             # Node.js/React Finance Module
└── documentation/       # This directory - Main documentation hub
```

## 📖 Module Documentation

### 1. Staff Portal (CodeIgniter)

The core CodeIgniter application that handles:
- User authentication and session management
- Staff profiles and directory
- Permission management
- Base infrastructure for other modules

**Documentation:**
- [Environment Variables Configuration](../assets/ENVIRONMENT_VARIABLES.md) - Environment variable setup
- [Auth Module Improvements](../application/modules/auth/README_IMPROVEMENTS.md) - Authentication improvements
- [Shared Header Usage](../application/modules/templates/views/partials/SHARED_HEADER_USAGE.md) - Header component documentation

### 2. APM (Approvals Management)

Laravel-based system for managing approvals, workflows, and document processing.

> 📚 **Complete APM Documentation**: See [apm/documentation/README.md](../apm/documentation/README.md)

**Quick Links:**
- [Approval Trail Management](../apm/documentation/APPROVAL_TRAIL_MANAGEMENT.md)
- [Document Numbering System](../apm/documentation/DOCUMENT_NUMBERING_SYSTEM.md)
- [Queue Setup Guide](../apm/documentation/QUEUE_SETUP_GUIDE.md)
- [Systemd Queue Guide](../apm/documentation/SYSTEMD_QUEUE_GUIDE.md)
- [Deployment Guide](../apm/documentation/DEPLOYMENT.md)
- [Cron Setup](../apm/documentation/CRON_SETUP.md)
- [Database Backup System](../apm/README_BACKUP.md) - Automatic database backups with retention policies

### 3. Finance Module

Node.js/Express backend with React frontend for finance management.

> 📚 **Complete Finance Documentation**: See [finance/documentation/README.md](../finance/documentation/README.md)

**Quick Links:**
- [Frontend Architecture](../finance/documentation/FRONTEND_ARCHITECTURE.md)
- [Server Architecture](../finance/documentation/SERVER_ARCHITECTURE.md)
- [Installation Guide](../finance/documentation/INSTALLATION.md)
- [Migrations Guide](../finance/documentation/MIGRATIONS.md)
- [Session Implementation](../finance/documentation/SESSION_IMPLEMENTATION.md)

## 🚀 Getting Started

### For New Developers

1. **Start with the Staff Portal**
   - Review [Environment Variables](../assets/ENVIRONMENT_VARIABLES.md)
   - Understand authentication flow
   - Set up CodeIgniter environment

2. **Explore APM Module**
   - Read [APM Documentation](../apm/documentation/README.md)
   - Set up Laravel environment
   - Configure queues and cron jobs

3. **Set up Finance Module**
   - Read [Finance Documentation](../finance/documentation/README.md)
   - Install Node.js dependencies
   - Configure session transfer

### For System Administrators

1. **Production Deployment**
   - Review [APM Deployment Guide](../apm/documentation/DEPLOYMENT.md)
   - Configure reverse proxy (see Finance docs)
   - Set up queue workers and cron jobs

2. **Infrastructure Setup**
   - [Queue Setup](../apm/documentation/QUEUE_SETUP_GUIDE.md)
   - [Systemd Queue Guide](../apm/documentation/SYSTEMD_QUEUE_GUIDE.md)
   - [Cron Configuration](../apm/documentation/CRON_SETUP.md)

## 🔗 Integration Points

### Session Management

All modules share session management through the CodeIgniter Staff Portal:

- **Staff Portal**: Primary authentication and session storage
- **APM**: Receives session via token parameter
- **Finance**: Receives session via token parameter

See:
- [Finance Session Implementation](../finance/documentation/SESSION_IMPLEMENTATION.md)
- [APM Session Expiry Setup](../apm/documentation/SESSION_EXPIRY_SETUP.md)

### Navigation Integration

Modules are integrated through navigation links:

- Staff Portal → APM (with token)
- Staff Portal → Finance (with token)
- APM → Staff Portal (direct link)
- Finance → Staff Portal (direct link)
- Finance → APM (with token)

### Permission System

All modules use the same permission system from the Staff Portal:

- Permission 92: Finance access
- Permission 93: Finance settings
- Various permissions for APM workflows

## 📝 Common Tasks

### Setting Up Development Environment

1. **CodeIgniter Setup**
   ```bash
   # Configure database in application/config/database.php
   # Set up .env or environment variables
   ```

2. **APM Setup**
   ```bash
   cd apm
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   ```

3. **Finance Setup**
   ```bash
   cd finance
   npm run install:all
   # Configure .env files
   npm run dev:all
   ```

### Production Deployment

1. **Configure Reverse Proxy**
   - APM: Standard Laravel deployment
   - Finance: See [Apache Reverse Proxy](../finance/documentation/APACHE_REVERSE_PROXY.md)

2. **Set Up Queue Workers**
   - See [APM Queue Setup](../apm/documentation/QUEUE_SETUP_GUIDE.md)
   - Or [Systemd Queue Guide](../apm/documentation/SYSTEMD_QUEUE_GUIDE.md)

3. **Configure Cron Jobs**
   - See [APM Cron Setup](../apm/documentation/CRON_SETUP.md)

## 🐛 Troubleshooting

### Common Issues

1. **Session not transferring between modules**
   - Check token generation in Staff Portal
   - Verify session configuration in APM/Finance
   - Review [Finance Session Implementation](../finance/documentation/SESSION_IMPLEMENTATION.md)

2. **Queue workers not processing**
   - See [APM Queue Troubleshooting](../apm/documentation/QUEUE_TROUBLESHOOTING.md)
   - Check [Systemd Queue Guide](../apm/documentation/SYSTEMD_QUEUE_GUIDE.md)

3. **Permission issues**
   - Verify user permissions in Staff Portal
   - Check permission checks in module code
   - Review permission middleware

## 📂 Documentation Structure

```
staff/
├── README.md                           # Main project README
├── documentation/                      # Main documentation hub (this directory)
│   └── README.md                      # This file
├── assets/
│   └── ENVIRONMENT_VARIABLES.md       # Environment variables guide
├── application/
│   └── modules/
│       ├── auth/
│       │   └── README_IMPROVEMENTS.md # Auth improvements
│       └── templates/
│           └── views/partials/
│               └── SHARED_HEADER_USAGE.md
├── apm/
│   ├── README.md                      # APM main README
│   ├── documentation/                 # APM documentation
│   │   ├── README.md
│   │   ├── APPROVAL_TRAIL_MANAGEMENT.md
│   │   ├── QUEUE_SETUP_GUIDE.md
│   │   └── ...
│   └── IMPROVED_SYNC_GUIDE.md
└── finance/
    ├── README.md                      # Finance main README
    └── documentation/                 # Finance documentation
        ├── README.md
        ├── FRONTEND_ARCHITECTURE.md
        ├── SERVER_ARCHITECTURE.md
        └── ...
```

## 🔗 Quick Reference

### Module READMEs
- [Staff Portal](../README.md) - Main project README
- [APM Module](../apm/README.md) - APM main README
- [Finance Module](../finance/README.md) - Finance main README

### Documentation Indexes
- [APM Documentation Index](../apm/documentation/README.md)
- [Finance Documentation Index](../finance/documentation/README.md)

### Key Configuration Files
- Environment Variables: [assets/ENVIRONMENT_VARIABLES.md](../assets/ENVIRONMENT_VARIABLES.md)
- Apache Config: [000-default.conf](../000-default.conf)

## 📞 Support

For issues or questions:

1. Check the relevant module documentation
2. Review troubleshooting sections
3. Check application logs:
   - CodeIgniter: `application/logs/`
   - Laravel: `apm/storage/logs/`
   - Node.js: Check console output

---

**Last Updated**: 2024
**Version**: 1.0.0

