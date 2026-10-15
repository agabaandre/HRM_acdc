# Database Migrations Guide

## Overview

This guide explains how to use Sequelize migrations to manage database schema changes in the Finance app.

## Prerequisites

1. **Install dependencies**:
   ```bash
   cd finance
   npm install --legacy-peer-deps
   ```

2. **Verify Sequelize CLI is installed**:
   ```bash
   npx sequelize-cli --version
   ```

3. **Check database configuration**:
   - Ensure `.env` file has correct database credentials
   - Verify `server/config/database.js` is configured correctly

## Running Migrations

### Run All Pending Migrations

```bash
npm run db:migrate
```

This will:
- Execute all migrations that haven't been run yet
- Create a `SequelizeMeta` table to track which migrations have been executed
- Run migrations in chronological order

**Example Output**:
```
Sequelize CLI [Node: 18.x.x]

Loaded configuration file "server/config/database.js".
Using environment "development".
== 20240101000000-create-finance-sessions: migrating =======
== 20240101000000-create-finance-sessions: migrated (0.123s)
```

### Check Migration Status

```bash
npm run db:migrate:status
```

This shows which migrations have been executed and which are pending.

**Example Output**:
```
up   20240101000000-create-finance-sessions.js
down 20240102000000-create-finance-table.js
```

### Rollback Last Migration

```bash
npm run db:migrate:undo
```

This rolls back the most recently executed migration.

### Rollback All Migrations

```bash
npm run db:migrate:undo:all
```

⚠️ **Warning**: This will rollback ALL migrations. Use with caution!

## Creating Migrations

### Generate a New Migration

```bash
npm run migration:generate -- --name create-finance-table
```

This creates a new migration file in `server/migrations/` with a timestamp prefix.

**Example**: `20240102120000-create-finance-table.js`

### Migration File Structure

```javascript
'use strict';

/** @type {import('sequelize-cli').Migration} */
module.exports = {
  async up(queryInterface, Sequelize) {
    // Migration code here - what to do when running migration
    await queryInterface.createTable('finances', {
      id: {
        allowNull: false,
        autoIncrement: true,
        primaryKey: true,
        type: Sequelize.INTEGER
      },
      amount: {
        type: Sequelize.DECIMAL(10, 2),
        allowNull: false
      },
      description: {
        type: Sequelize.TEXT,
        allowNull: true
      },
      created_at: {
        allowNull: false,
        type: Sequelize.DATE,
        defaultValue: Sequelize.literal('CURRENT_TIMESTAMP')
      },
      updated_at: {
        allowNull: false,
        type: Sequelize.DATE,
        defaultValue: Sequelize.literal('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')
      }
    });
  },

  async down(queryInterface, Sequelize) {
    // Rollback code here - what to do when undoing migration
    await queryInterface.dropTable('finances');
  }
};
```

## Common Migration Operations

### Create Table

```javascript
await queryInterface.createTable('table_name', {
  id: {
    type: Sequelize.INTEGER,
    primaryKey: true,
    autoIncrement: true
  },
  name: {
    type: Sequelize.STRING,
    allowNull: false
  }
});
```

### Add Column

```javascript
await queryInterface.addColumn('table_name', 'new_column', {
  type: Sequelize.STRING,
  allowNull: true
});
```

### Remove Column

```javascript
await queryInterface.removeColumn('table_name', 'column_name');
```

### Add Index

```javascript
await queryInterface.addIndex('table_name', ['column_name'], {
  name: 'idx_table_column'
});
```

### Remove Index

```javascript
await queryInterface.removeIndex('table_name', 'idx_table_column');
```

### Rename Column

```javascript
await queryInterface.renameColumn('table_name', 'old_name', 'new_name');
```

### Change Column Type

```javascript
await queryInterface.changeColumn('table_name', 'column_name', {
  type: Sequelize.TEXT,
  allowNull: false
});
```

## Best Practices

1. **Always implement both `up` and `down`**:
   - `up`: What to do when running the migration
   - `down`: How to reverse the migration

2. **Never modify existing migrations**:
   - If you need to change a migration, create a new one
   - Modifying existing migrations can cause issues in production

3. **Use descriptive migration names**:
   - ✅ Good: `create-finance-table`, `add-status-to-finances`
   - ❌ Bad: `migration1`, `update`, `fix`

4. **Test migrations**:
   - Test `up` and `down` in development
   - Verify data integrity after migrations

5. **Keep migrations small and focused**:
   - One migration = one logical change
   - Easier to debug and rollback

6. **Use transactions when possible**:
   ```javascript
   await queryInterface.sequelize.transaction(async (transaction) => {
     await queryInterface.createTable('table', {...}, { transaction });
   });
   ```

## Environment-Specific Migrations

Migrations run based on `NODE_ENV`:

- **Development**: Uses `development` config from `server/config/database.js`
- **Production**: Uses `production` config

To run migrations in a specific environment:

```bash
NODE_ENV=production npm run db:migrate
```

## Troubleshooting

### Migration Fails

1. **Check database connection**:
   ```bash
   # Test connection
   node -e "require('./server/models').testConnection()"
   ```

2. **Check migration syntax**:
   - Verify JavaScript syntax is correct
   - Check Sequelize method names

3. **Check if table/column already exists**:
   ```javascript
   const tableExists = await queryInterface.showAllTables().then(tables => 
     tables.includes('table_name')
   );
   ```

### Migration Stuck

If a migration fails partway through:

1. **Check migration status**:
   ```bash
   npm run db:migrate:status
   ```

2. **Manually fix the database** if needed

3. **Mark migration as complete** (if already applied):
   ```sql
   INSERT INTO SequelizeMeta (name) VALUES ('20240101000000-create-finance-sessions.js');
   ```

### Rollback Issues

If rollback fails:

1. **Check the `down` method** is implemented correctly
2. **Manually fix the database** if needed
3. **Remove from SequelizeMeta**:
   ```sql
   DELETE FROM SequelizeMeta WHERE name = 'migration-name.js';
   ```

## Example: Complete Migration Workflow

```bash
# 1. Create a new migration
npm run migration:generate -- --name create-finances-table

# 2. Edit the migration file in server/migrations/
# Add your table creation code

# 3. Run the migration
npm run db:migrate

# 4. Verify it worked
npm run db:migrate:status

# 5. If something went wrong, rollback
npm run db:migrate:undo

# 6. Fix the migration and run again
npm run db:migrate
```

## Related Documentation

- [ORM Setup Guide](./ORM_SETUP.md) - Complete Sequelize ORM guide
- [Installation Guide](./INSTALLATION.md) - Initial setup instructions
- [Server Configuration](./SERVER_CONFIGURATION.md) - Server setup details

