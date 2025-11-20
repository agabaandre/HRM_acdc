const express = require('express');
const router = express.Router();
const config = require('../config');

// Session transfer endpoint - receives session from CI app
// Matches Laravel APM implementation: stores entire JSON as user, plus base_url and permissions separately
router.post('/transfer', (req, res) => {
  try {
    const { sessionData } = req.body;
    
    if (!sessionData) {
      console.error('Session transfer failed: No session data provided');
      return res.status(400).json({ 
        success: false, 
        message: 'Session data is required',
        redirectUrl: `${config.ciBaseUrl}/auth`
      });
    }

    // Debug logging (always log in production for troubleshooting)
    console.log('Session transfer - Received session data:', {
      hasUser: !!sessionData,
      hasBaseUrl: !!sessionData.base_url,
      hasPermissions: !!sessionData.permissions,
      staffId: sessionData.staff_id || sessionData.id || 'N/A',
      ip: req.ip,
      forwardedFor: req.get('X-Forwarded-For')
    });

    // Store session data exactly like Laravel APM does:
    // session(['user' => $json, 'base_url' => $json['base_url'] ?? '', 'permissions' => $json['permissions'] ?? []])
    req.session.user = sessionData; // Store entire JSON object as user
    req.session.base_url = sessionData.base_url || '';
    req.session.permissions = sessionData.permissions || [];
    req.session.authenticated = true;
    req.session.transferredAt = new Date().toISOString();

    // Explicitly save the session before sending response
    req.session.save((err) => {
      if (err) {
        console.error('Error saving session:', err);
        return res.status(500).json({
          success: false,
          message: 'Failed to save session',
          error: err.message,
          redirectUrl: `${config.ciBaseUrl}/auth`
        });
      }

      if (process.env.NODE_ENV === 'development') {
        console.log('Session saved successfully - Session ID:', req.sessionID);
      }

      res.json({
        success: true,
        message: 'Session transferred successfully',
        user: sessionData // Return full user object like Laravel
      });
    });
  } catch (error) {
    console.error('Session transfer error:', error);
    console.error('Error stack:', error.stack);
    res.status(500).json({
      success: false,
      message: 'Failed to transfer session',
      error: error.message,
      redirectUrl: `${config.ciBaseUrl}/auth`
    });
  }
});

// Get current session - matches Laravel pattern
router.get('/', (req, res) => {
  // Debug logging (remove in production)
  if (process.env.NODE_ENV === 'development') {
    console.log('Session check - Session ID:', req.sessionID);
    console.log('Session check - Authenticated:', req.session?.authenticated);
    console.log('Session check - Has user:', !!req.session?.user);
  }

  if (req.session && req.session.authenticated && req.session.user) {
    res.json({
      authenticated: true,
      user: req.session.user,
      base_url: req.session.base_url || '',
      permissions: req.session.permissions || [],
      transferredAt: req.session.transferredAt
    });
  } else {
    res.status(401).json({
      authenticated: false,
      message: 'No active session',
      redirectUrl: `${config.ciBaseUrl}/auth`
    });
  }
});

module.exports = router;

