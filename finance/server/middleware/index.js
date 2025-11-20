const cors = require('cors');
const session = require('express-session');
const cookieParser = require('cookie-parser');
const bodyParser = require('body-parser');
const path = require('path');
const express = require('express');
const config = require('../config');
const {
  requestTimeout,
  responseSizeLimit,
  memoryMonitor,
  bodySizeLimit,
  cleanup
} = require('./memory');

module.exports = (app) => {
  // Memory management middleware (apply early)
  app.use(memoryMonitor({
    threshold: 0.8, // Warn at 80% heap usage
    logInterval: 60000, // Log every minute
    enableGC: config.nodeEnv === 'production' // Only enable GC in production if --expose-gc flag is set
  }));

  // Request timeout (30 seconds)
  app.use(requestTimeout(30000));

  // Body size limit (1MB)
  app.use(bodySizeLimit(1024 * 1024));

  // Response size limit (10MB)
  app.use(responseSizeLimit(10 * 1024 * 1024));

  // Cleanup middleware
  app.use(cleanup());

  // CORS middleware - allow production domain when behind proxy
  const allowedOrigins = [
    config.clientUrl,
    'https://cbp.africacdc.org',
    'http://localhost:3002',
    'http://localhost:3003'
  ];
  
  app.use(cors({
    origin: function (origin, callback) {
      // Allow requests with no origin (like mobile apps or curl requests) or from allowed origins
      if (!origin || allowedOrigins.indexOf(origin) !== -1) {
        callback(null, true);
      } else {
        callback(null, true); // Allow all origins when behind proxy (proxy handles security)
      }
    },
    credentials: true
  }));

  // Body parsing middleware with limits
  app.use(bodyParser.json({
    limit: '1mb', // Limit JSON body size
    strict: true
  }));
  app.use(bodyParser.urlencoded({
    extended: true,
    limit: '1mb', // Limit URL-encoded body size
    parameterLimit: 100 // Limit number of parameters
  }));
  app.use(cookieParser());

  // Session middleware with memory-efficient settings
  app.use(session({
    secret: config.sessionSecret,
    resave: false,
    saveUninitialized: false,
    name: 'finance.sid', // Custom session name to avoid conflicts
    cookie: {
      secure: config.nodeEnv === 'production', // Only use secure cookies in production (HTTPS)
      httpOnly: true,
      maxAge: 24 * 60 * 60 * 1000, // 24 hours
      sameSite: config.nodeEnv === 'production' ? 'none' : 'lax', // Allow cross-site cookies in production
      path: '/' // Ensure cookie is available for all paths (works with reverse proxy)
      // Note: Don't set domain - let browser handle it based on the request domain
    },
    // Memory-efficient session store settings
    rolling: false, // Don't reset expiration on every request
    unset: 'destroy' // Destroy session when unset
  }));

  // Serve Laravel assets (proxy to main app assets)
  app.use('/assets', express.static(path.join(config.assetsBasePath, 'assets')));
  app.use('/apm/assets', express.static(path.join(config.assetsBasePath, 'apm/public/assets')));

  // Serve static files from React app in production
  // Handle both direct access and reverse proxy access (with /finance prefix)
  if (config.nodeEnv === 'production') {
    const buildPath = path.join(__dirname, '../../frontend/build');
    // Serve static files at root (for direct access)
    app.use(express.static(buildPath));
    // Also serve static files at /finance (for reverse proxy access)
    app.use('/finance', express.static(buildPath));
  }
};

