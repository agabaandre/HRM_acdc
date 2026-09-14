# Server Architecture

This document describes the modernized server architecture of the Finance app, following Node.js best practices and modern patterns.

## Directory Structure

```
server/
├── app.js                 # Express app factory
├── index.js               # Server entry point
├── config/                # Configuration
│   ├── index.js          # Main config
│   └── database.js       # Sequelize config
├── controllers/          # Request handlers (thin layer)
│   ├── FinanceController.js
│   └── SettingsController.js
├── services/             # Business logic layer
│   ├── sessionService.js
│   └── financeService.js
├── models/               # Data models
│   ├── BaseModel.js
│   ├── FinanceModel.js
│   └── index.js
├── middleware/           # Express middleware
│   ├── index.js         # Middleware setup
│   ├── permissions.js   # Permission checks
│   ├── security.js      # Security headers & rate limiting
│   ├── validation.js    # Request validation
│   ├── memory.js        # Memory management
│   └── sessionStore.js  # Session store config
├── routes/              # Route definitions
│   ├── index.js        # Route aggregator
│   ├── auth.js         # Authentication routes
│   ├── session.js      # Session routes
│   └── finance.js      # Finance routes
├── utils/               # Utility functions
│   ├── errorHandler.js # Error handling
│   └── logger.js       # Logging utility
└── database/            # Database connection
    └── index.js
```

## Architecture Overview

### 1. App Factory Pattern (`app.js`)

The Express app is created using a factory function pattern, which:
- Allows for better testing (can create multiple app instances)
- Separates app creation from server startup
- Makes the app reusable in different contexts

```javascript
const createApp = require('./app');
const app = createApp();
```

### 2. Services Layer (`services/`)

Services contain business logic and interact with models. Controllers are thin and delegate to services.

**Benefits:**
- Separation of concerns
- Reusable business logic
- Easier to test
- Can be used by multiple controllers

**Example:**
```javascript
// services/financeService.js
class FinanceService {
  async getUserAdvances(userId, options) {
    // Business logic here
    return await this.model.getUserAdvances(userId, options);
  }
}
```

### 3. Controllers (`controllers/`)

Controllers are thin layers that:
- Handle HTTP requests/responses
- Validate input (via middleware)
- Call services
- Format responses

**Pattern:**
```javascript
class FinanceController {
  getAdvances = asyncHandler(async (req, res) => {
    const userId = req.session.user?.staff_id;
    const result = await financeService.getUserAdvances(userId, req.query);
    res.json({ success: true, ...result });
  });
}
```

### 4. Middleware (`middleware/`)

#### Security Middleware (`security.js`)
- Security headers (X-Content-Type-Options, X-Frame-Options, etc.)
- Rate limiting for API routes
- Strict rate limiting for auth routes

#### Validation Middleware (`validation.js`)
- Request body validation
- Query parameter validation
- Can be extended with Joi or Yup

#### Permissions Middleware (`permissions.js`)
- `requireAuth` - Requires authentication
- `requireFinanceAccess` - Requires permission 92
- `requireFinanceSettings` - Requires permission 93

### 5. Error Handling (`utils/errorHandler.js`)

#### AppError Class
Custom error class with status codes and error codes:
```javascript
throw new AppError('User not found', 404, 'USER_NOT_FOUND');
```

#### Async Handler
Wraps async route handlers to catch errors automatically:
```javascript
router.get('/route', asyncHandler(async (req, res) => {
  // No need for try/catch - errors are caught automatically
  const data = await someAsyncOperation();
  res.json(data);
}));
```

#### Global Error Handler
Catches all errors and returns consistent error responses:
```javascript
app.use(errorHandler);
```

### 6. Logging (`utils/logger.js`)

Simple logger with different log levels:
- `logger.error()` - Errors
- `logger.warn()` - Warnings
- `logger.info()` - Informational
- `logger.debug()` - Debug (development only)

Can be replaced with Winston or Pino in the future.

### 7. Routes (`routes/`)

Routes are organized by feature:
- `auth.js` - Authentication routes
- `session.js` - Session management routes
- `finance.js` - Finance feature routes

All routes use:
- `asyncHandler` for error handling
- Service layer for business logic
- Middleware for validation and permissions

## Key Improvements

### 1. Separation of Concerns
- **Controllers**: Handle HTTP requests/responses
- **Services**: Business logic
- **Models**: Data access
- **Middleware**: Cross-cutting concerns

### 2. Error Handling
- Consistent error responses
- Automatic error catching with `asyncHandler`
- Custom error classes with status codes
- Proper error logging

### 3. Security
- Security headers
- Rate limiting
- Input validation
- Permission checks

### 4. Code Quality
- Consistent patterns
- Reusable code
- Better testability
- Clear separation of responsibilities

### 5. Maintainability
- Easy to find code
- Easy to add features
- Easy to modify
- Well-documented

## Request Flow

1. **Request arrives** → Express app
2. **Security middleware** → Headers, rate limiting
3. **Body parsing** → JSON, URL-encoded
4. **Session middleware** → Load session
5. **Route handler** → Controller method
6. **Permission check** → Middleware
7. **Validation** → Middleware (if needed)
8. **Service call** → Business logic
9. **Model access** → Database
10. **Response** → JSON response
11. **Error handling** → If error occurs

## Adding New Features

### 1. Add a New Service
```javascript
// services/newService.js
class NewService {
  async doSomething(data) {
    // Business logic
  }
}
module.exports = new NewService();
```

### 2. Add a New Controller Method
```javascript
// controllers/NewController.js
const newService = require('../services/newService');

class NewController {
  doSomething = asyncHandler(async (req, res) => {
    const result = await newService.doSomething(req.body);
    res.json({ success: true, data: result });
  });
}
```

### 3. Add a New Route
```javascript
// routes/new.js
const router = express.Router();
const newController = new NewController();

router.post('/something', requireAuth, newController.doSomething);
```

### 4. Register Route
```javascript
// routes/index.js
router.use('/new', require('./new'));
```

## Testing

The architecture supports testing:

1. **Unit Tests**: Test services and utilities in isolation
2. **Integration Tests**: Test routes with test database
3. **E2E Tests**: Test full request/response cycle

Example:
```javascript
// Test service
const service = require('../services/financeService');
const result = await service.getUserAdvances(userId, options);

// Test controller
const app = createApp();
const response = await request(app)
  .get('/api/finance/advances')
  .set('Cookie', sessionCookie);
```

## Best Practices

1. **Always use `asyncHandler`** for async route handlers
2. **Use services** for business logic, not controllers
3. **Validate input** using validation middleware
4. **Check permissions** using permission middleware
5. **Log errors** using logger, not console
6. **Return consistent** JSON responses
7. **Handle errors** properly with AppError
8. **Keep controllers thin** - delegate to services

## Future Improvements

- [ ] Add request validation with Joi or Yup
- [ ] Add API documentation with Swagger/OpenAPI
- [ ] Add unit tests with Jest
- [ ] Add integration tests
- [ ] Replace simple logger with Winston or Pino
- [ ] Add request ID tracking
- [ ] Add performance monitoring
- [ ] Add caching layer (Redis)
- [ ] Add message queue for async tasks
- [ ] Migrate to TypeScript

