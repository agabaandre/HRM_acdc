const express = require('express');
const router = express.Router();
const authRoutes = require('./auth');
const sessionRoutes = require('./session');
const financeRoutes = require('./finance');
const config = require('../config');

// Health check
router.get('/health', (req, res) => {
  res.json({ status: 'ok', message: 'Finance server is running' });
});

// Get CI login URL for redirects
router.get('/ci-login-url', (req, res) => {
  res.json({ 
    loginUrl: `${config.ciBaseUrl}/auth` 
  });
});

// Mount route modules
router.use('/auth', authRoutes);
router.use('/session', sessionRoutes);
router.use('/finance', financeRoutes);

module.exports = router;
