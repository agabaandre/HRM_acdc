# Server Structure

This directory contains the Node.js/Express server code organized by separation of concerns.

## Directory Structure

```
server/
├── index.js           # Main server entry point
├── config/            # Configuration files
│   └── index.js       # Application configuration
├── database/          # Database connection and utilities
│   ├── index.js       # Database connection pool and query helpers
│   └── README.md      # Database documentation
├── middleware/        # Express middleware
│   └── index.js       # Middleware setup (CORS, sessions, static files)
└── routes/            # API route handlers
    ├── index.js       # Route aggregator
    ├── auth.js        # Authentication routes (login, logout)
    ├── session.js     # Session management routes
    └── finance.js     # Finance-specific routes
```

## Configuration

Configuration is managed in `config/index.js` and reads from environment variables:

- `PORT` - Server port (default: 3003)
- `NODE_ENV` - Environment (development/production)
- `SESSION_SECRET` - Session secret key
- `CLIENT_URL` - React app URL for CORS
- `ASSETS_BASE_PATH` - Path to Laravel assets directory
- `CI_BASE_URL` - CodeIgniter app base URL
- `DB_HOST` - Database host (default: 127.0.0.1)
- `DB_PORT` - Database port (default: 3306)
- `DB_USERNAME` - Database username (default: root)
- `DB_PASSWORD` - Database password
- `DB_DATABASE` - Database name (default: approvals_management)
- `DB_CHARSET` - Character set (default: utf8mb4)

## Routes

### API Routes

- `GET /api/health` - Health check endpoint
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `POST /api/session/transfer` - Transfer session from CI app
- `GET /api/session` - Get current session
- `GET /api/finance/data` - Protected finance data (requires auth)
- `GET /api/finance/test-db` - Test database connection (requires auth)

## Middleware

The middleware setup includes:

1. **CORS** - Cross-origin resource sharing
2. **Body Parser** - JSON and URL-encoded body parsing
3. **Cookie Parser** - Cookie parsing
4. **Session** - Express session management
5. **Static Files** - Laravel assets and React build files

## Adding New Routes

1. Create a new route file in `routes/` directory
2. Export an Express router
3. Import and mount in `routes/index.js`

Example:

```javascript
// routes/example.js
const express = require('express');
const router = express.Router();

router.get('/test', (req, res) => {
  res.json({ message: 'Test route' });
});

module.exports = router;

// routes/index.js
const exampleRoutes = require('./example');
router.use('/example', exampleRoutes);
```

## Database

The server connects to the `approvals_management` MySQL database using a connection pool.

### Usage in Routes

```javascript
const db = require('../database');

// Simple query
router.get('/users', async (req, res) => {
  try {
    const users = await db.query('SELECT * FROM users WHERE active = ?', [1]);
    res.json({ success: true, data: users });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// Get single row
router.get('/user/:id', async (req, res) => {
  const user = await db.queryOne('SELECT * FROM users WHERE id = ?', [req.params.id]);
  res.json({ success: true, data: user });
});
```

### Transactions

```javascript
const connection = await db.beginTransaction();
try {
  await connection.execute('INSERT INTO table1 ...');
  await connection.execute('UPDATE table2 ...');
  await db.commit(connection);
} catch (error) {
  await db.rollback(connection);
  throw error;
}
```

See `server/database/README.md` for complete database documentation.

## Authentication Middleware

Use the `requireAuth` middleware from `routes/finance.js` as a template for protected routes:

```javascript
const requireAuth = (req, res, next) => {
  if (!req.session || !req.session.authenticated) {
    return res.status(401).json({
      success: false,
      message: 'Unauthorized'
    });
  }
  next();
};
```

