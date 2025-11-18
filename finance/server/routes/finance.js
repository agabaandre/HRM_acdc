const express = require('express');
const router = express.Router();
const db = require('../database');

// Middleware to check authentication
const requireAuth = (req, res, next) => {
  if (!req.session || !req.session.authenticated) {
    return res.status(401).json({
      success: false,
      message: 'Unauthorized'
    });
  }
  next();
};

// Protected route example - matches Laravel pattern
router.get('/data', requireAuth, (req, res) => {
  res.json({
    success: true,
    data: {
      message: 'This is protected finance data',
      user: req.session.user,
      base_url: req.session.base_url || '',
      permissions: req.session.permissions || []
    }
  });
});

// Example database query route
router.get('/test-db', requireAuth, async (req, res) => {
  try {
    // Example query to test database connection
    const result = await db.query('SELECT 1 as test');
    res.json({
      success: true,
      message: 'Database connection successful',
      data: result
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Database query failed',
      error: error.message
    });
  }
});

module.exports = router;
