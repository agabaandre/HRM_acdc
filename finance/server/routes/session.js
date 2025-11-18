const express = require('express');
const router = express.Router();
const config = require('../config');

// Session transfer endpoint - receives session from CI app
// Matches Laravel APM implementation: stores entire JSON as user, plus base_url and permissions separately
router.post('/transfer', (req, res) => {
  try {
    const { sessionData } = req.body;
    
    if (!sessionData) {
      return res.status(400).json({ 
        success: false, 
        message: 'Session data is required',
        redirectUrl: `${config.ciBaseUrl}/auth`
      });
    }

    // Store session data exactly like Laravel APM does:
    // session(['user' => $json, 'base_url' => $json['base_url'] ?? '', 'permissions' => $json['permissions'] ?? []])
    req.session.user = sessionData; // Store entire JSON object as user
    req.session.base_url = sessionData.base_url || '';
    req.session.permissions = sessionData.permissions || [];
    req.session.authenticated = true;
    req.session.transferredAt = new Date().toISOString();

    res.json({
      success: true,
      message: 'Session transferred successfully',
      user: sessionData // Return full user object like Laravel
    });
  } catch (error) {
    console.error('Session transfer error:', error);
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

