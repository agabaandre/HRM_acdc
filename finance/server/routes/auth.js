const express = require('express');
const router = express.Router();
const config = require('../config');

// Logout endpoint - clears session and redirects to CI login
router.post('/logout', (req, res) => {
  req.session.destroy((err) => {
    if (err) {
      return res.status(500).json({
        success: false,
        message: 'Failed to logout'
      });
    }
    res.clearCookie('connect.sid');
    res.json({
      success: true,
      message: 'Logged out successfully',
      redirectUrl: `${config.ciBaseUrl}/auth`
    });
  });
});

module.exports = router;

