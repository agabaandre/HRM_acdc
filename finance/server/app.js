/**
 * Express App Factory
 * Creates and configures Express application
 * This pattern allows for better testing and separation of concerns
 */
const express = require('express');
const path = require('path');
const config = require('./config');
const middleware = require('./middleware');
const routes = require('./routes');
const { errorHandler, notFoundHandler } = require('./utils/errorHandler');
const logger = require('./utils/logger');

const base64UrlDecode = (input) => {
  const normalized = input.replace(/-/g, '+').replace(/_/g, '/');
  const padding = '='.repeat((4 - (normalized.length % 4)) % 4);
  return Buffer.from(normalized + padding, 'base64').toString('utf-8');
};

const decodeSsoToken = (token) => {
  if (!token || typeof token !== 'string') {
    throw new Error('Missing token');
  }
  const raw = token.trim();
  const parts = raw.split('.');
  const jwtSecret = process.env.JWT_SECRET || process.env.APP_KEY || '';

  // Try JWT first
  if (parts.length === 3 && jwtSecret) {
    const [h, p, s] = parts;
    const expected = Buffer.from(
      require('crypto').createHmac('sha256', jwtSecret).update(`${h}.${p}`).digest('base64')
        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, ''),
      'utf-8'
    ).toString();
    if (expected === s) {
      const payload = JSON.parse(base64UrlDecode(p));
      if (!payload || typeof payload !== 'object') {
        throw new Error('Invalid JWT payload');
      }
      if (payload.exp && Number(payload.exp) < Math.floor(Date.now() / 1000)) {
        throw new Error('JWT expired');
      }
      return payload;
    }
  }

  // Backward-compatible fallback: base64(json)
  const decodedToken = Buffer.from(raw, 'base64').toString('utf-8');
  return JSON.parse(decodedToken);
};

/**
 * Create Express application
 * @returns {express.Application} Configured Express app
 */
function createApp() {
  const app = express();

  // Trust proxy - IMPORTANT for reverse proxy to work correctly
  if (config.nodeEnv === 'production') {
    app.set('trust proxy', 1);
  }

  // Apply middleware
  middleware(app);

  // Root route handler - handles token processing and SPA serving
  const handleRootRoute = (req, res) => {
    const rawToken = req.query.token;

    if (rawToken) {
      try {
        const json = decodeSsoToken(rawToken);

        if (!json) {
          throw new Error('Invalid token format');
        }

        // Store session data
        req.session.user = json;
        req.session.base_url = json.base_url || '';
        req.session.permissions = json.permissions || [];
        req.session.authenticated = true;
        req.session.transferredAt = new Date().toISOString();

        // Check Finance access permission
        const permissions = json.permissions || [];
        const hasFinanceAccess = permissions.includes('92') || permissions.includes(92);

        if (!hasFinanceAccess) {
          return res.redirect(`${config.ciBaseUrl}/auth?error=no_finance_permission`);
        }

        // Save session and serve React app
        req.session.save((err) => {
          if (err) {
            logger.error('Error saving session in root route', { error: err.message });
            return res.redirect(`${config.ciBaseUrl}/auth?error=session_error`);
          }

          serveReactApp(req, res);
        });
        return;
      } catch (error) {
        logger.error('Token processing error', { error: error.message });
        return res.redirect(`${config.ciBaseUrl}/auth`);
      }
    }

    // No token - check existing session
    if (req.session && req.session.authenticated) {
      const permissions = req.session.permissions || [];
      const hasFinanceAccess = permissions.includes('92') || permissions.includes(92);

      if (!hasFinanceAccess) {
        return res.redirect(`${config.ciBaseUrl}/auth?error=no_finance_permission`);
      }
    } else {
      return res.redirect(`${config.ciBaseUrl}/auth`);
    }

    serveReactApp(req, res);
  };

  // Helper function to serve React app
  const serveReactApp = (req, res) => {
    if (config.nodeEnv === 'production') {
      res.sendFile(path.join(__dirname, '../frontend/build', 'index.html'));
    } else {
      res.redirect(config.clientUrl);
    }
  };

  // Register root routes
  app.get('/', handleRootRoute);
  app.get('/finance', handleRootRoute);

  // API Routes - handle both /api and /finance/api for reverse proxy
  app.use('/api', routes);
  app.use('/finance/api', routes);

  // Serve React app in production (catch-all for SPA routing)
  if (config.nodeEnv === 'production') {
    const serveIndex = (req, res) => {
      // Skip API routes
      if (req.path.startsWith('/api') || req.path.startsWith('/finance/api')) {
        return res.status(404).json({ error: 'Not found' });
      }
      res.sendFile(path.join(__dirname, '../frontend/build', 'index.html'));
    };

    app.get('/finance/*', serveIndex);
    app.get('*', serveIndex);
  }

  // Error handling middleware (must be last)
  app.use(notFoundHandler);
  app.use(errorHandler);

  return app;
}

module.exports = createApp;

