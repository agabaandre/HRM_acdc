/**
 * Finance Controller
 * Handles all finance-related HTTP requests
 */
const FinanceModel = require('../models/FinanceModel');

class FinanceController {
  constructor() {
    this.model = new FinanceModel();
  }

  /**
   * Get finance data
   * GET /api/finance/data
   */
  async getData(req, res) {
    try {
      const user = req.session.user;
      
      res.json({
        success: true,
        data: {
          message: 'This is protected finance data',
          user: user,
          base_url: req.session.base_url || '',
          permissions: req.session.permissions || []
        }
      });
    } catch (error) {
      console.error('FinanceController.getData error:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch finance data',
        error: process.env.NODE_ENV === 'development' ? error.message : undefined
      });
    }
  }

  /**
   * Get user advances
   * GET /api/finance/advances
   */
  async getAdvances(req, res) {
    try {
      const userId = req.session.user?.staff_id || req.session.user?.id;
      const { page = 1, limit = 50, status } = req.query;
      
      const offset = (parseInt(page) - 1) * parseInt(limit);
      
      const advances = await this.model.getUserAdvances(userId, {
        limit: Math.min(parseInt(limit), 100), // Max 100 per page
        offset,
        status
      });

      const total = await this.model.count({ user_id: userId });

      res.json({
        success: true,
        data: advances,
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total,
          totalPages: Math.ceil(total / parseInt(limit))
        }
      });
    } catch (error) {
      console.error('FinanceController.getAdvances error:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch advances',
        error: process.env.NODE_ENV === 'development' ? error.message : undefined
      });
    }
  }

  /**
   * Get user missions
   * GET /api/finance/missions
   */
  async getMissions(req, res) {
    try {
      const userId = req.session.user?.staff_id || req.session.user?.id;
      const { page = 1, limit = 50, status } = req.query;
      
      const offset = (parseInt(page) - 1) * parseInt(limit);
      
      const missions = await this.model.getUserMissions(userId, {
        limit: Math.min(parseInt(limit), 100), // Max 100 per page
        offset,
        status
      });

      const total = await this.model.count({ user_id: userId });

      res.json({
        success: true,
        data: missions,
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total,
          totalPages: Math.ceil(total / parseInt(limit))
        }
      });
    } catch (error) {
      console.error('FinanceController.getMissions error:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch missions',
        error: process.env.NODE_ENV === 'development' ? error.message : undefined
      });
    }
  }

  /**
   * Get budgets
   * GET /api/finance/budgets
   */
  async getBudgets(req, res) {
    try {
      const { page = 1, limit = 100, divisionId, year } = req.query;
      
      const offset = (parseInt(page) - 1) * parseInt(limit);
      
      const budgets = await this.model.getBudgets({
        limit: Math.min(parseInt(limit), 200), // Max 200 per page
        offset,
        divisionId,
        year
      });

      // Count total (simplified - adjust based on your needs)
      const total = budgets.length;

      res.json({
        success: true,
        data: budgets,
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total,
          totalPages: Math.ceil(total / parseInt(limit))
        }
      });
    } catch (error) {
      console.error('FinanceController.getBudgets error:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch budgets',
        error: process.env.NODE_ENV === 'development' ? error.message : undefined
      });
    }
  }

  /**
   * Get finance statistics
   * GET /api/finance/stats
   */
  async getStats(req, res) {
    try {
      const userId = req.session.user?.staff_id || req.session.user?.id;
      
      const stats = await this.model.getFinanceStats(userId);

      res.json({
        success: true,
        data: stats
      });
    } catch (error) {
      console.error('FinanceController.getStats error:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch statistics',
        error: process.env.NODE_ENV === 'development' ? error.message : undefined
      });
    }
  }

  /**
   * Get user permissions
   * GET /api/finance/permissions
   */
  getPermissions(req, res) {
    try {
      const permissions = req.session.permissions || [];
      
      res.json({
        success: true,
        data: {
          hasFinanceAccess: permissions.includes('92') || permissions.includes(92),
          hasFinanceSettings: permissions.includes('93') || permissions.includes(93),
          allPermissions: permissions
        }
      });
    } catch (error) {
      console.error('FinanceController.getPermissions error:', error);
      res.status(500).json({
        success: false,
        message: 'Failed to fetch permissions',
        error: process.env.NODE_ENV === 'development' ? error.message : undefined
      });
    }
  }

  /**
   * Test database connection
   * GET /api/finance/test-db
   */
  async testDb(req, res) {
    try {
      const result = await this.model.query('SELECT 1 as test');
      
      res.json({
        success: true,
        message: 'Database connection successful',
        data: result
      });
    } catch (error) {
      console.error('FinanceController.testDb error:', error);
      res.status(500).json({
        success: false,
        message: 'Database query failed',
        error: process.env.NODE_ENV === 'development' ? error.message : undefined
      });
    }
  }
}

module.exports = FinanceController;

