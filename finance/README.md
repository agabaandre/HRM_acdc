# Finance Management Module

A Node.js/Express backend with React frontend for the Africa CDC Finance Management System.

## Project Structure

```
finance/
├── server/                # Node.js Express server
│   ├── index.js          # Main server entry point
│   ├── config/           # Configuration files
│   │   └── index.js      # App configuration
│   ├── middleware/       # Express middleware
│   │   └── index.js      # Middleware setup
│   └── routes/           # API route handlers
│       ├── index.js      # Route aggregator
│       ├── auth.js       # Authentication routes
│       ├── session.js    # Session management routes
│       └── finance.js    # Finance-specific routes
├── frontend/             # React application
│   ├── src/
│   │   ├── components/   # React components
│   │   ├── App.js        # Main app component
│   │   └── index.js      # Entry point
│   └── package.json      # Frontend dependencies
├── package.json          # Server dependencies
└── README.md
```

## Features

- Session transfer from CodeIgniter app
- React frontend with Bootstrap 5 styling
- Express.js backend with session management
- Automatic redirect to CI login for unauthenticated users
- Protected routes

## Setup Instructions

### 1. Install Server Dependencies

```bash
cd finance
npm install
```

### 2. Install Frontend Dependencies

```bash
cd frontend
npm install
```

### 3. Configure Environment

The setup script will create separate environment files for backend and frontend. You can also create them manually:

**Backend `.env` file** (in `finance/` directory):
```env
# Backend Server Configuration
PORT=3003
NODE_ENV=development
SESSION_SECRET=your-secret-key-here
CLIENT_URL=http://localhost:3002
CI_BASE_URL=http://localhost/staff
ASSETS_BASE_PATH=/opt/homebrew/var/www/staff

# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=your-password
DB_DATABASE=approvals_management
DB_CHARSET=utf8mb4
```

**Frontend `frontend/.env` file** (in `finance/frontend/` directory):
```env
# Frontend React App Configuration
PORT=3002
REACT_APP_API_URL=http://localhost:3003/api
REACT_APP_CI_BASE_URL=http://localhost/staff
```

> **Note:** The `setup.sh` script will automatically create both `.env` files with default values. You can then edit them as needed.

### 4. Run the Application

#### Development Mode (Run both server and client)

From the `finance/` directory:

```bash
npm run dev:all
```

This will start:
- Node.js server on `http://localhost:3003`
- React app on `http://localhost:3002`

#### Or run separately:

**Terminal 1 - Server:**
```bash
npm run dev
```

**Terminal 2 - Frontend:**
```bash
npm run client
```

### 5. Production Build

```bash
# Build React app
npm run client:build

# Start server (serves built React app)
NODE_ENV=production npm start
```

## Session Transfer from CodeIgniter

The app receives session data from the CodeIgniter home page via a token parameter:

1. User clicks "Finance Management" card on the CI home page
2. CI app generates a token with session data
3. User is redirected to React app with token: `http://localhost:3002?token=...`
4. React app decodes token and transfers session to Node.js server
5. User is authenticated and can access the finance dashboard

## API Endpoints

### Session Management
- `POST /api/session/transfer` - Transfer session from CI app
- `GET /api/session` - Get current session
- `POST /api/auth/logout` - Logout (redirects to CI login)
- `GET /api/ci-login-url` - Get CI login URL for redirects

### Finance Data
- `GET /api/finance/data` - Get protected finance data (requires authentication)

## Styling

The app uses Bootstrap 5 and custom CSS matching the Laravel/CodeIgniter UI:
- Primary color: `#119a48` (green)
- Secondary color: `#9f2240` (red)
- Font: Inter, Roboto, system fonts

## Development Notes

- The React app proxies API requests to `http://localhost:3003` in development
- Sessions are stored using `express-session`
- CORS is enabled for development (configure for production)
- The app matches the design patterns from `home.php` in the CI app

