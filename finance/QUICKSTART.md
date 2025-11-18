# Quick Start Guide

## Prerequisites
- Node.js (v14 or higher)
- npm (v6 or higher)

## Installation

1. **Run the setup script:**
   ```bash
   cd finance
   ./setup.sh
   ```

   Or manually:
   ```bash
   npm install
   cd frontend && npm install && cd ..
   ```

2. **Start the application:**

   **Option 1: Run both server and client together (recommended for development)**
   
   You can run from either location:
   
   From root `finance/` directory:
   ```bash
   npm run dev:all
   ```
   
   From `finance/frontend/` directory:
   ```bash
   npm run dev:all
   ```

   **Option 2: Run separately**
   
   From root directory:
   
   Terminal 1 (Server):
   ```bash
   npm run dev
   ```
   
   Terminal 2 (Client):
   ```bash
   npm run client
   ```
   
   From frontend directory:
   
   Terminal 1 (Frontend):
   ```bash
   npm run dev
   ```
   
   Terminal 2 (Backend):
   ```bash
   npm run server
   ```

## Accessing the Application

1. **Direct Access:**
   - React App: http://localhost:3002
   - API Server: http://localhost:3003

2. **From CodeIgniter Home Page:**
   - Login to your CI app
   - Click on "Finance Management" card
   - You'll be redirected to the React app with your session automatically transferred

## Testing Session Transfer

1. Log into the CodeIgniter app
2. Navigate to the home page
3. Click the "Finance Management" card
4. You should be automatically logged into the Finance app with your CI session

## Authentication Flow

1. All authentication is handled by the CodeIgniter app
2. If you access the finance app without a session, you'll be automatically redirected to the CI login page
3. After logging in to CI, click the "Finance Management" card to access the finance app

## API Endpoints

- `GET /api/health` - Health check
- `POST /api/session/transfer` - Transfer session from CI
- `GET /api/session` - Get current session
- `POST /api/auth/logout` - Logout (redirects to CI login)
- `GET /api/ci-login-url` - Get CI login URL
- `GET /api/finance/data` - Get finance data (protected)

## Troubleshooting

### Port already in use
If port 3002 or 3003 is already in use, you can:
- Change the port in `package.json` scripts
- Set `PORT` environment variable for the server
- Set `PORT` in `.env` file for React (create `frontend/.env`)

### CORS errors
Make sure the `CLIENT_URL` in `.env` matches your React app URL.

### Session not transferring
- Check browser console for errors
- Verify the token is being generated correctly in CI
- Check that the Node.js server is running
- Verify CORS settings allow credentials

