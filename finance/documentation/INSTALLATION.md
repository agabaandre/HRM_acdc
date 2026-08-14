# Installation Guide

## Prerequisites

- Node.js (v14 or higher)
- npm (v6 or higher)

## Step 1: Fix npm Permissions (if needed)

If you encounter permission errors, run:

```bash
sudo chown -R $(whoami) ~/.npm
```

## Step 2: Install Server Dependencies

```bash
cd finance
npm install --legacy-peer-deps
```

## Step 3: Install Frontend Dependencies

```bash
cd frontend
npm install --legacy-peer-deps
cd ..
```

## Step 4: Configure Environment

Create a `.env` file in the `finance/` directory:

**Backend `.env` file:**
```env
# Backend Server Configuration
PORT=3003
NODE_ENV=development
SESSION_SECRET=africacdc-finance-secret-key-change-in-production
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

**Frontend `frontend/.env` file:**
```env
# Frontend React App Configuration
PORT=3002
REACT_APP_API_URL=http://localhost:3003/api
REACT_APP_CI_BASE_URL=http://localhost/staff
```

## Step 5: Start the Application

### Option 1: Run Both Server and Client Together

```bash
npm run dev:all
```

### Option 2: Run Separately

**Terminal 1 - Server:**
```bash
npm run dev
```

**Terminal 2 - Frontend:**
```bash
npm run client
```

## Step 6: Access the Application

- React App: http://localhost:3002
- API Server: http://localhost:3003
- Assets: Served from Laravel public directory via proxy

## Troubleshooting

### npm Permission Errors

```bash
sudo chown -R $(whoami) ~/.npm
npm cache clean --force
```

### Port Already in Use

Change the port in `.env` or `package.json` scripts.

### Assets Not Loading

1. Verify `ASSETS_BASE_PATH` in `.env` points to your Laravel root
2. Check that assets exist in `/opt/homebrew/var/www/staff/assets/`
3. Ensure the Node.js server has read permissions

### React Build Errors

```bash
cd frontend
rm -rf node_modules package-lock.json
npm install --legacy-peer-deps
```

## Production Build

```bash
# Build React app
cd frontend
npm run build
cd ..

# Start production server
NODE_ENV=production npm start
```

## Layout Components

The React app now uses layout components that match the Laravel design:

- `Layout` - Main layout wrapper
- `Header` - Top navigation bar with user menu
- `Nav` - Side navigation menu
- `Footer` - Page footer
- `Breadcrumbs` - Breadcrumb navigation

All components are styled to match the Laravel application's UI.

