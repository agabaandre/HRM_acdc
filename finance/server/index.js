const express = require('express');
const path = require('path');
const config = require('./config');
const middleware = require('./middleware');
const routes = require('./routes');
const db = require('./database');

const app = express();

// Apply middleware
middleware(app);

// Root route handler - matches Laravel APM pattern for token processing
// This handles direct server-side access (though React app handles it client-side)
app.get('/', (req, res) => {
  // Get token from query parameter (matches Laravel: $request->query('token'))
  const base64Token = req.query.token;

  if (base64Token) {
    try {
      // Decode the base64 token (Laravel: base64_decode($base64Token))
      // Express automatically URL decodes query params, so we just need base64 decode
      const decodedToken = Buffer.from(base64Token, 'base64').toString('utf-8');
      
      // Parse the JSON data (Laravel: json_decode($decodedToken, true))
      const json = JSON.parse(decodedToken);

      if (!json) {
        throw new Error('Invalid token format');
      }

      // Store session data exactly like Laravel:
      // session(['user' => $json, 'base_url' => $json['base_url'] ?? '', 'permissions' => $json['permissions'] ?? []])
      req.session.user = json;
      req.session.base_url = json.base_url || '';
      req.session.permissions = json.permissions || [];
      req.session.authenticated = true;
      req.session.transferredAt = new Date().toISOString();

      // Check if user has permission 92 (Finance access)
      const permissions = json.permissions || [];
      const hasFinanceAccess = permissions.includes('92') || permissions.includes(92);
      
      if (!hasFinanceAccess) {
        // User doesn't have permission, redirect to CI with error message
        return res.redirect(`${config.ciBaseUrl}/auth?error=no_finance_permission`);
      }
    } catch (error) {
      console.error('Token processing error:', error);
      // On error, redirect to CI login
      return res.redirect(`${config.ciBaseUrl}/auth`);
    }
  } else {
    // No token provided, check existing session
    if (req.session && req.session.authenticated) {
      const permissions = req.session.permissions || [];
      const hasFinanceAccess = permissions.includes('92') || permissions.includes(92);
      
      if (!hasFinanceAccess) {
        // User doesn't have permission, redirect to CI
        return res.redirect(`${config.ciBaseUrl}/auth?error=no_finance_permission`);
      }
    } else {
      // No session, redirect to CI login
      return res.redirect(`${config.ciBaseUrl}/auth`);
    }
  }

  // Serve React app (which will handle client-side token processing)
  if (config.nodeEnv === 'production') {
    res.sendFile(path.join(__dirname, '../frontend/build', 'index.html'));
  } else {
    // In development, redirect to React dev server
    res.redirect(config.clientUrl);
  }
});

// API Routes
app.use('/api', routes);

// Serve React app in production (catch-all for SPA routing)
// This handles all non-API routes for the React SPA
if (config.nodeEnv === 'production') {
  app.get('*', (req, res) => {
    // Skip API routes
    if (req.path.startsWith('/api')) {
      return res.status(404).json({ error: 'Not found' });
    }
    res.sendFile(path.join(__dirname, '../frontend/build', 'index.html'));
  });
}

// Server reference for graceful shutdown
let server;

// Initialize database connection
async function startServer() {
  // Test database connection
  const dbConnected = await db.testConnection();
  if (!dbConnected) {
    console.warn('Warning: Database connection failed. Some features may not work.');
  }

  // Start server and store reference for graceful shutdown
  server = app.listen(config.port, () => {
    console.log(`Finance server running on port ${config.port}`);
    console.log(`Environment: ${config.nodeEnv}`);
    console.log(`Database: ${config.database.database}@${config.database.host}:${config.database.port}`);
    console.log(`Assets base path: ${config.assetsBasePath}`);
    
    // Log initial memory usage
    const { getMemoryUsage, formatBytes } = require('./middleware/memory');
    const memUsage = getMemoryUsage();
    console.log(`Initial memory usage: ${formatBytes(memUsage.heapUsed)} / ${formatBytes(memUsage.heapTotal)}`);
  });
  
  // Set server timeout to prevent hanging connections
  server.timeout = 30000; // 30 seconds
  server.keepAliveTimeout = 65000; // 65 seconds (slightly longer than load balancer)
  server.headersTimeout = 66000; // 66 seconds (slightly longer than keepAliveTimeout)
}

// Start the server
startServer().catch(error => {
  console.error('Failed to start server:', error);
  process.exit(1);
});

// Memory management: Set max listeners to prevent memory leaks
process.setMaxListeners(20);

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
  console.error('Uncaught Exception:', error);
  // Close database connections
  db.close().then(() => {
    process.exit(1);
  }).catch(() => {
    process.exit(1);
  });
});

// Handle unhandled promise rejections
process.on('unhandledRejection', (reason, promise) => {
  console.error('Unhandled Rejection at:', promise, 'reason:', reason);
  // Close database connections
  db.close().then(() => {
    process.exit(1);
  }).catch(() => {
    process.exit(1);
  });
});

// Graceful shutdown
const gracefulShutdown = async (signal) => {
  console.log(`${signal} signal received: closing HTTP server and database connections`);
  
  if (server) {
    server.close(() => {
      console.log('HTTP server closed');
    });
  }
  
  try {
    await db.close();
    console.log('Database connections closed');
    process.exit(0);
  } catch (error) {
    console.error('Error during shutdown:', error);
    process.exit(1);
  }
};

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => gracefulShutdown('SIGINT'));

module.exports = app;

