const MySQLStore = require('express-mysql-session')(require('express-session'));
const config = require('../config');

// Create MySQL session store configuration
const sessionStoreOptions = {
  host: config.database.host,
  port: config.database.port,
  user: config.database.user,
  password: config.database.password,
  database: config.database.database,
  charset: config.database.charset,
  clearExpired: true, // Automatically clear expired sessions
  checkExpirationInterval: 900000, // Check for expired sessions every 15 minutes
  expiration: 86400000, // Session expiration time (24 hours in milliseconds)
  createDatabaseTable: true, // Automatically create the sessions table if it doesn't exist
  schema: {
    tableName: 'finance_sessions', // Custom table name to avoid conflicts
    columnNames: {
      session_id: 'session_id',
      expires: 'expires',
      data: 'data'
    }
  }
};

// Create MySQL session store
const sessionStore = new MySQLStore(sessionStoreOptions);

// Handle store errors
sessionStore.on('error', (error) => {
  console.error('Session store error:', error);
});

// Log when store is ready (this event may not fire, so we'll log on first use)
if (config.nodeEnv === 'development') {
  console.log('MySQL session store initialized');
  console.log('Session store config:', {
    host: sessionStoreOptions.host,
    database: sessionStoreOptions.database,
    table: sessionStoreOptions.schema.tableName
  });
}

module.exports = sessionStore;

