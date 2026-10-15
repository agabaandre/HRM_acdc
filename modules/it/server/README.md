# Finance Server - MVC Architecture

This directory contains the Node.js/Express server code organized using MVC (Model-View-Controller) architecture with comprehensive memory management strategies.

## Directory Structure

```
server/
├── index.js              # Main server entry point
├── config/               # Configuration files
│   └── index.js          # Application configuration
├── controllers/          # Controllers (business logic)
│   ├── FinanceController.js
│   └── SettingsController.js
├── models/               # Models (data access layer)
│   ├── BaseModel.js      # Base model with common operations
│   └── FinanceModel.js   # Finance-specific model
├── middleware/           # Express middleware
│   ├── index.js          # Middleware setup
│   ├── permissions.js    # Permission checking middleware
│   └── memory.js         # Memory management middleware
├── routes/               # API route definitions
│   ├── index.js          # Route aggregator
│   ├── auth.js           # Authentication routes
│   ├── session.js        # Session management routes
│   └── finance.js        # Finance routes
├── database/             # Database connection and utilities
│   └── index.js          # Database connection pool
├── utils/                # Utility functions
│   └── errorHandler.js   # Error handling utilities
└── README.md             # This file
```

## MVC Architecture

### Models (`models/`)
Models handle all database operations and data access logic.

- **BaseModel**: Provides common CRUD operations and query methods
- **FinanceModel**: Extends BaseModel with finance-specific queries

**Example:**
```javascript
const FinanceModel = require('../models/FinanceModel');
const model = new FinanceModel();
const advances = await model.getUserAdvances(userId, { limit: 50, offset: 0 });
```

### Controllers (`controllers/`)
Controllers handle HTTP requests, call models, and return responses.

- **FinanceController**: Handles finance-related requests
- **SettingsController**: Handles settings requests (requires permission 93)

**Example:**
```javascript
const FinanceController = require('../controllers/FinanceController');
const controller = new FinanceController();
router.get('/advances', requireFinanceAccess, (req, res) => 
  controller.getAdvances(req, res)
);
```

### Routes (`routes/`)
Routes define API endpoints and connect them to controllers.

Routes are thin - they only define the endpoint, apply middleware, and call controller methods.

## Memory Management Strategies

### 1. Connection Pooling
- Database connections are pooled with limits (max 10 connections)
- Idle connections are closed after 60 seconds
- Query timeout of 30 seconds prevents hanging queries

### 2. Request Timeouts
- All requests have a 30-second timeout
- Prevents hanging requests from consuming memory

### 3. Size Limits
- Request body limit: 1MB
- Response size limit: 10MB
- Parameter limit: 100 parameters per request

### 4. Memory Monitoring
- Automatic memory usage monitoring
- Logs warnings when heap usage exceeds 80%
- Optional garbage collection triggering (requires `--expose-gc` flag)

### 5. Pagination
- All list endpoints support pagination
- Default limit: 50-100 records per page
- Maximum limit enforced to prevent large result sets

### 6. Resource Cleanup
- Automatic cleanup of request-specific resources
- Graceful shutdown closes all connections
- Uncaught exception handlers ensure cleanup

### 7. Session Management
- Sessions don't reset expiration on every request (`rolling: false`)
- Sessions are destroyed when unset
- 24-hour session expiration

## API Endpoints

### Health & Monitoring
- `GET /api/health` - Server health check
- `GET /api/health/memory` - Memory usage statistics

### Finance Routes (Requires Permission 92)
- `GET /api/finance/data` - Get finance data
- `GET /api/finance/advances` - Get user advances (paginated)
- `GET /api/finance/missions` - Get user missions (paginated)
- `GET /api/finance/budgets` - Get budgets (paginated)
- `GET /api/finance/stats` - Get finance statistics
- `GET /api/finance/permissions` - Get user permissions
- `GET /api/finance/test-db` - Test database connection

### Settings Routes (Requires Permission 93)
- `GET /api/finance/settings` - Get finance settings

## Error Handling

All errors are handled consistently using the `errorHandler` utility:

```javascript
const { AppError, asyncHandler } = require('../utils/errorHandler');

// In controller
async getData(req, res) {
  try {
    // Your code
  } catch (error) {
    throw new AppError('Failed to fetch data', 500, 'FETCH_ERROR');
  }
}

// In route
router.get('/data', asyncHandler((req, res) => controller.getData(req, res)));
```

## Memory Management Best Practices

1. **Always use pagination** for list endpoints
2. **Set reasonable limits** on query results
3. **Use transactions** for multiple related operations
4. **Close connections** properly in error cases
5. **Monitor memory usage** regularly via `/api/health/memory`
6. **Use async/await** with proper error handling
7. **Avoid storing large objects** in memory unnecessarily

## Running with Memory Management

For production with garbage collection monitoring:

```bash
node --expose-gc server/index.js
```

Or with PM2:

```bash
pm2 start server/index.js --node-args="--expose-gc" --name finance-server
```

## Monitoring

Check memory health:
```bash
curl http://localhost:3003/api/health/memory
```

Response:
```json
{
  "status": "ok",
  "memory": {
    "status": "healthy",
    "heapUsed": "45.2 MB",
    "heapTotal": "128 MB",
    "heapUsedPercent": "35.31%",
    "rss": "156.8 MB",
    "external": "2.1 MB",
    "uptime": 3600
  }
}
```

## Configuration

Memory management settings can be adjusted in:
- `middleware/memory.js` - Memory monitoring thresholds
- `database/index.js` - Connection pool settings
- `middleware/index.js` - Request/response size limits
