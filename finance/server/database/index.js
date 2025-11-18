const mysql = require('mysql2/promise');
const config = require('../config');

// Create connection pool
const pool = mysql.createPool({
  host: config.database.host,
  port: config.database.port,
  user: config.database.user,
  password: config.database.password,
  database: config.database.database,
  charset: config.database.charset,
  waitForConnections: config.database.waitForConnections,
  connectionLimit: config.database.connectionLimit,
  queueLimit: config.database.queueLimit,
  enableKeepAlive: config.database.enableKeepAlive,
  keepAliveInitialDelay: config.database.keepAliveInitialDelay
});

// Test database connection
async function testConnection() {
  try {
    const connection = await pool.getConnection();
    console.log('Database connected successfully');
    connection.release();
    return true;
  } catch (error) {
    console.error('Database connection error:', error.message);
    return false;
  }
}

// Execute a query
async function query(sql, params = []) {
  try {
    const [results] = await pool.execute(sql, params);
    return results;
  } catch (error) {
    console.error('Database query error:', error);
    throw error;
  }
}

// Execute a query and return first row
async function queryOne(sql, params = []) {
  const results = await query(sql, params);
  return results[0] || null;
}

// Begin a transaction
async function beginTransaction() {
  const connection = await pool.getConnection();
  await connection.beginTransaction();
  return connection;
}

// Commit a transaction
async function commit(connection) {
  await connection.commit();
  connection.release();
}

// Rollback a transaction
async function rollback(connection) {
  await connection.rollback();
  connection.release();
}

// Close all connections (for graceful shutdown)
async function close() {
  await pool.end();
}

module.exports = {
  pool,
  query,
  queryOne,
  beginTransaction,
  commit,
  rollback,
  close,
  testConnection
};

