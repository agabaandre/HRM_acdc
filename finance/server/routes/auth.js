const express = require('express');
const router = express.Router();
const config = require('../config');
const sessionService = require('../services/sessionService');
const { asyncHandler } = require('../utils/errorHandler');
const logger = require('../utils/logger');

// Logout endpoint - clears session and redirects to CI login
router.post('/logout', asyncHandler(async (req, res) => {
  await sessionService.destroySession(req.session);
  res.clearCookie('finance.sid'); // Use the same cookie name as session config
  res.json({
    success: true,
    message: 'Logged out successfully',
    redirectUrl: `${config.ciBaseUrl}/auth`
  });
}));

module.exports = router;

