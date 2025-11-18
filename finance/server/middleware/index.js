const cors = require('cors');
const session = require('express-session');
const cookieParser = require('cookie-parser');
const bodyParser = require('body-parser');
const path = require('path');
const express = require('express');
const config = require('../config');

module.exports = (app) => {
  // CORS middleware
  app.use(cors({
    origin: config.clientUrl,
    credentials: true
  }));

  // Body parsing middleware
  app.use(bodyParser.json());
  app.use(bodyParser.urlencoded({ extended: true }));
  app.use(cookieParser());

  // Session middleware
  app.use(session({
    secret: config.sessionSecret,
    resave: false,
    saveUninitialized: false,
    cookie: {
      secure: config.nodeEnv === 'production',
      httpOnly: true,
      maxAge: 24 * 60 * 60 * 1000 // 24 hours
    }
  }));

  // Serve Laravel assets (proxy to main app assets)
  app.use('/assets', express.static(path.join(config.assetsBasePath, 'assets')));
  app.use('/apm/assets', express.static(path.join(config.assetsBasePath, 'apm/public/assets')));

  // Serve static files from React app in production
  if (config.nodeEnv === 'production') {
    app.use(express.static(path.join(__dirname, '../../frontend/build')));
  }
};

