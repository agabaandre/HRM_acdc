/**
 * Finance Service
 * Handles finance-related business logic
 */
const FinanceModel = require('../models/FinanceModel');
const { AppError } = require('../utils/errorHandler');
const logger = require('../utils/logger');

class FinanceService {
  constructor() {
    this.model = new FinanceModel();
  }

  /**
   * Get finance data for user
   * @param {object} user - User object from session
   * @returns {Promise<object>} Finance data
   */
  async getFinanceData(user) {
    try {
      return {
        message: 'This is protected finance data',
        user: {
          id: user.staff_id || user.id,
          name: user.name || `${user.fname || ''} ${user.lname || ''}`.trim()
        }
      };
    } catch (error) {
      logger.error('Failed to get finance data', { error: error.message });
      throw new AppError('Failed to fetch finance data', 500, 'FINANCE_DATA_ERROR');
    }
  }

  /**
   * Get user advances
   * @param {string|number} userId - User ID
   * @param {object} options - Query options (page, limit, status)
   * @returns {Promise<object>} Advances data with pagination
   */
  async getUserAdvances(userId, options = {}) {
    try {
      const { page = 1, limit = 50, status } = options;
      const offset = (parseInt(page) - 1) * parseInt(limit);
      const maxLimit = Math.min(parseInt(limit), 100); // Max 100 per page

      const advances = await this.model.getUserAdvances(userId, {
        limit: maxLimit,
        offset,
        status
      });

      const total = await this.model.count({ user_id: userId });

      return {
        data: advances,
        pagination: {
          page: parseInt(page),
          limit: maxLimit,
          total,
          totalPages: Math.ceil(total / maxLimit)
        }
      };
    } catch (error) {
      logger.error('Failed to get user advances', { userId, error: error.message });
      throw new AppError('Failed to fetch advances', 500, 'ADVANCES_FETCH_ERROR');
    }
  }

  /**
   * Get user missions
   * @param {string|number} userId - User ID
   * @param {object} options - Query options (page, limit, status)
   * @returns {Promise<object>} Missions data with pagination
   */
  async getUserMissions(userId, options = {}) {
    try {
      const { page = 1, limit = 50, status } = options;
      const offset = (parseInt(page) - 1) * parseInt(limit);
      const maxLimit = Math.min(parseInt(limit), 100); // Max 100 per page

      const missions = await this.model.getUserMissions(userId, {
        limit: maxLimit,
        offset,
        status
      });

      const total = await this.model.count({ user_id: userId });

      return {
        data: missions,
        pagination: {
          page: parseInt(page),
          limit: maxLimit,
          total,
          totalPages: Math.ceil(total / maxLimit)
        }
      };
    } catch (error) {
      logger.error('Failed to get user missions', { userId, error: error.message });
      throw new AppError('Failed to fetch missions', 500, 'MISSIONS_FETCH_ERROR');
    }
  }

  /**
   * Get budgets
   * @param {object} options - Query options (page, limit, divisionId, year)
   * @returns {Promise<object>} Budgets data with pagination
   */
  async getBudgets(options = {}) {
    try {
      const { page = 1, limit = 100, divisionId, year } = options;
      const offset = (parseInt(page) - 1) * parseInt(limit);
      const maxLimit = Math.min(parseInt(limit), 200); // Max 200 per page

      const budgets = await this.model.getBudgets({
        limit: maxLimit,
        offset,
        divisionId,
        year
      });

      // Count total (simplified - adjust based on your needs)
      const total = budgets.length;

      return {
        data: budgets,
        pagination: {
          page: parseInt(page),
          limit: maxLimit,
          total,
          totalPages: Math.ceil(total / maxLimit)
        }
      };
    } catch (error) {
      logger.error('Failed to get budgets', { error: error.message });
      throw new AppError('Failed to fetch budgets', 500, 'BUDGETS_FETCH_ERROR');
    }
  }

  /**
   * Get finance statistics
   * @param {string|number} userId - User ID
   * @returns {Promise<object>} Statistics data
   */
  async getFinanceStats(userId) {
    try {
      const stats = await this.model.getFinanceStats(userId);
      return stats;
    } catch (error) {
      logger.error('Failed to get finance stats', { userId, error: error.message });
      throw new AppError('Failed to fetch statistics', 500, 'STATS_FETCH_ERROR');
    }
  }

  /**
   * Test database connection
   * @returns {Promise<object>} Test result
   */
  async testDatabase() {
    try {
      const result = await this.model.query('SELECT 1 as test');
      return {
        success: true,
        message: 'Database connection successful',
        data: result
      };
    } catch (error) {
      logger.error('Database test failed', { error: error.message });
      throw new AppError('Database query failed', 500, 'DATABASE_ERROR');
    }
  }
}

module.exports = new FinanceService();

