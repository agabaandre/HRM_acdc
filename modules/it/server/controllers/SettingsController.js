/**
 * Settings Controller
 * Handles finance settings (requires permission 93)
 */
class SettingsController {
  /**
   * Get finance settings
   * GET /api/finance/settings
   */
  getSettings(req, res) {
    try {
      res.json({
        success: true,
        data: {
          message: 'Finance settings data',
          user: req.session.user,
          permissions: req.session.permissions || []
        }
      });
    } catch (error) {
      console.error('SettingsController.getSettings error:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch settings',
        error: process.env.NODE_ENV === 'development' ? error.message : undefined
      });
    }
  }
}

module.exports = SettingsController;

