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
    } catch (error) {
      console.error('Token processing error:', error);
      // On error, redirect to CI login
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

// Initialize database connection
async function startServer() {
  // Test database connection
  const dbConnected = await db.testConnection();
  if (!dbConnected) {
    console.warn('Warning: Database connection failed. Some features may not work.');
  }

  // Start server
  app.listen(config.port, () => {
    console.log(`Finance server running on port ${config.port}`);
    console.log(`Environment: ${config.nodeEnv}`);
    console.log(`Database: ${config.database.database}@${config.database.host}:${config.database.port}`);
    console.log(`Assets base path: ${config.assetsBasePath}`);
  });
}

// Start the server
startServer().catch(error => {
  console.error('Failed to start server:', error);
  process.exit(1);
});

// Graceful shutdown
process.on('SIGTERM', async () => {
  console.log('SIGTERM signal received: closing HTTP server and database connections');
  await db.close();
  process.exit(0);
});

process.on('SIGINT', async () => {
  console.log('SIGINT signal received: closing HTTP server and database connections');
  await db.close();
  process.exit(0);
});

module.exports = app;

