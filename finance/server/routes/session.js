const express = require('express');
const router = express.Router();
const config = require('../config');
const sessionService = require('../services/sessionService');
const { asyncHandler } = require('../utils/errorHandler');
const { authLimiter } = require('../middleware/security');
const logger = require('../utils/logger');

// Session transfer endpoint - receives session from CI app
// Matches Laravel APM implementation: stores entire JSON as user, plus base_url and permissions separately
router.post('/transfer', authLimiter, asyncHandler(async (req, res) => {
  const { sessionData } = req.body;
  
  logger.debug('Session transfer request', {
    hasSessionData: !!sessionData,
    ip: req.ip,
    forwardedFor: req.get('X-Forwarded-For')
  });

  const result = await sessionService.transferSession(sessionData, req.session);

  res.json({
    success: true,
    message: 'Session transferred successfully',
    user: result.user
  });
}));

// Get current session - matches Laravel pattern
router.get('/', asyncHandler(async (req, res) => {
  logger.debug('Session check request', {
    sessionID: req.sessionID,
    hasSession: !!req.session,
    ip: req.ip
  });

  const session = sessionService.getSession(req.session);

  if (session) {
    logger.debug('Session valid', {
      userId: session.user.staff_id || session.user.id || 'N/A'
    });
    res.json(session);
  } else {
    logger.debug('No valid session found');
    res.status(401).json({
      authenticated: false,
      message: 'No active session',
      redirectUrl: `${config.ciBaseUrl}/auth`
    });
  }
}));

module.exports = router;

