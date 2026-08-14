/**
 * Base Model Class
 * Provides common database operations and memory-efficient query methods
 */
const db = require('../database');

class BaseModel {
  constructor(tableName) {
    this.tableName = tableName;
  }

  /**
   * Execute a query with automatic connection cleanup
   * @param {string} sql - SQL query
   * @param {array} params - Query parameters
   * @returns {Promise<array>}
   */
  async query(sql, params = []) {
    try {
      const results = await db.query(sql, params);
      return results;
    } catch (error) {
      console.error(`[${this.tableName}] Query error:`, error);
      throw error;
    }
  }

  /**
   * Execute a query and return first row
   * @param {string} sql - SQL query
   * @param {array} params - Query parameters
   * @returns {Promise<object|null>}
   */
  async queryOne(sql, params = []) {
    const results = await this.query(sql, params);
    return results[0] || null;
  }

  /**
   * Find all records with pagination (memory efficient)
   * @param {object} options - Query options
   * @param {number} options.limit - Number of records to return
   * @param {number} options.offset - Offset for pagination
   * @param {object} options.where - WHERE conditions
   * @param {string} options.orderBy - ORDER BY clause
   * @returns {Promise<array>}
   */
  async findAll(options = {}) {
    const {
      limit = 100, // Default limit to prevent memory issues
      offset = 0,
      where = {},
      orderBy = null
    } = options;

    let sql = `SELECT * FROM ${this.tableName}`;
    const params = [];

    // Build WHERE clause
    const whereClauses = [];
    Object.keys(where).forEach((key, index) => {
      whereClauses.push(`${key} = ?`);
      params.push(where[key]);
    });

    if (whereClauses.length > 0) {
      sql += ` WHERE ${whereClauses.join(' AND ')}`;
    }

    // Add ORDER BY
    if (orderBy) {
      sql += ` ORDER BY ${orderBy}`;
    }

    // Add LIMIT and OFFSET
    sql += ` LIMIT ? OFFSET ?`;
    params.push(limit, offset);

    return await this.query(sql, params);
  }

  /**
   * Find a single record by ID
   * @param {number|string} id - Record ID
   * @param {string} idColumn - ID column name (default: 'id')
   * @returns {Promise<object|null>}
   */
  async findById(id, idColumn = 'id') {
    const sql = `SELECT * FROM ${this.tableName} WHERE ${idColumn} = ? LIMIT 1`;
    return await this.queryOne(sql, [id]);
  }

  /**
   * Count records (memory efficient)
   * @param {object} where - WHERE conditions
   * @returns {Promise<number>}
   */
  async count(where = {}) {
    let sql = `SELECT COUNT(*) as total FROM ${this.tableName}`;
    const params = [];

    const whereClauses = [];
    Object.keys(where).forEach((key) => {
      whereClauses.push(`${key} = ?`);
      params.push(where[key]);
    });

    if (whereClauses.length > 0) {
      sql += ` WHERE ${whereClauses.join(' AND ')}`;
    }

    const result = await this.queryOne(sql, params);
    return result ? result.total : 0;
  }

  /**
   * Create a new record
   * @param {object} data - Record data
   * @returns {Promise<object>}
   */
  async create(data) {
    const columns = Object.keys(data);
    const values = Object.values(data);
    const placeholders = columns.map(() => '?').join(', ');

    const sql = `INSERT INTO ${this.tableName} (${columns.join(', ')}) VALUES (${placeholders})`;
    const result = await this.query(sql, values);
    
    return {
      id: result.insertId,
      ...data
    };
  }

  /**
   * Update a record
   * @param {number|string} id - Record ID
   * @param {object} data - Update data
   * @param {string} idColumn - ID column name (default: 'id')
   * @returns {Promise<boolean>}
   */
  async update(id, data, idColumn = 'id') {
    const columns = Object.keys(data);
    const values = Object.values(data);
    const setClause = columns.map(col => `${col} = ?`).join(', ');

    const sql = `UPDATE ${this.tableName} SET ${setClause} WHERE ${idColumn} = ?`;
    const result = await this.query(sql, [...values, id]);
    
    return result.affectedRows > 0;
  }

  /**
   * Delete a record
   * @param {number|string} id - Record ID
   * @param {string} idColumn - ID column name (default: 'id')
   * @returns {Promise<boolean>}
   */
  async delete(id, idColumn = 'id') {
    const sql = `DELETE FROM ${this.tableName} WHERE ${idColumn} = ?`;
    const result = await this.query(sql, [id]);
    
    return result.affectedRows > 0;
  }

  /**
   * Execute a transaction
   * @param {function} callback - Transaction callback
   * @returns {Promise<any>}
   */
  async transaction(callback) {
    const connection = await db.beginTransaction();
    try {
      const result = await callback(connection);
      await db.commit(connection);
      return result;
    } catch (error) {
      await db.rollback(connection);
      throw error;
    }
  }
}

module.exports = BaseModel;

