#!/bin/bash

echo "Setting up Finance Management Module..."
echo ""

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "Error: Node.js is not installed. Please install Node.js first."
    exit 1
fi

echo "Node.js version: $(node --version)"
echo "npm version: $(npm --version)"
echo ""

# Fix npm permissions if needed
if [ -d ~/.npm ] && [ ! -w ~/.npm ]; then
    echo "Fixing npm permissions..."
    sudo chown -R $(whoami) ~/.npm 2>/dev/null || true
fi

# Install server dependencies
echo "Installing server dependencies..."
npm install --legacy-peer-deps

# Install frontend dependencies
echo ""
echo "Installing frontend dependencies..."
cd frontend
npm install --legacy-peer-deps
cd ..

# Create backend .env file if it doesn't exist
if [ ! -f .env ]; then
    echo ""
    echo "Creating backend .env file..."
    cat > .env << 'EOF'
# Backend Server Configuration
PORT=3003
NODE_ENV=development
SESSION_SECRET=africacdc-finance-secret-key-change-in-production

# Frontend URL (for CORS)
CLIENT_URL=http://localhost:3002

# CodeIgniter App URL
CI_BASE_URL=http://localhost/staff

# Assets Base Path
ASSETS_BASE_PATH=/opt/homebrew/var/www/staff

# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=
DB_DATABASE=approvals_management
DB_CHARSET=utf8mb4
EOF
    echo "Backend .env file created. Please update it with your configuration."
fi

# Create frontend .env file if it doesn't exist
if [ ! -f frontend/.env ]; then
    echo ""
    echo "Creating frontend .env file..."
    cat > frontend/.env << 'EOF'
# Frontend React App Configuration
PORT=3002

# Backend API URL
REACT_APP_API_URL=http://localhost:3003/api

# CodeIgniter App URL (for redirects)
REACT_APP_CI_BASE_URL=http://localhost/staff
EOF
    echo "Frontend .env file created. Please update it with your configuration."
fi

echo ""
echo "Setup complete!"
echo ""
echo "To start the application:"
echo "  Development (both server and client): npm run dev:all"
echo "  Server only: npm run dev"
echo "  Client only: npm run client"
echo ""
echo "Backend server will run on: http://localhost:3003"
echo "Frontend app will run on: http://localhost:3002"
echo ""
echo "Environment files created:"
echo "  - .env (backend configuration)"
echo "  - frontend/.env (frontend configuration)"

