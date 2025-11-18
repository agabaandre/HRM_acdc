require('dotenv').config();

module.exports = {
  port: process.env.PORT || 3003,
  nodeEnv: process.env.NODE_ENV || 'development',
  sessionSecret: process.env.SESSION_SECRET || 'africacdc-finance-secret-key-change-in-production',
  clientUrl: process.env.CLIENT_URL || 'http://localhost:3002',
  assetsBasePath: process.env.ASSETS_BASE_PATH || '/opt/homebrew/var/www/staff',
  ciBaseUrl: process.env.CI_BASE_URL || 'http://localhost/staff',
  database: {
    host: process.env.DB_HOST || '127.0.0.1',
    port: process.env.DB_PORT || 3306,
    user: process.env.DB_USERNAME || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_DATABASE || 'approvals_management',
    charset: process.env.DB_CHARSET || 'utf8mb4',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    enableKeepAlive: true,
    keepAliveInitialDelay: 0
  }
};

