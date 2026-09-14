# Frontend Architecture

This document describes the refactored frontend architecture of the Finance app, following React and JavaScript best practices.

## Directory Structure

```
frontend/src/
├── components/          # React components
│   ├── Layout/         # Layout components (Header, Nav, Footer, etc.)
│   ├── Dashboard.js    # Dashboard component
│   ├── MyAdvances.js   # My Advances component
│   ├── MyMissions.js   # My Missions component
│   ├── Budgets.js      # Budgets component
│   └── ProtectedRoute.js # Route protection component
├── providers/          # Context providers
│   └── AuthProvider.js # Authentication context provider
├── hooks/              # Custom React hooks
│   └── useTranslation.js # Google Translate hook
├── services/           # API and business logic services
│   ├── apiService.js   # Axios configuration
│   └── sessionService.js # Session management service
├── utils/              # Utility functions
│   ├── config.js       # Configuration utilities
│   ├── tokenDecoder.js # Token decoding utilities
│   └── translation.js  # Google Translate utilities
└── App.js              # Main App component (simplified)
```

## Architecture Overview

### 1. Services Layer (`services/`)

Services handle API calls and business logic, keeping components clean and testable.

#### `apiService.js`
- Configures axios with default settings
- Sets up base URL and credentials
- Exports a configured axios instance

#### `sessionService.js`
- `transferSession()` - Transfers session from CI app
- `checkSession()` - Checks current session status
- `logout()` - Handles user logout
- `processTokenFromUrl()` - Processes token from URL query parameter
- `hasFinanceAccess()` - Checks if user has Finance permission (92)
- `redirectToCiLogin()` - Redirects to CI login page

### 2. Providers Layer (`providers/`)

Providers manage global state using React Context API.

#### `AuthProvider.js`
- Manages authentication state (user, permissions, loading, error)
- Provides `useAuth()` hook for accessing auth state
- Handles session initialization
- Provides `hasPermission()` helper function
- Provides `logout()` function

**Usage:**
```javascript
import { useAuth } from '../providers/AuthProvider';

function MyComponent() {
  const { user, permissions, hasPermission, logout, loading } = useAuth();
  
  if (loading) return <Loading />;
  
  return <div>Welcome, {user.name}</div>;
}
```

### 3. Hooks Layer (`hooks/`)

Custom hooks encapsulate reusable logic.

#### `useTranslation.js`
- Initializes Google Translate
- Applies translation based on user's preferred language
- Handles Google Translate loading and initialization

**Usage:**
```javascript
import { useTranslation } from '../hooks/useTranslation';

function MyComponent() {
  const { user } = useAuth();
  useTranslation(user); // Automatically translates based on user's language
  return <div>Content</div>;
}
```

### 4. Utils Layer (`utils/`)

Utility functions for common operations.

#### `config.js`
- `getApiBaseUrl()` - Determines API base URL based on environment
- `getCiBaseUrl()` - Gets CI base URL
- `getBasePath()` - Determines React Router base path
- Exports constants: `API_BASE_URL`, `CI_BASE_URL`

#### `tokenDecoder.js`
- `decodeToken()` - Decodes base64 token from URL
- `getTokenFromUrl()` - Extracts token from URL query params
- `removeTokenFromUrl()` - Removes token from URL without reload

#### `translation.js`
- `GTranslateFireEvent()` - Fires events for Google Translate
- `doGTranslate()` - Applies translation to specific language
- `initializeGoogleTranslateElement()` - Initializes Google Translate widget
- `getGoogleTranslateLang()` - Maps user language to Google Translate code
- `LANGUAGE_MAP` - Language code mapping

### 5. Components Layer (`components/`)

React components for UI rendering.

#### `ProtectedRoute.js`
- Wraps routes that require authentication
- Shows loading spinner while checking auth
- Redirects if not authenticated

## App.js Structure

The main `App.js` is now simplified to ~100 lines (down from ~400 lines):

```javascript
function App() {
  return (
    <SnackbarProvider>
      <AuthProvider>
        <ProtectedRoute>
          <AppRoutes />
        </ProtectedRoute>
      </AuthProvider>
    </SnackbarProvider>
  );
}
```

## Benefits of This Architecture

1. **Separation of Concerns**: Each layer has a specific responsibility
2. **Reusability**: Services, hooks, and utils can be reused across components
3. **Testability**: Each module can be tested independently
4. **Maintainability**: Code is organized and easy to find
5. **Scalability**: Easy to add new features without cluttering App.js
6. **Type Safety**: Ready for TypeScript migration if needed

## Migration Guide

### Before (Old App.js)
```javascript
// All logic in one file
const [user, setUser] = useState(null);
const checkSession = async () => { /* ... */ };
const handleLogout = async () => { /* ... */ };
// ... 300+ more lines
```

### After (New Structure)
```javascript
// App.js - Clean and simple
import { AuthProvider } from './providers/AuthProvider';
import { useAuth } from './providers/AuthProvider';

function App() {
  return (
    <AuthProvider>
      <AppRoutes />
    </AuthProvider>
  );
}

// In components
function MyComponent() {
  const { user, logout } = useAuth(); // Clean and simple
}
```

## Adding New Features

### Adding a New Service
1. Create file in `services/` directory
2. Export functions that handle API calls
3. Import and use in components or providers

### Adding a New Provider
1. Create file in `providers/` directory
2. Use React Context API
3. Export provider component and custom hook
4. Wrap App with provider

### Adding a New Hook
1. Create file in `hooks/` directory
2. Use React hooks (useState, useEffect, etc.)
3. Export custom hook
4. Use in components

### Adding a New Utility
1. Create file in `utils/` directory
2. Export pure functions
3. Import where needed

## Best Practices

1. **Services**: Keep API calls and business logic here
2. **Providers**: Use for global state that multiple components need
3. **Hooks**: Extract reusable component logic
4. **Utils**: Pure functions with no side effects
5. **Components**: Focus on rendering and user interaction

## Future Improvements

- [ ] Add TypeScript for type safety
- [ ] Add unit tests for services and utils
- [ ] Add integration tests for providers
- [ ] Add error boundary component
- [ ] Add request/response interceptors for API error handling
- [ ] Add caching layer for API responses

