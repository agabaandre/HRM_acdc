/**
 * Finance Model
 * Handles all finance-related database operations
 */
const BaseModel = require('./BaseModel');

class FinanceModel extends BaseModel {
  constructor() {
    super('finance'); // Adjust table name as needed
  }

  /**
   * Get user's advances with pagination
   * @param {number} userId - User ID
   * @param {object} options - Query options
   * @returns {Promise<array>}
   */
  async getUserAdvances(userId, options = {}) {
    const {
      limit = 50,
      offset = 0,
      status = null
    } = options;

    let sql = `
      SELECT * FROM advances 
      WHERE user_id = ? 
    `;
    const params = [userId];

    if (status) {
      sql += ` AND status = ?`;
      params.push(status);
    }

    sql += ` ORDER BY created_at DESC LIMIT ? OFFSET ?`;
    params.push(limit, offset);

    return await this.query(sql, params);
  }

  /**
   * Get user's missions with pagination
   * @param {number} userId - User ID
   * @param {object} options - Query options
   * @returns {Promise<array>}
   */
  async getUserMissions(userId, options = {}) {
    const {
      limit = 50,
      offset = 0,
      status = null
    } = options;

    let sql = `
      SELECT * FROM missions 
      WHERE user_id = ? 
    `;
    const params = [userId];

    if (status) {
      sql += ` AND status = ?`;
      params.push(status);
    }

    sql += ` ORDER BY created_at DESC LIMIT ? OFFSET ?`;
    params.push(limit, offset);

    return await this.query(sql, params);
  }

  /**
   * Get budgets with pagination
   * @param {object} options - Query options
   * @returns {Promise<array>}
   */
  async getBudgets(options = {}) {
    const {
      limit = 100,
      offset = 0,
      divisionId = null,
      year = null
    } = options;

    let sql = `SELECT * FROM budgets WHERE 1=1`;
    const params = [];

    if (divisionId) {
      sql += ` AND division_id = ?`;
      params.push(divisionId);
    }

    if (year) {
      sql += ` AND year = ?`;
      params.push(year);
    }

    sql += ` ORDER BY year DESC, division_id ASC LIMIT ? OFFSET ?`;
    params.push(limit, offset);

    return await this.query(sql, params);
  }

  /**
   * Get finance statistics (memory efficient - single query)
   * @param {number} userId - User ID
   * @returns {Promise<object>}
   */
  async getFinanceStats(userId) {
    const sql = `
      SELECT 
        (SELECT COUNT(*) FROM advances WHERE user_id = ?) as total_advances,
        (SELECT COUNT(*) FROM advances WHERE user_id = ? AND status = 'pending') as pending_advances,
        (SELECT COUNT(*) FROM missions WHERE user_id = ?) as total_missions,
        (SELECT COUNT(*) FROM missions WHERE user_id = ? AND status = 'pending') as pending_missions
    `;
    
    return await this.queryOne(sql, [userId, userId, userId, userId]);
  }
}

module.exports = FinanceModel;

