# MySQL Session Store Setup

## Overview

The Finance app now uses MySQL for session storage instead of MemoryStore. This provides:
- ✅ Persistent sessions (survive server restarts)
- ✅ No memory leaks
- ✅ Scalable across multiple processes/servers
- ✅ Automatic cleanup of expired sessions

## Installation

1. **Install the package**:
   ```bash
   cd finance
   npm install express-mysql-session --legacy-peer-deps
   ```

2. **The sessions table will be created automatically** when the server starts.

   If you want to create it manually, run this SQL:
   ```sql
   CREATE TABLE IF NOT EXISTS `finance_sessions` (
     `session_id` varchar(128) COLLATE utf8mb4_bin NOT NULL,
     `expires` int(11) unsigned NOT NULL,
     `data` mediumtext COLLATE utf8mb4_bin,
     PRIMARY KEY (`session_id`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
   ```

## Configuration

The session store is configured in `server/middleware/sessionStore.js`:

- **Table name**: `finance_sessions` (to avoid conflicts with other apps)
- **Expiration**: 24 hours (86400000 milliseconds)
- **Cleanup interval**: Every 15 minutes (900000 milliseconds)
- **Auto-create table**: Enabled

## Verification

After installing and restarting the server:

1. **Check the table was created**:
   ```sql
   USE approvals_management;
   SHOW TABLES LIKE 'finance_sessions';
   DESCRIBE finance_sessions;
   ```

2. **Check server logs** for:
   ```
   MySQL session store connected successfully
   ```

3. **Test a session**:
   - Visit the finance app
   - Check the `finance_sessions` table for a new session entry
   - Verify the session persists after server restart

## Troubleshooting

### Table not created automatically
- Check database permissions
- Verify database connection in `server/config/index.js`
- Check server logs for errors

### Sessions not persisting
- Verify the table exists: `SELECT * FROM finance_sessions;`
- Check server logs for session store errors
- Verify database connection is working

### Memory warnings still appearing
- Make sure `express-mysql-session` is installed
- Verify `sessionStore` is imported and used in `server/middleware/index.js`
- Restart the server after changes

## Migration from MemoryStore

If you were using MemoryStore before:
1. All existing sessions will be lost (this is expected)
2. Users will need to log in again
3. New sessions will be stored in MySQL

## Maintenance

The session store automatically:
- Clears expired sessions every 15 minutes
- Removes sessions older than 24 hours
- Handles connection errors gracefully

You can manually clean up old sessions:
```sql
DELETE FROM finance_sessions WHERE expires < UNIX_TIMESTAMP() * 1000;
```

