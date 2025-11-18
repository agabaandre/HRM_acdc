# Database Module

This module provides database connectivity to the `approvals_management` MySQL database.

## Configuration

Database configuration is managed in `server/config/index.js` and reads from environment variables:

- `DB_HOST` - Database host (default: 127.0.0.1)
- `DB_PORT` - Database port (default: 3306)
- `DB_USERNAME` - Database username (default: root)
- `DB_PASSWORD` - Database password
- `DB_DATABASE` - Database name (default: approvals_management)
- `DB_CHARSET` - Character set (default: utf8mb4)

## Usage

### Basic Query

```javascript
const db = require('./database');

// Execute a query
const results = await db.query('SELECT * FROM users WHERE id = ?', [userId]);

// Get first row
const user = await db.queryOne('SELECT * FROM users WHERE id = ?', [userId]);
```

### Transactions

```javascript
const connection = await db.beginTransaction();
try {
  await connection.execute('INSERT INTO table1 ...');
  await connection.execute('INSERT INTO table2 ...');
  await db.commit(connection);
} catch (error) {
  await db.rollback(connection);
  throw error;
}
```

### Connection Pool

The module uses a connection pool for efficient database access:

- **Connection Limit**: 10 concurrent connections
- **Keep Alive**: Enabled for persistent connections
- **Queue Limit**: 0 (unlimited queued requests)

## API

### `query(sql, params)`
Execute a SQL query with parameters. Returns all rows.

### `queryOne(sql, params)`
Execute a SQL query and return the first row only.

### `beginTransaction()`
Start a database transaction. Returns a connection object.

### `commit(connection)`
Commit a transaction and release the connection.

### `rollback(connection)`
Rollback a transaction and release the connection.

### `testConnection()`
Test the database connection. Returns `true` if successful.

### `close()`
Close all database connections (for graceful shutdown).

## Error Handling

All database operations throw errors that should be caught:

```javascript
try {
  const results = await db.query('SELECT * FROM table');
} catch (error) {
  console.error('Database error:', error);
  // Handle error
}
```

## Security

- Always use parameterized queries (never concatenate user input)
- The connection pool handles SQL injection prevention
- Passwords and sensitive data are read from environment variables

## Example Routes

See `server/routes/finance.js` for examples of using the database in routes.

